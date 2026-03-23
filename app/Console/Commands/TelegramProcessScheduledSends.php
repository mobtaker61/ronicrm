<?php

namespace App\Console\Commands;

use App\Jobs\TelegramForwardToGroupsJob;
use App\Jobs\TelegramSendToGroupsJob;
use App\Models\TelegramGroup;
use App\Models\TelegramScheduledSend;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        $now = now();
        $claimed = DB::table('telegram_scheduled_sends')
            ->where('id', $schedule->id)
            ->where('status', 'active')
            ->whereRaw('runs_count < days_count')
            ->where(function ($q) use ($now) {
                $q->whereNull('last_sent_at')
                    ->orWhereRaw('DATE(last_sent_at) < ?', [$now->toDateString()]);
            })
            ->update([
                'last_sent_at' => $now,
                'runs_count' => DB::raw('runs_count + 1'),
                'updated_at' => $now,
            ]);

        if ($claimed === 0) {
            return;
        }

        $schedule->refresh();
        if ($schedule->runs_count >= $schedule->days_count) {
            $schedule->update(['status' => 'completed']);
        }

        $groupIds = TelegramGroup::where('telegram_user_connection_id', $conn->id)
            ->active()
            ->where('can_post', true)
            ->where('telegram_group_category_id', $schedule->telegram_group_category_id)
            ->pluck('telegram_group_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if (empty($groupIds)) {
            Log::info('Telegram scheduled send: no groups in category', [
                'schedule_id' => $schedule->id,
                'category_id' => $schedule->telegram_group_category_id,
            ]);

            return;
        }

        $titles = TelegramGroup::where('telegram_user_connection_id', $conn->id)
            ->whereIn('telegram_group_id', $groupIds)
            ->pluck('title', 'telegram_group_id')
            ->all();

        set_time_limit(300);

        if ($schedule->type === 'template') {
            $tmpl = $schedule->template;
            if (! $tmpl || $tmpl->type !== 'telegram') {
                Log::warning('Telegram scheduled send: template not found or invalid', ['schedule_id' => $schedule->id]);
                $schedule->stop();

                return;
            }
            $sendId = Str::uuid()->toString();
            TelegramSendToGroupsJob::dispatchSync($groupIds, (int) $schedule->campaign_template_id, $sendId, $titles);
        } else {
            $parsed = MadelineProtoService::parseTelegramPostLink($schedule->post_link ?? '');
            if (! $parsed) {
                Log::warning('Telegram scheduled send: invalid post link', ['schedule_id' => $schedule->id]);
                $schedule->stop();

                return;
            }
            $forwardId = Str::uuid()->toString();
            TelegramForwardToGroupsJob::dispatchSync(
                $parsed['from_peer'],
                $parsed['message_id'],
                $groupIds,
                $forwardId,
                $titles
            );
        }

        Log::info('Telegram scheduled send executed', [
            'schedule_id' => $schedule->id,
            'type' => $schedule->type,
            'groups_count' => count($groupIds),
        ]);
    }
}
