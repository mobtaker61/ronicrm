<?php

namespace App\Http\Controllers;

use App\Jobs\TelegramCrawlJob;
use App\Models\CampaignTemplate;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramCrawlerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $conn = TelegramUserConnection::getActive();
        $templates = CampaignTemplate::where('type', 'telegram')->orderBy('name')->get(['id', 'name', 'content']);
        return Inertia::render('TelegramCrawler/Index', [
            'telegramConnected' => $conn !== null,
            'templates' => $templates,
        ]);
    }

    public function groups(Request $request): JsonResponse
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn) {
            return response()->json(['error' => 'Not connected'], 403);
        }
        $cacheKey = 'telegram_groups_' . $conn->id;
        $forceRefresh = $request->boolean('refresh');
        $cached = Cache::get($cacheKey);

        // Return cached list unless user explicitly requested refresh
        if (!$forceRefresh && $cached !== null && count($cached) > 0) {
            return response()->json(['groups' => $cached]);
        }

        try {
            $service = new MadelineProtoService($conn);
            $dialogs = $service->getDialogs();
            $fresh = array_filter($dialogs, fn ($d) => in_array($d['type'] ?? '', ['group', 'supergroup', 'channel']) || (isset($d['id']) && str_starts_with((string) $d['id'], '-')));
            $fresh = array_values($fresh);

            // Merge: key by id, fresh overwrites; keep existing cached entries that weren't in fresh (group left/changed)
            $byId = [];
            foreach ($cached ?? [] as $g) {
                $byId[$g['id'] ?? ''] = $g;
            }
            foreach ($fresh as $g) {
                $byId[$g['id'] ?? ''] = $g;
            }
            $groups = array_values($byId);
            usort($groups, fn ($a, $b) => strcasecmp($a['title'] ?? '', $b['title'] ?? ''));

            Cache::put($cacheKey, $groups, now()->addDays(1));
            return response()->json(['groups' => $groups]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function crawl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|string|max:50',
            'limit' => 'required|integer|min:1|max:200',
            'message' => 'required|string|max:4000',
            'template_id' => 'nullable|exists:campaign_templates,id',
        ]);
        $conn = TelegramUserConnection::getActive();
        if (!$conn) {
            return response()->json(['error' => 'Not connected. Connect in Settings → Telegram.'], 403);
        }
        $messageText = $validated['message'];
        if (!empty($validated['template_id'])) {
            $tmpl = CampaignTemplate::find($validated['template_id']);
            if ($tmpl && $tmpl->type === 'telegram') {
                $messageText = $tmpl->content;
            }
        }
        $crawlId = Str::uuid()->toString();
        // Write initial cache so frontend gets instant feedback (status: queued)
        Cache::put('telegram_crawl_' . $crawlId, [
            'status' => 'queued',
            'phase' => 'queued',
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'error' => null,
        ], now()->addHours(24));

        TelegramCrawlJob::dispatch(
            $validated['group_id'],
            $validated['limit'],
            $messageText,
            $crawlId,
            $validated['template_id'] ?? null
        );
        \Illuminate\Support\Facades\Log::info('TelegramCrawlJob dispatched', ['crawl_id' => $crawlId, 'group_id' => $validated['group_id']]);
        return response()->json(['crawl_id' => $crawlId]);
    }

    public function crawlStatus(string $crawlId): JsonResponse
    {
        $data = Cache::get('telegram_crawl_' . $crawlId);
        if (!$data) {
            return response()->json(['status' => 'pending']);
        }
        return response()->json($data);
    }
}
