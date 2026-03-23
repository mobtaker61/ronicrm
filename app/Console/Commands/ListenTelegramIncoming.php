<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\TelegramUserConnection;
use App\Support\OrganizationContext;
use App\Telegram\IncomingMessageHandler;
use Illuminate\Console\Command;

class ListenTelegramIncoming extends Command
{
    protected $signature = 'telegram:listen-incoming {--organization_id=}';
    protected $description = 'Listen for incoming Telegram DMs in real-time (run in screen/supervisor)';

    public function handle(): int
    {
        $organizationId = $this->option('organization_id');
        if ($organizationId) {
            OrganizationContext::setOrganizationId((int) $organizationId);
        } elseif (! OrganizationContext::hasOrganization()) {
            $fallback = Organization::query()->where('is_active', true)->orderBy('id')->value('id');
            OrganizationContext::setOrganizationId($fallback ? (int) $fallback : null);
        }

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
        $this->warn('حالت دریافت لحظه‌ای: فقط این فرآیند مالک session است.');
        $this->warn('تا وقتی این daemon روشن است: ارسال از اینباکس، کراول گروه و Madeline از cron غیرفعال می‌شود.');
        $this->warn('برای ارسال/کراول از وب، ابتدا این سرویس را در Supervisor متوقف کنید؛ برای دریافت آنی دوباره روشن کنید.');
        $this->warn('زمان‌بندی telegram:fetch-incoming وقتی این PID زنده است اجرا نمی‌شود.');
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
