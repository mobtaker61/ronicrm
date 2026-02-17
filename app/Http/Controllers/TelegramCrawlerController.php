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
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json(['groups' => $cached]);
        }
        try {
            $service = new MadelineProtoService($conn);
            $dialogs = $service->getDialogs();
            $groups = array_filter($dialogs, fn ($d) => in_array($d['type'] ?? '', ['group', 'supergroup', 'channel']) || (isset($d['id']) && str_starts_with((string) $d['id'], '-')));
            $groups = array_values($groups);
            Cache::put($cacheKey, $groups, now()->addMinutes(5));
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
        TelegramCrawlJob::dispatch(
            $validated['group_id'],
            $validated['limit'],
            $messageText,
            $crawlId,
            $validated['template_id'] ?? null
        );
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
