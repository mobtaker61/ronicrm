<?php

namespace App\Jobs;

use App\Models\GoogleContactsIntegration;
use App\Services\GoogleContactsOAuthService;
use App\Services\GoogleContactsSyncService;
use App\Support\GoogleContactsPhotoBulkState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGoogleContactPhotosBulkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 7200;

    public function __construct() {}

    public function uniqueId(): string
    {
        return 'google-contacts-photo-bulk';
    }

    public function handle(GoogleContactsSyncService $sync, GoogleContactsOAuthService $oauth): void
    {
        $lock = Cache::lock('google-contacts-photo-bulk', 7200);
        if (! $lock->get()) {
            GoogleContactsPhotoBulkState::markFailed('Another photo sync from Google is already running.');

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
            GoogleContactsPhotoBulkState::markFailed('Google Contacts is not connected.');

            return;
        }

        if (! $oauth->getValidAccessToken()) {
            GoogleContactsPhotoBulkState::markFailed('Token refresh failed. Reconnect Google in Settings.');

            return;
        }

        $total = (int) \App\Models\Customer::query()
            ->whereNotNull('google_people_resource_name')
            ->where('google_people_resource_name', '!=', '')
            ->count();

        GoogleContactsPhotoBulkState::startRunning($total);

        $errorsTail = [];

        $result = $sync->syncAllLinkedCustomerPhotosFromGoogle(function (
            int $processed,
            int $totalCount,
            int $updated,
            int $skipped,
            int $failed,
            ?string $lastError
        ) use (&$errorsTail) {
            if ($lastError !== null) {
                $errorsTail[] = $lastError;
                $errorsTail = array_slice($errorsTail, -40);
            }
            GoogleContactsPhotoBulkState::tick($processed, $totalCount, $updated, $skipped, $failed, $errorsTail);
        });

        if (! empty($result['cancelled'])) {
            GoogleContactsPhotoBulkState::markCancelled(
                $result['processed'],
                $result['total'],
                $result['updated'],
                $result['skipped'],
                $result['failed'],
                array_slice($result['errors'], -40)
            );

            return;
        }

        if (($result['errors'][0] ?? '') !== '' && $result['updated'] === 0 && $result['failed'] === 0 && $result['skipped'] === 0) {
            GoogleContactsPhotoBulkState::markFailed($result['errors'][0]);

            return;
        }

        GoogleContactsPhotoBulkState::markDone($result);

        Log::info('Google Contacts photo bulk finished', [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
            'total' => $result['total'],
        ]);
    }
}
