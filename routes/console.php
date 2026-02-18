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
