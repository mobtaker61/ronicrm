<?php

namespace App\Jobs;

use App\Models\TelegramGroup;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TelegramForwardToGroupsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string|int $fromPeer,
        public int $messageId,
        public array $groupIds,
        public string $forwardId,
        public ?array $groupTitles = null
    ) {}

    public function handle(): void
    {
        $conn = TelegramUserConnection::getActive();
        if (! $conn || ! $conn->isConnected()) {
            $this->setProgress('error', 0, 0, 0, [], 'No active Telegram connection.');
            return;
        }

        $service = new MadelineProtoService($conn);
        $total = count($this->groupIds);
        $sent = 0;
        $failed = 0;
        $results = [];
        $titles = $this->groupTitles ?? [];

        $this->setProgress('running', 0, $sent, $failed, $results);
        $service->start();

        foreach ($this->groupIds as $i => $groupId) {
            $title = $titles[$groupId] ?? null;
            $r = $service->forwardMessageToGroup($this->fromPeer, $this->messageId, $groupId);
            if ($r['success']) {
                $sent++;
                $results[] = ['group_id' => $groupId, 'status' => 'sent'];
                $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)
                    ->where('telegram_group_id', $groupId)
                    ->first();
                if ($tg) {
                    $tg->markCanPost();
                }
            } else {
                $failed++;
                $err = $r['error'] ?? '';
                $results[] = ['group_id' => $groupId, 'status' => 'failed', 'error' => $err];
                if (static::isNonPostableError($err)) {
                    $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)
                        ->where('telegram_group_id', $groupId)
                        ->first();
                    if ($tg) {
                        $tg->markCannotPost($err);
                    }
                }
            }
            $this->setProgress('running', $i + 1, $sent, $failed, $results);
            usleep(rand(2, 5) * 500000);
        }

        $this->setProgress('completed', $total, $sent, $failed, $results);
    }

    protected function setProgress(string $status, int $processed, int $sent, int $failed, array $results, ?string $error = null): void
    {
        $key = 'telegram_forward_' . $this->forwardId;
        $data = [
            'status' => $status,
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ];
        if ($error !== null) {
            $data['error'] = $error;
        }
        Cache::put($key, $data, now()->addHours(24));
    }

    protected static function isNonPostableError(string $error): bool
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
