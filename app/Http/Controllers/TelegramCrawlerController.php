<?php

namespace App\Http\Controllers;

use App\Jobs\TelegramCrawlJob;
use App\Jobs\TelegramSendToGroupsJob;
use App\Jobs\TelegramSyncContactsJob;
use App\Models\CampaignTemplate;
use App\Models\Language;
use App\Models\TelegramGroup;
use App\Models\TelegramGroupCategory;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $templates = CampaignTemplate::where('type', 'telegram')->orderBy('name')->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'content' => $t->content,
            'image' => $t->image ? asset('storage/' . $t->image) : null,
        ]);
        return Inertia::render('TelegramCrawler/Index', [
            'telegramConnected' => $conn !== null,
            'templates' => $templates,
        ]);
    }

    public function groupsIndex(Request $request): Response
    {
        $conn = TelegramUserConnection::getActive();

        $query = $conn
            ? TelegramGroup::with('category')->active()->where('telegram_user_connection_id', $conn->id)
            : null;

        if ($query) {
            if ($request->filled('category')) {
                $query->where('telegram_group_category_id', $request->category);
            }
            if ($request->filled('language')) {
                $query->where('language', $request->language);
            }
            $groups = $query->orderBy('title')->paginate(50)->withQueryString()
                ->through(fn ($g) => [
                    'id' => $g->id,
                    'telegram_group_id' => $g->telegram_group_id,
                    'title' => $g->title,
                    'type' => $g->type,
                    'category' => $g->category ? ['id' => $g->category->id, 'name' => $g->category->name] : null,
                    'language' => $g->language,
                    'can_post' => $g->can_post,
                    'last_error' => $g->last_error,
                    'last_crawled_message_id' => $g->last_crawled_message_id,
                    'last_synced_at' => $g->last_synced_at?->toIso8601String(),
                    'created_at' => $g->created_at->toIso8601String(),
                ]);
        } else {
            $groups = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
        }

        return Inertia::render('TelegramGroups/Index', [
            'telegramConnected' => $conn !== null,
            'groups' => $groups,
        ]);
    }

    public function groupsUpdate(Request $request, TelegramGroup $group): JsonResponse
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || $group->telegram_user_connection_id !== $conn->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'telegram_group_category_id' => ['nullable', 'exists:telegram_group_categories,id'],
            'language' => ['nullable', 'string', 'max:10', Rule::in(array_merge([''], Language::pluck('code')->toArray()))],
        ]);

        $update = [
            'telegram_group_category_id' => $validated['telegram_group_category_id'] ?? null ?: null,
            'language' => ($validated['language'] ?? null) ?: null,
        ];
        $group->update($update);
        $group->load('category');

        return response()->json(['success' => true, 'group' => [
            'id' => $group->id,
            'category' => $group->category ? ['id' => $group->category->id, 'name' => $group->category->name] : null,
            'language' => $group->language,
        ]]);
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

        // Return cached list unless user explicitly requested refresh (enrich with DB can_post)
        if (!$forceRefresh && $cached !== null && count($cached) > 0) {
            $dbGroups = TelegramGroup::with('category')->where('telegram_user_connection_id', $conn->id)
                ->whereIn('telegram_group_id', array_column($cached, 'id'))
                ->get()
                ->keyBy('telegram_group_id');
            foreach ($cached as &$g) {
                $g['can_post'] = true;
                $g['last_error'] = null;
                $g['category'] = null;
                $g['language'] = null;
                $db = $dbGroups->get($g['id'] ?? '');
                if ($db) {
                    $g['can_post'] = $db->can_post;
                    $g['last_error'] = $db->last_error;
                    $g['category'] = $db->category ? ['id' => $db->category->id, 'name' => $db->category->name] : null;
                    $g['language'] = $db->language;
                }
            }
            $result = $this->filterGroupsByCategoryAndLanguage($cached, $request);
            return response()->json(['groups' => $result]);
        }

        try {
            $service = new MadelineProtoService($conn);
            $dialogs = $service->getDialogs();
            $fresh = array_filter($dialogs, fn ($d) => in_array($d['type'] ?? '', ['group', 'supergroup', 'channel']) || (isset($d['id']) && str_starts_with((string) $d['id'], '-')));
            $fresh = array_values($fresh);
            $freshIds = array_map(fn ($g) => (string) ($g['id'] ?? ''), $fresh);

            $groups = $fresh;
            usort($groups, fn ($a, $b) => strcasecmp($a['title'] ?? '', $b['title'] ?? ''));

            // فقط وقتی لیست تلگرام خالی نیست: گروه‌هایی که دیگر در تلگرام نیستند (از آن‌ها خارج شده‌ایم) را غیرفعال کن
            if (count($freshIds) > 0) {
                TelegramGroup::where('telegram_user_connection_id', $conn->id)
                    ->whereNotIn('telegram_group_id', $freshIds)
                    ->update(['is_active' => false]);
            }

            // گروه‌های جدید را اضافه کن، گروه‌های موجود را به‌روز کن (عنوان، نوع، is_active=true)
            $dbGroups = TelegramGroup::with('category')->where('telegram_user_connection_id', $conn->id)
                ->whereIn('telegram_group_id', $freshIds)
                ->get()
                ->keyBy('telegram_group_id');

            foreach ($groups as &$g) {
                $g['can_post'] = true;
                $g['last_error'] = null;
                $g['category'] = null;
                $g['language'] = null;
                $db = $dbGroups->get((string) ($g['id'] ?? ''));
                if ($db) {
                    $g['can_post'] = $db->can_post;
                    $g['last_error'] = $db->last_error;
                    $g['category'] = $db->category ? ['id' => $db->category->id, 'name' => $db->category->name] : null;
                    $g['language'] = $db->language;
                }
                TelegramGroup::findOrCreateForConnection($conn->id, (string) ($g['id'] ?? ''), $g['title'] ?? null, $g['type'] ?? null);
            }

            Cache::put($cacheKey, $groups, now()->addDays(1));
            $result = $this->filterGroupsByCategoryAndLanguage($groups, $request);
            return response()->json(['groups' => $result]);
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

    public function sendToGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_ids' => 'required|array',
            'group_ids.*' => 'required|string|max:50',
            'template_id' => 'required|exists:campaign_templates,id',
            'group_titles' => 'nullable|array',
            'group_titles.*' => 'nullable|string|max:255',
        ]);
        $conn = TelegramUserConnection::getActive();
        if (!$conn) {
            return response()->json(['error' => 'Not connected.'], 403);
        }
        $tmpl = CampaignTemplate::find($validated['template_id']);
        if (!$tmpl || $tmpl->type !== 'telegram') {
            return response()->json(['error' => 'Invalid template.'], 400);
        }
        $sendId = Str::uuid()->toString();
        Cache::put('telegram_send_groups_' . $sendId, [
            'status' => 'queued',
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'results' => [],
        ], now()->addHours(24));

        $titles = [];
        foreach ($validated['group_ids'] as $gid) {
            $titles[$gid] = $validated['group_titles'][$gid] ?? null;
        }
        TelegramSendToGroupsJob::dispatch(
            $validated['group_ids'],
            (int) $validated['template_id'],
            $sendId,
            $titles
        );
        return response()->json(['send_id' => $sendId]);
    }

    public function sendToGroupsStatus(string $sendId): JsonResponse
    {
        $data = Cache::get('telegram_send_groups_' . $sendId);
        if (!$data) {
            return response()->json(['status' => 'pending']);
        }
        return response()->json($data);
    }

    /**
     * Start syncing Telegram contact data (name, phone, avatar) for extracted customers.
     * Use ?sync=1 to run immediately (without queue) - useful when no queue worker is running.
     */
    public function syncContacts(Request $request): JsonResponse
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn) {
            return response()->json(['error' => 'Not connected to Telegram.'], 403);
        }
        $syncId = Str::uuid()->toString();
        Cache::put('telegram_sync_' . $syncId, [
            'status' => 'queued',
            'processed' => 0,
            'updated' => 0,
            'total' => null,
            'failed' => 0,
        ], now()->addHours(24));

        $runSync = $request->boolean('sync', false);
        if ($runSync) {
            set_time_limit(1800);
            try {
                TelegramSyncContactsJob::dispatchSync($syncId);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Sync failed: ' . $e->getMessage()], 500);
            }
        } else {
            TelegramSyncContactsJob::dispatch($syncId);
        }
        return response()->json(['sync_id' => $syncId]);
    }

    public function syncContactsStatus(string $syncId): JsonResponse
    {
        $data = Cache::get('telegram_sync_' . $syncId);
        if (!$data) {
            return response()->json(['status' => 'pending']);
        }
        return response()->json($data);
    }

    protected function filterGroupsByCategoryAndLanguage(array $groups, Request $request): array
    {
        $categoryId = $request->input('category');
        $language = $request->input('language');
        if (!$categoryId && !$language) {
            return $groups;
        }
        return array_values(array_filter($groups, function ($g) use ($categoryId, $language) {
            if ($categoryId && ($g['category']['id'] ?? null) != $categoryId) {
                return false;
            }
            if ($language && ($g['language'] ?? null) !== $language) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Queue status for debugging (jobs pending, failed).
     */
    public function queueStatus(Request $request): JsonResponse
    {
        $jobsTable = config('queue.connections.database.table', 'jobs');
        $pending = 0;
        try {
            $pending = DB::table($jobsTable)->count();
        } catch (\Throwable $e) {
            // table might not exist
        }
        $failed = 0;
        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            //
        }
        return response()->json([
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
        ]);
    }
}
