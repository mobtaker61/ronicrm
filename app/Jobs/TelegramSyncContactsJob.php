<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sync customer data from Telegram for contacts extracted via crawl/inbox.
 * Fetches full user info (name, username, phone, avatar) and updates customer.
 */
class TelegramSyncContactsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public ?string $syncId = null
    ) {
        $this->syncId = $syncId ?? 'sync_' . uniqid();
    }

    public function handle(): void
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            $this->setProgress('error', 0, 0, 'No active Telegram connection.');
            Log::warning('TelegramSyncContactsJob: No active connection');
            return;
        }

        $telegramContacts = CustomerContact::where('type', 'telegram')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->whereNotNull('customer_id')
            ->get();

        $customerIds = $telegramContacts
            ->filter(fn ($c) => ctype_digit((string) $c->value))
            ->pluck('customer_id')
            ->unique()
            ->values();
        $total = $customerIds->count();

        if ($total === 0) {
            $this->setProgress('completed', 0, 0, 'No Telegram contacts to sync.');
            return;
        }

        $service = new MadelineProtoService($conn);
        try {
            $service->start();
        } catch (\Throwable $e) {
            $this->setProgress('error', 0, 0, 'Failed to start: ' . $e->getMessage());
            Log::warning('TelegramSyncContactsJob: start failed - ' . $e->getMessage());
            return;
        }

        $updated = 0;
        $failed = 0;
        $processed = 0;

        foreach ($customerIds as $idx => $customerId) {
            $processed++;
            $this->setProgress('running', $processed, $updated, null, $total);

            $contact = CustomerContact::where('customer_id', $customerId)->where('type', 'telegram')->first();
            if (!$contact) {
                continue;
            }
            $userId = $contact->value;
            if (!ctype_digit((string) $userId)) {
                continue;
            }

            try {
                $info = $service->getFullTelegramUserInfo($userId);
                if (empty($info)) {
                    continue;
                }

                $customer = Customer::find($customerId);
                if (!$customer) {
                    continue;
                }

                $updates = [];
                $name = trim(($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''));
                if ($name !== '' && (empty($customer->name) || str_starts_with($customer->name ?? '', 'Telegram '))) {
                    $updates['name'] = $name;
                }

                $phone = $info['phone'] ?? null;
                if (!empty($phone) && empty($customer->phone)) {
                    $updates['phone'] = $phone;
                    if (!CustomerContact::where('customer_id', $customerId)->where('type', 'phone')->exists()) {
                        CustomerContact::create([
                            'customer_id' => $customerId,
                            'type' => 'phone',
                            'value' => $phone,
                            'is_primary' => false,
                        ]);
                    }
                }

                $username = $info['username'] ?? null;
                if (!empty($username) && $username !== ($contact->value ?? '')) {
                    $handle = str_starts_with($username, '@') ? $username : '@' . $username;
                    $existingHandle = $customer->socialMedia()
                        ->whereHas('socialMediaType', fn ($q) => $q->where('name', 'Telegram'))
                        ->first();
                    if (!$existingHandle) {
                        $tgTypeId = \App\Models\SocialMediaType::where('name', 'Telegram')->value('id');
                        if ($tgTypeId) {
                            $customer->socialMedia()->create([
                                'social_media_type_id' => $tgTypeId,
                                'handle' => $handle,
                                'url' => 'https://t.me/' . ltrim($handle, '@'),
                            ]);
                        }
                    }
                }

                if (!empty($info['avatar_path'])) {
                    $relPath = $info['avatar_path'];
                    $fullPath = storage_path('app/public/' . $relPath);
                    if (file_exists($fullPath)) {
                        $targetDir = 'customers';
                        $targetName = 'avatar_' . $customerId . '_' . time() . '.jpg';
                        $targetPath = $targetDir . '/' . $targetName;
                        Storage::disk('public')->put($targetPath, file_get_contents($fullPath));
                        @unlink($fullPath);
                        $updates['avatar'] = $targetPath;
                    }
                }

                if (!empty($updates)) {
                    $customer->update($updates);
                    $updated++;
                }

                sleep(rand(3, 6));
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("TelegramSyncContactsJob: sync for user $userId failed - " . $e->getMessage());
                if (str_contains($e->getMessage(), 'FLOOD') || str_contains($e->getMessage(), 'Flood')) {
                    sleep(30);
                }
            }
        }

        $this->setProgress('completed', $total, $updated, null, $total, $failed);
    }

    protected function setProgress(
        string $status,
        int $processed,
        int $updated,
        ?string $error = null,
        ?int $total = null,
        ?int $failed = null
    ): void {
        $key = 'telegram_sync_' . $this->syncId;
        $data = [
            'status' => $status,
            'processed' => $processed,
            'updated' => $updated,
            'error' => $error,
            'total' => $total,
            'failed' => $failed ?? 0,
        ];
        Cache::put($key, $data, now()->addHours(24));
    }
}
