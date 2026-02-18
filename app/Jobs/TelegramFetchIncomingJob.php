<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use App\Services\CustomerMatchService;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramFetchIncomingJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            Log::warning('TelegramFetchIncomingJob: No active connection, skipping');
            return;
        }

        $service = new MadelineProtoService($conn);
        try {
            $service->start();
            Log::info('TelegramFetchIncomingJob: MadelineProto started');
        } catch (\Throwable $e) {
            Log::warning('TelegramFetchIncomingJob: start failed - ' . $e->getMessage());
            return;
        }

        $dialogs = $service->getDialogs();
        $userPeerIds = [];
        foreach ($dialogs as $d) {
            $type = $d['type'] ?? '';
            $id = $d['id'] ?? '';
            if (in_array($type, ['user'], true) && $id !== '' && !str_starts_with((string) $id, '-')) {
                $userPeerIds[] = (string) $id;
            }
        }

        // Prioritize users we've contacted (more likely to have replies)
        $contactedIds = CustomerContact::where('type', 'telegram')->pluck('value')->flip()->toArray();
        usort($userPeerIds, function ($a, $b) use ($contactedIds) {
            $aHas = isset($contactedIds[$a]) ? 1 : 0;
            $bHas = isset($contactedIds[$b]) ? 1 : 0;
            return $bHas - $aHas;
        });

        Log::info('TelegramFetchIncomingJob', ['total_dialogs' => count($dialogs), 'user_peers' => count($userPeerIds)]);

        if (empty($userPeerIds)) {
            Log::info('TelegramFetchIncomingJob: No user dialogs found (only groups/channels?)');
            return;
        }

        // Limit & rotate: process max 10 users per run to avoid Telegram flood limits
        $maxPerRun = 10;
        $offsetKey = 'telegram_fetch_offset_' . ($conn->id ?? 0);
        $offset = (int) Cache::get($offsetKey, 0);
        $slice = array_slice($userPeerIds, $offset, $maxPerRun);
        $nextOffset = ($offset + count($slice)) % max(1, count($userPeerIds));
        Cache::put($offsetKey, $nextOffset, now()->addDays(1));

        $fetched = 0;
        foreach ($slice as $userId) {
            try {
                $result = $this->fetchAndSaveForPeer($service, $userId);
                $fetched += $result['saved'];
                if (!empty($result['user_data']) && \is_array($result['user_data'])) {
                    $this->updateCustomerFromUserData($userId, $result['user_data']);
                }
                // 5–7 sec delay to avoid Telegram flood (messages.getHistory is rate-limited)
                sleep(rand(5, 7));
            } catch (\Throwable $e) {
                Log::warning("TelegramFetchIncomingJob: fetch for $userId failed - " . $e->getMessage());
                // On flood or salt errors, wait longer before next attempt
                if (str_contains($e->getMessage(), 'Flood') || str_contains($e->getMessage(), 'salt')) {
                    sleep(15);
                }
            }
        }

        if ($fetched > 0) {
            Log::info("TelegramFetchIncomingJob: fetched $fetched new incoming messages");
        }
    }

    protected function fetchAndSaveForPeer(MadelineProtoService $service, string $userId): array
    {
        $lastMsg = TelegramMessage::forChat($userId)
            ->whereNotNull('telegram_message_id')
            ->where('telegram_message_id', '!=', '')
            ->orderByRaw('CAST(telegram_message_id AS UNSIGNED) DESC')
            ->first();
        $minId = $lastMsg && $lastMsg->telegram_message_id ? (int) $lastMsg->telegram_message_id : null;

        $result = $service->getPrivateChatHistory($userId, 50, $minId);
        $messages = $result['messages'] ?? [];
        $users = $result['users'] ?? [];
        $usersById = [];
        foreach ($users as $u) {
            $uid = $u['id'] ?? null;
            if ($uid) {
                $usersById[(string) $uid] = $u;
            }
        }
        $userData = $usersById[$userId] ?? null;

        $saved = 0;
        foreach ($messages as $msg) {
            $msgId = $msg['id'] ?? null;
            if (!$msgId) continue;

            $out = $msg['out'] ?? true;
            if ($out) continue;

            if (TelegramMessage::where('chat_id', $userId)->where('telegram_message_id', $msgId)->exists()) {
                continue;
            }

            $fromId = $this->extractFromMessage($msg);
            $text = $msg['message'] ?? '';
            $customer = $this->findOrCreateCustomer($userId, $userData);

            TelegramMessage::create([
                'telegram_message_id' => (string) $msgId,
                'chat_id' => $userId,
                'from_username' => $usersById[$fromId]['username'] ?? $usersById[$userId]['username'] ?? null,
                'message' => $text,
                'message_type' => 'text',
                'media_url' => null,
                'media_mime_type' => null,
                'customer_id' => $customer?->id,
                'direction' => 'incoming',
                'status' => 'received',
                'metadata' => null,
            ]);
            $saved++;
        }

        return ['saved' => $saved, 'user_data' => $userData];
    }

    protected function extractFromMessage(array $msg): ?string
    {
        $fromId = $msg['from_id'] ?? null;
        if (is_numeric($fromId)) return (string) $fromId;
        if (is_array($fromId) && isset($fromId['user_id'])) {
            return (string) $fromId['user_id'];
        }
        return null;
    }

    protected function findOrCreateCustomer(string $chatId, ?array $userData): ?Customer
    {
        $username = $userData['username'] ?? null;
        $phone = $userData['phone'] ?? null;
        if ($username && !str_starts_with((string) $username, '@')) {
            $username = '@' . $username;
        }

        $existing = CustomerMatchService::findExistingByTelegram($chatId, $username, $phone);
        if ($existing) {
            $this->ensureTelegramContact($existing, $chatId);
            return $existing;
        }

        $name = $this->buildNameFromUser($userData) ?: ('Telegram ' . substr($chatId, -4));
        $customer = Customer::create([
            'name' => $name,
            'type' => 'person',
            'status' => 'lead',
            'source' => 'telegram',
            'created_by' => null,
        ]);
        $this->ensureTelegramContact($customer, $chatId);

        if ($userData && !empty($userData['phone'])) {
            $customer->update(['phone' => $userData['phone']]);
            if (!CustomerContact::where('customer_id', $customer->id)->where('type', 'phone')->exists()) {
                $customer->contacts()->create([
                    'type' => 'phone',
                    'value' => $userData['phone'],
                    'is_primary' => false,
                ]);
            }
        }

        return $customer;
    }

    protected function ensureTelegramContact(Customer $customer, string $chatId): void
    {
        $contact = CustomerContact::where('customer_id', $customer->id)->where('type', 'telegram')->first();
        if ($contact) {
            if ($contact->value !== $chatId) {
                $contact->update(['value' => $chatId]);
            }
        } else {
            $customer->contacts()->create([
                'type' => 'telegram',
                'value' => $chatId,
                'is_primary' => true,
            ]);
        }
    }

    protected function buildNameFromUser(?array $user): string
    {
        if (!$user || !\is_array($user)) return '';
        $first = trim($user['first_name'] ?? '');
        $last = trim($user['last_name'] ?? '');
        return trim("$first $last") ?: '';
    }

    protected function updateCustomerFromUserData(string $userId, array $userData): void
    {
        $contact = CustomerContact::where('type', 'telegram')->where('value', $userId)->first();
        if (!$contact || !$contact->customer) return;

        $customer = $contact->customer;
        $updates = [];

        $name = $this->buildNameFromUser($userData);
        if ($name && ($customer->name === null || str_starts_with($customer->name ?? '', 'Telegram '))) {
            $updates['name'] = $name;
        }

        $phone = $userData['phone'] ?? null;
        if (!empty($phone) && empty($customer->phone)) {
            $updates['phone'] = $phone;
            if (!CustomerContact::where('customer_id', $customer->id)->where('type', 'phone')->exists()) {
                $customer->contacts()->create([
                    'type' => 'phone',
                    'value' => $phone,
                    'is_primary' => false,
                ]);
            }
        }

        if (!empty($updates)) {
            $customer->update($updates);
        }
    }
}
