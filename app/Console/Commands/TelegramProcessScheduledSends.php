<?php

namespace App\Console\Commands;

use App\Models\CampaignTemplate;
use App\Models\Organization;
use App\Models\TelegramGroup;
use App\Models\TelegramScheduledSend;
use App\Models\TelegramScheduledSendItem;
use App\Models\TelegramScheduledSendRun;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use App\Support\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TelegramProcessScheduledSends extends Command
{
    protected $signature = 'telegram:process-scheduled-sends {--organization_id=}';

    protected $description = 'Process due Telegram scheduled sends (template or forward to category groups)';

    public function handle(): int
    {
        try {
            if (! Schema::hasTable('telegram_scheduled_send_runs')) {
                $dbName = DB::connection()->getDatabaseName();
                Log::error('Telegram scheduled send: telegram_scheduled_send_runs table does not exist.', [
                    'database' => $dbName,
                    'connection' => config('database.default'),
                    'cwd' => getcwd(),
                    'hint' => 'Cron may be using wrong working directory. Ensure crontab runs: cd /path/to/project && php artisan schedule:run',
                ]);

                return 1;
            }

            $organizationIds = $this->resolveOrganizationIds();
            foreach ($organizationIds as $organizationId) {
                OrganizationContext::setOrganizationId((int) $organizationId);
                $due = TelegramScheduledSend::dueNow()->get();
                if ($due->isEmpty()) {
                    continue;
                }

                foreach ($due as $schedule) {
                    $scheduleConn = TelegramUserConnection::where('user_id', $schedule->user_id)->where('status', 'connected')->first();
                    if (! $scheduleConn || ! $scheduleConn->isConnected()) {
                        continue;
                    }
                    try {
                        $this->processOne($schedule, $scheduleConn);
                    } catch (\Throwable $e) {
                        Log::error('Telegram scheduled send processOne failed', [
                            'organization_id' => $organizationId,
                            'schedule_id' => $schedule->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }

            return 0;
        } catch (\Throwable $e) {
            Log::error('Telegram process-scheduled-sends command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * @return array<int, int>
     */
    protected function resolveOrganizationIds(): array
    {
        $target = $this->option('organization_id');
        if ($target) {
            return [(int) $target];
        }

        return Organization::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function processOne(TelegramScheduledSend $schedule, TelegramUserConnection $conn): void
    {
        $lockKey = 'telegram_scheduled_send_' . $schedule->id;
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            Log::info('Telegram scheduled send: skip, already processing', ['schedule_id' => $schedule->id]);

            return;
        }

        try {
            $this->processOneLocked($schedule, $conn);
        } finally {
            $lock->release();
        }
    }

    protected function processOneLocked(TelegramScheduledSend $schedule, TelegramUserConnection $conn): void
    {
        $today = now()->toDateString();

        $run = TelegramScheduledSendRun::firstOrCreate(
            [
                'telegram_scheduled_send_id' => $schedule->id,
                'schedule_version' => (int) ($schedule->version ?? 1),
                'run_date' => $today,
            ],
            ['status' => 'in_progress']
        );

        $run->refresh();

        $groupIds = TelegramGroup::where('telegram_user_connection_id', $conn->id)
            ->active()
            ->where('can_post', true)
            ->where('telegram_group_category_id', $schedule->telegram_group_category_id)
            ->pluck('telegram_group_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if (empty($groupIds)) {
            $run->markCompleted();
            $schedule->increment('runs_count');
            $schedule->update(['last_sent_at' => now()]);
            if ($schedule->runs_count >= $schedule->days_count) {
                $schedule->update(['status' => 'completed']);
            }
            Log::info('Telegram scheduled send: no groups in category', ['schedule_id' => $schedule->id]);

            return;
        }

        foreach ($groupIds as $groupId) {
            TelegramScheduledSendItem::firstOrCreate(
                [
                    'telegram_scheduled_send_run_id' => $run->id,
                    'telegram_group_id' => (string) $groupId,
                ],
                ['status' => 'pending']
            );
        }

        $pendingItems = $run->items()->where('status', 'pending')->limit(10)->get();
        if ($pendingItems->isEmpty()) {
            $run->markCompleted();
            $schedule->increment('runs_count');
            $schedule->update(['last_sent_at' => now()]);
            if ($schedule->runs_count >= $schedule->days_count) {
                $schedule->update(['status' => 'completed']);
            }
            Log::info('Telegram scheduled send run completed', ['schedule_id' => $schedule->id, 'run_id' => $run->id]);

            return;
        }

        set_time_limit(300);
        $service = new MadelineProtoService($conn);
        $service->start();

        $dbGroups = TelegramGroup::where('telegram_user_connection_id', $conn->id)
            ->whereIn('telegram_group_id', $pendingItems->pluck('telegram_group_id'))
            ->get()
            ->keyBy('telegram_group_id');

        if ($schedule->type === 'template') {
            $tmpl = CampaignTemplate::find($schedule->campaign_template_id);
            if (! $tmpl || $tmpl->type !== 'telegram') {
                Log::warning('Telegram scheduled send: template invalid', ['schedule_id' => $schedule->id]);
                $schedule->stop();

                return;
            }
            $imagePath = $tmpl->image ? storage_path('app/public/' . $tmpl->image) : null;

            foreach ($pendingItems as $item) {
                $groupId = $item->telegram_group_id;
                $group = $dbGroups->get($groupId);
                $langCode = $group?->language;
                $text = $tmpl->getContentForLanguage($langCode);
                $r = $service->sendGroupMessage($groupId, $text, $imagePath);
                if ($r['success']) {
                    $item->markSent();
                    $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)->where('telegram_group_id', $groupId)->first();
                    if ($tg) {
                        $tg->markCanPost();
                    }
                } else {
                    $err = $r['error'] ?? '';
                    $item->markFailed($err);
                    if ($this->isNonPostableError($err)) {
                        $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)->where('telegram_group_id', $groupId)->first();
                        if ($tg) {
                            $tg->markCannotPost($err);
                        }
                    }
                }
                usleep(rand(3000000, 6000000));
            }
        } else {
            $parsed = MadelineProtoService::parseTelegramPostLink($schedule->post_link ?? '');
            if (! $parsed) {
                Log::warning('Telegram scheduled send: invalid post link', ['schedule_id' => $schedule->id]);
                $schedule->stop();

                return;
            }

            foreach ($pendingItems as $item) {
                $groupId = $item->telegram_group_id;
                $r = $service->forwardMessageToGroup($parsed['from_peer'], $parsed['message_id'], $groupId);
                if ($r['success']) {
                    $item->markSent();
                    $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)->where('telegram_group_id', $groupId)->first();
                    if ($tg) {
                        $tg->markCanPost();
                    }
                } else {
                    $err = $r['error'] ?? '';
                    $item->markFailed($err);
                    if ($this->isNonPostableError($err)) {
                        $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)->where('telegram_group_id', $groupId)->first();
                        if ($tg) {
                            $tg->markCannotPost($err);
                        }
                    }
                }
                usleep(rand(2000000, 5000000));
            }
        }

        $stillPending = $run->items()->where('status', 'pending')->exists();
        if (! $stillPending) {
            $run->markCompleted();
            $schedule->increment('runs_count');
            $schedule->update(['last_sent_at' => now()]);
            if ($schedule->runs_count >= $schedule->days_count) {
                $schedule->update(['status' => 'completed']);
            }
        }

        $runCount = DB::table('telegram_scheduled_send_runs')->where('telegram_scheduled_send_id', $schedule->id)->count();
        $itemCount = DB::table('telegram_scheduled_send_items')->where('telegram_scheduled_send_run_id', $run->id)->count();
        $dbName = DB::connection()->getDatabaseName();

        Log::info('Telegram scheduled send processed', [
            'schedule_id' => $schedule->id,
            'schedule_version' => (int) ($schedule->version ?? 1),
            'run_id' => $run->id,
            'processed' => $pendingItems->count(),
            'db_runs_total' => $runCount,
            'db_items_in_run' => $itemCount,
            'database' => $dbName,
            'connection' => config('database.default'),
        ]);
    }

    protected function isNonPostableError(string $error): bool
    {
        $codes = [
            'CHAT_ADMIN_REQUIRED', 'CHAT_WRITE_FORBIDDEN', 'CHANNEL_PRIVATE',
            'USER_BANNED_IN_CHANNEL', 'PEER_ID_INVALID', 'MESSAGE_ID_INVALID',
        ];
        foreach ($codes as $code) {
            if (str_contains($error, $code)) {
                return true;
            }
        }

        return false;
    }
}
