<?php

namespace App\Console\Commands;

use App\Models\TelegramUserConnection;
use App\Telegram\IncomingMessageHandler;
use Illuminate\Console\Command;

class ListenTelegramIncoming extends Command
{
    protected $signature = 'telegram:listen-incoming';
    protected $description = 'Listen for incoming Telegram DMs in real-time (run in screen/supervisor)';

    public function handle(): int
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            $this->error('No active Telegram user connection. Connect via Settings first.');
            return 1;
        }
        $sessionPath = $conn->getSessionPath();
        $apiId = (int) $conn->getApiId();
        $apiHash = $conn->getApiHash();
        if (!$apiId || !$apiHash) {
            $apiId = (int) config('services.telegram.api_id');
            $apiHash = config('services.telegram.api_hash');
        }

        $settings = new \danog\MadelineProto\Settings();
        $settings->getAppInfo()->setApiId($apiId)->setApiHash($apiHash);
        $settings->getLogger()
            ->setType(\danog\MadelineProto\Logger::LOGGER_FILE)
            ->setExtra(storage_path('logs/madelineproto.log'));

        $this->warn('══════════════════════════════════════════════════════════════');
        $this->warn('حالت دریافت لحظه‌ای: پیام‌های ورودی در DB توسط EventHandler ذخیره می‌شوند.');
        $this->warn('زمان‌بندی telegram:fetch-incoming به‌طور خودکار غیرفعال می‌شود (نیازی به polling نیست).');
        $this->warn('ارسال از اینباکس با همان session از طریق IPC MadelineProto 8 امکان‌پذیر است؛ از چند درخواست همزمان سنگین پرهیز کنید.');
        $this->warn('══════════════════════════════════════════════════════════════');

        $marker = \App\Services\MadelineProtoService::daemonListenMarkerPath($conn);
        file_put_contents($marker, (string) getmypid());
        register_shutdown_function(static function () use ($marker): void {
            @unlink($marker);
        });

        $this->info('Starting Telegram DM listener (Ctrl+C to stop)...');
        $this->info('Session: ' . $sessionPath);

        IncomingMessageHandler::startAndLoop($sessionPath, $settings);

        return 0;
    }
}
