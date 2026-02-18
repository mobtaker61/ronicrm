<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:fetch-incoming', function () {
    $this->info('Running TelegramFetchIncomingJob...');
    dispatch_sync(new \App\Jobs\TelegramFetchIncomingJob);
    $this->info('Done.');
})->purpose('Fetch incoming Telegram DMs and sync to inbox (run manually)');

Artisan::command('telegram:sync-contacts', function () {
    $conn = \App\Models\TelegramUserConnection::getActive();
    if (!$conn || !$conn->isConnected()) {
        $this->error('No active Telegram connection. Connect in Settings → Telegram first.');
        return 1;
    }
    $contacts = \App\Models\CustomerContact::where('type', 'telegram')
        ->whereNotNull('value')->where('value', '!=', '')
        ->whereNotNull('customer_id')
        ->get()
        ->filter(fn ($c) => ctype_digit((string) trim($c->value)));
    $total = $contacts->pluck('customer_id')->unique()->count();
    $this->info("Found $total contacts to sync.");
    if ($total === 0) {
        $this->warn('No numeric Telegram IDs found. Sync skipped.');
        return 0;
    }
    $this->info('Running TelegramSyncContactsJob...');
    $syncId = \Illuminate\Support\Str::uuid()->toString();
    \Illuminate\Support\Facades\Cache::put('telegram_sync_' . $syncId, [
        'status' => 'running',
        'processed' => 0,
        'updated' => 0,
        'total' => null,
        'failed' => 0,
    ], now()->addHours(24));
    try {
        \App\Jobs\TelegramSyncContactsJob::dispatchSync($syncId);
        $data = \Illuminate\Support\Facades\Cache::get('telegram_sync_' . $syncId);
        if (($data['status'] ?? '') === 'error') {
            $this->error('Sync failed: ' . ($data['error'] ?? 'Unknown'));
            return 1;
        }
        $this->info('Done. Processed: ' . ($data['processed'] ?? 0) . ', Updated: ' . ($data['updated'] ?? 0) . ', Failed: ' . ($data['failed'] ?? 0));
    } catch (\Throwable $e) {
        $this->error('Failed: ' . $e->getMessage());
        throw $e;
    }
    return 0;
})->purpose('Sync contact info from Telegram (run manually when queue does not work)');
