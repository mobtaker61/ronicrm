<?php

namespace App\Jobs;

use App\Models\GoogleContactsIntegration;
use App\Services\GoogleContactsOAuthService;
use App\Services\GoogleContactsSyncService;
use App\Support\GoogleContactsBulkSyncState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncGoogleContactsBulkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 7200;

    public function __construct() {}

    public function uniqueId(): string
    {
        return 'google-contacts-bulk-sync';
    }

    public function handle(GoogleContactsSyncService $sync, GoogleContactsOAuthService $oauth): void
    {
        if (! GoogleContactsIntegration::getSingleton()) {
            GoogleContactsBulkSyncState::markFailed('Google Contacts is not connected.');

            return;
        }

        if (! $oauth->getValidAccessToken()) {
            GoogleContactsBulkSyncState::markFailed('Token refresh failed. Reconnect Google in Settings.');

            return;
        }

        $total = (int) \App\Models\Customer::query()->count();
        GoogleContactsBulkSyncState::startRunning($total);

        $errorsTail = [];

        $result = $sync->syncAllCustomers(function (int $processed, int $total, int $success, int $failed, ?string $lastError) use (&$errorsTail) {
            if ($lastError !== null) {
                $errorsTail[] = $lastError;
                $errorsTail = array_slice($errorsTail, -40);
            }
            GoogleContactsBulkSyncState::tick($processed, $total, $success, $failed, $errorsTail);
        });

        if (($result['errors'][0] ?? '') !== '' && $result['success'] === 0 && $result['failed'] === 0) {
            GoogleContactsBulkSyncState::markFailed($result['errors'][0]);

            return;
        }

        GoogleContactsBulkSyncState::markDone($result);

        Log::info('Google Contacts bulk sync finished', [
            'success' => $result['success'],
            'failed' => $result['failed'],
            'total' => $result['total'],
        ]);
    }
}
