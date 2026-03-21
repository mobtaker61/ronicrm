<?php

namespace App\Console\Commands;

use App\Services\GoogleContactsSyncService;
use Illuminate\Console\Command;

class SyncGoogleContactsCommand extends Command
{
    protected $signature = 'google:sync-contacts';

    protected $description = 'Push all CRM customers to Google Contacts (one-way sync)';

    public function handle(GoogleContactsSyncService $sync): int
    {
        $result = $sync->syncAllCustomers();

        $this->info("Synced: {$result['success']}, Failed: {$result['failed']}");

        if ($result['success'] === 0 && $result['failed'] === 0 && $result['errors'] !== []) {
            $this->error($result['errors'][0] ?? 'Nothing synced.');

            return self::FAILURE;
        }

        foreach (array_slice($result['errors'], 0, 20) as $err) {
            $this->warn($err);
        }

        if ($result['errors'] !== [] && count($result['errors']) > 20) {
            $this->warn('… more errors omitted');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
