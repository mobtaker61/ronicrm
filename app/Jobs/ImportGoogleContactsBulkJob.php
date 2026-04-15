<?php

namespace App\Jobs;

use App\Models\GoogleContactsIntegration;
use App\Services\GoogleContactsOAuthService;
use App\Services\GoogleContactsSyncService;
use App\Support\GoogleContactsImportBulkState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportGoogleContactsBulkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 7200;

    public function __construct() {}

    public function uniqueId(): string
    {
        return 'google-contacts-import-bulk';
    }

    public function handle(GoogleContactsSyncService $sync, GoogleContactsOAuthService $oauth): void
    {
        $lock = Cache::lock('google-contacts-import-bulk', 7200);
        if (! $lock->get()) {
            GoogleContactsImportBulkState::markFailed('Another Google Contacts import is already running.');

            return;
        }

        try {
            $this->runHandle($sync, $oauth);
        } finally {
            $lock->release();
        }
    }

    protected function runHandle(GoogleContactsSyncService $sync, GoogleContactsOAuthService $oauth): void
    {
        @ini_set('max_execution_time', '0');
        set_time_limit(0);
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (! GoogleContactsIntegration::getSingleton()) {
            GoogleContactsImportBulkState::markFailed('Google Contacts is not connected.');

            return;
        }

        if (! $oauth->getValidAccessToken()) {
            GoogleContactsImportBulkState::markFailed('Token refresh failed. Reconnect Google in Settings.');

            return;
        }

        GoogleContactsImportBulkState::startRunning();

        $errorsTail = [];

        $result = $sync->importAllFromGoogle(function (
            int $processed,
            int $total,
            int $imported,
            int $skippedDuplicate,
            int $skippedEmpty,
            int $failed,
            ?string $lastError
        ) use (&$errorsTail) {
            if ($lastError !== null) {
                $errorsTail[] = $lastError;
                $errorsTail = array_slice($errorsTail, -40);
            }
            GoogleContactsImportBulkState::tick(
                $processed,
                $total,
                $imported,
                $skippedDuplicate,
                $skippedEmpty,
                $failed,
                $errorsTail
            );
        });

        if (($result['errors'][0] ?? '') !== '' && $result['imported'] === 0 && $result['failed'] === 0 && $result['skipped_duplicate'] === 0 && $result['skipped_empty'] === 0) {
            GoogleContactsImportBulkState::markFailed($result['errors'][0]);

            return;
        }

        GoogleContactsImportBulkState::markDone($result);

        Log::info('Google Contacts import finished', [
            'imported' => $result['imported'],
            'skipped_duplicate' => $result['skipped_duplicate'],
            'skipped_empty' => $result['skipped_empty'],
            'failed' => $result['failed'],
            'total' => $result['total'],
        ]);
    }
}
