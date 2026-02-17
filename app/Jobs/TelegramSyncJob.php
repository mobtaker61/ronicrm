<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sync incoming Telegram messages from user account to telegram_messages table.
 * Run via scheduler (e.g. every 5 min) or on-demand.
 */
class TelegramSyncJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            return;
        }
        try {
            $service = new MadelineProtoService($conn);
            $service->start();
            // TODO: Implement dialog sync - get recent messages from each dialog
            // and insert into telegram_messages with direction=incoming
            // For now this is a placeholder for future implementation
        } catch (\Throwable) {
            // Silent fail - sync can retry later
        }
    }
}
