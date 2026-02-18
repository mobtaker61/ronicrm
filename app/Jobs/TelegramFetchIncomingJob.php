<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelegramFetchIncomingJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            Log::info('TelegramFetchIncomingJob: No active connection, skipping');
            return;
        }

        $service = new MadelineProtoService($conn);
        try {
            $service->start();
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

        if (empty($userPeerIds)) {
            Log::info('TelegramFetchIncomingJob: No user dialogs found');
            return;
        }

        $fetched = 0;
        foreach ($userPeerIds as $userId) {
            try {
                $result = $this->fetchAndSaveForPeer($service, $userId);
                $fetched += $result['saved'];
                if (!empty($result['user_data']) && \is_array($result['user_data'])) {
                    $this->updateCustomerFromUserData($userId, $result['user_data']);
                }
                usleep(300000);
            } catch (\Throwable $e) {
                Log::warning("TelegramFetchIncomingJob: fetch for $userId failed - " . $e->getMessage());
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
        $contact = CustomerContact::where('type', 'telegram')->where('value', $chatId)->first();
        if ($contact && $contact->customer) {
            return $contact->customer;
        }

        $name = $this->buildNameFromUser($userData) ?: ('Telegram ' . substr($chatId, -4));
        $customer = Customer::create([
            'name' => $name,
            'type' => 'person',
            'status' => 'lead',
            'source' => 'telegram',
            'created_by' => null,
        ]);
        $customer->contacts()->create([
            'type' => 'telegram',
            'value' => $chatId,
            'is_primary' => true,
        ]);

        if ($userData && !empty($userData['phone'])) {
            $customer->update(['phone' => $userData['phone']]);
            $customer->contacts()->create([
                'type' => 'phone',
                'value' => $userData['phone'],
                'is_primary' => false,
            ]);
        }

        return $customer;
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
