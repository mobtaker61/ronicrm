<?php

namespace App\Console\Commands;

use App\Models\CampaignTemplate;
use App\Models\TelegramGroup;
use App\Models\TelegramScheduledSend;
use App\Models\TelegramScheduledSendItem;
use App\Models\TelegramScheduledSendRun;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramProcessScheduledSends extends Command
{
    protected $signature = 'telegram:process-scheduled-sends';

    protected $description = 'Process due Telegram scheduled sends (template or forward to category groups)';

    public function handle(): int
    {
        try {
            $conn = TelegramUserConnection::getActive();
            if (! $conn || ! $conn->isConnected()) {
                return 0;
            }

            $due = TelegramScheduledSend::dueNow()->get();
            if ($due->isEmpty()) {
                return 0;
            }

            foreach ($due as $schedule) {
                try {
                    $this->processOne($schedule, $conn);
                } catch (\Throwable $e) {
                    Log::error('Telegram scheduled send processOne failed', [
                        'schedule_id' => $schedule->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
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

    protected function processOne(TelegramScheduledSend $schedule, TelegramUserConnection $conn): void
    {
        $today = now()->toDateString();

        $run = TelegramScheduledSendRun::firstOrCreate(
            [
                'telegram_scheduled_send_id' => $schedule->id,
                'run_date' => $today,
            ],
            ['status' => 'in_progress']
        );

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
                    'telegram_group_id' => $groupId,
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

        Log::info('Telegram scheduled send processed', [
            'schedule_id' => $schedule->id,
            'run_id' => $run->id,
            'processed' => $pendingItems->count(),
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
