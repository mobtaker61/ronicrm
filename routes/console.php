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
        $this->info('Done. Updated: ' . ($data['updated'] ?? 0) . ', Processed: ' . ($data['processed'] ?? 0));
    } catch (\Throwable $e) {
        $this->error('Failed: ' . $e->getMessage());
        throw $e;
    }
})->purpose('Sync contact info from Telegram (run manually when queue does not work)');
