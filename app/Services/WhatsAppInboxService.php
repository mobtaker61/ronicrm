<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramGroup;
use App\Models\WhatsAppMessage;
use App\Support\OrganizationContext;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppInboxService
{
    public function syncConversationsIfDue(?int $organizationId = null): void
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId || ! WhatsAppSettings::isReady($organizationId)) {
            return;
        }

        $cacheKey = 'whatsapp_inbox_chats_sync_'.$organizationId;
        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addSeconds(30));

        $this->syncChatsFromApi((int) $organizationId);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildConversationList(?int $organizationId = null): Collection
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId) {
            return collect();
        }

        try {
            $this->syncConversationsIfDue((int) $organizationId);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp inbox sync skipped', ['error' => $e->getMessage()]);
        }

        $fromApi = $this->mapApiChatsToConversations((int) $organizationId);
        $fromDb = $this->buildFromDatabase((int) $organizationId);
        $direct = $this->mergeConversations($fromApi, $fromDb);
        $groups = $this->buildWhatsAppGroupConversations((int) $organizationId);

        return $direct->concat($groups)->sortByDesc(fn ($c) => $c['last_message_at'] ?? null)->values();
    }

    public function syncChatHistoryForChatId(string $chatId, ?int $organizationId = null, int $limit = 80): void
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId || ! WhatsAppSettings::isReady($organizationId)) {
            return;
        }

        $chatId = trim($chatId);
        if ($chatId === '') {
            return;
        }

        $settings = WhatsAppSettings::get($organizationId);
        $sessionId = (string) ($settings['session_id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        $api = app(WhatsAppYarApiService::class)->forOrganization((int) $organizationId);
        $history = $api->getChatHistory($sessionId, $chatId, $limit);
        $messages = $this->unwrapList($history, ['messages', 'data', 'items']);

        foreach ($messages as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (WhatsAppYarApiService::isGroupChatId($chatId)) {
                $this->upsertGroupMessageFromApiRow($row, (int) $organizationId, $chatId, $settings);
            } else {
                $peerPhone = WhatsAppYarApiService::chatIdToPhone($chatId);
                $this->upsertMessageFromApiRow($row, (int) $organizationId, $chatId, $peerPhone, $settings);
            }
        }
    }

    public function syncChatHistoryForPeer(string $peerPhone, ?int $organizationId = null, int $limit = 80): void
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId || ! WhatsAppSettings::isReady($organizationId)) {
            return;
        }

        $peerPhone = WhatsAppYarApiService::normalizePhoneDigits($peerPhone);
        if (! WhatsAppYarApiService::isValidInboxPeerPhone($peerPhone, WhatsAppSettings::get($organizationId)['line_phone'] ?? null)) {
            return;
        }

        $chatId = WhatsAppYarApiService::phoneToChatId($peerPhone);
        $this->syncChatHistoryForChatId($chatId, $organizationId, $limit);
    }

    protected function syncChatsFromApi(int $organizationId): void
    {
        $settings = WhatsAppSettings::get($organizationId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '') {
            return;
        }

        $api = app(WhatsAppYarApiService::class)->forOrganization($organizationId);
        $payload = $api->listChats($sessionId);
        $chats = $this->unwrapList($payload, ['chats', 'data', 'items']);

        foreach ($chats as $chat) {
            if (! is_array($chat)) {
                continue;
            }

            $chatId = (string) ($chat['id'] ?? $chat['chatId'] ?? '');
            if ($chatId === '' || ! WhatsAppYarApiService::isPrivateDirectChatId($chatId)) {
                continue;
            }

            $peerPhone = WhatsAppYarApiService::chatIdToPhone($chatId);
            if (! WhatsAppYarApiService::isValidInboxPeerPhone($peerPhone, $settings['line_phone'] ?? null)) {
                continue;
            }

            $last = is_array($chat['lastMessage'] ?? null) ? $chat['lastMessage'] : $chat;
            if (is_array($last) && ($last['body'] ?? $last['text'] ?? null) !== null) {
                $this->upsertMessageFromApiRow($last, $organizationId, $chatId, $peerPhone, $settings);
            }
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function mapApiChatsToConversations(int $organizationId): Collection
    {
        if (! WhatsAppSettings::isReady($organizationId)) {
            return collect();
        }

        $settings = WhatsAppSettings::get($organizationId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '') {
            return collect();
        }

        try {
            $api = app(WhatsAppYarApiService::class)->forOrganization($organizationId);
            $payload = $api->listChats($sessionId);
            $chats = $this->unwrapList($payload, ['chats', 'data', 'items']);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp listChats failed', ['error' => $e->getMessage()]);

            return collect();
        }

        $conversations = collect();
        foreach ($chats as $chat) {
            if (! is_array($chat)) {
                continue;
            }

            $chatId = (string) ($chat['id'] ?? $chat['chatId'] ?? '');
            if ($chatId === '' || ! WhatsAppYarApiService::isPrivateDirectChatId($chatId)) {
                continue;
            }

            $peerPhone = WhatsAppYarApiService::chatIdToPhone($chatId);
            if (! WhatsAppYarApiService::isValidInboxPeerPhone($peerPhone, $settings['line_phone'] ?? null)) {
                continue;
            }

            $customer = $this->findCustomerByPhone($peerPhone, $organizationId);
            $lastMessage = is_array($chat['lastMessage'] ?? null) ? $chat['lastMessage'] : null;
            $lastBody = is_array($lastMessage)
                ? (string) ($lastMessage['body'] ?? $lastMessage['text'] ?? $lastMessage['caption'] ?? '')
                : (string) ($chat['preview'] ?? $chat['lastMessageText'] ?? '');

            $lastAt = $chat['timestamp'] ?? $chat['lastMessageTime'] ?? ($lastMessage['timestamp'] ?? null);
            $lastAtCarbon = is_numeric($lastAt) ? \Carbon\Carbon::createFromTimestamp((int) $lastAt) : null;

            $dbUnread = WhatsAppMessage::query()
                ->where('from_phone', $peerPhone)
                ->where('direction', 'incoming')
                ->whereNull('read_at')
                ->count();

            $displayName = trim((string) ($chat['name'] ?? $chat['pushName'] ?? $chat['contact']['name'] ?? ''));
            if ($displayName === '' && $customer) {
                $displayName = (string) $customer->name;
            }
            if ($displayName === '' || $displayName === $peerPhone) {
                $displayName = $peerPhone;
            }

            $conversations->push([
                'phone' => $peerPhone,
                'chat_id' => $chatId,
                'ig_user_id' => null,
                'tiktok_open_id' => null,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/'.$customer->avatar) : null,
                'last_message' => $lastBody !== '' ? $lastBody : null,
                'last_message_at' => $lastAtCarbon,
                'unread_count' => (int) ($chat['unreadCount'] ?? $chat['unread'] ?? $dbUnread),
                'message_count' => WhatsAppMessage::query()->conversationWithPeer($peerPhone)->count(),
            ]);
        }

        return $conversations->sortByDesc(fn ($c) => $c['last_message_at'] ?? null)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildFromDatabase(int $organizationId): Collection
    {
        $linePhone = WhatsAppSettings::get($organizationId)['line_phone'] ?? null;

        $peersFromIncoming = WhatsAppMessage::incoming()
            ->whereNotNull('from_phone')
            ->distinct()
            ->pluck('from_phone');
        $peersFromOutgoing = WhatsAppMessage::query()
            ->where('direction', 'outgoing')
            ->whereNotNull('to_phone')
            ->distinct()
            ->pluck('to_phone');

        $allPhones = $peersFromIncoming->merge($peersFromOutgoing)->unique()->filter();

        $conversations = collect();
        foreach ($allPhones as $phone) {
            if (! WhatsAppYarApiService::isValidInboxPeerPhone((string) $phone, $linePhone)) {
                continue;
            }

            $customer = $this->findCustomerByPhone((string) $phone, $organizationId);
            $lastMessage = WhatsAppMessage::query()
                ->conversationWithPeer((string) $phone)
                ->latest()
                ->first();

            $customerName = $customer?->name ?? '';
            $displayName = ($customer && trim((string) $customerName) !== '' && $customerName !== $phone) ? $customerName : $phone;

            $conversations->push([
                'phone' => $phone,
                'chat_id' => $lastMessage?->chat_id ?: WhatsAppYarApiService::phoneToChatId((string) $phone),
                'ig_user_id' => null,
                'tiktok_open_id' => null,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/'.$customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => WhatsAppMessage::where('from_phone', $phone)->where('direction', 'incoming')->whereNull('read_at')->count(),
                'message_count' => WhatsAppMessage::query()->conversationWithPeer((string) $phone)->count(),
            ]);
        }

        return $conversations->sortByDesc('last_message_at')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildWhatsAppGroupConversations(int $organizationId): Collection
    {
        $groups = TelegramGroup::query()
            ->where('channel', 'whatsapp')
            ->where('at_inbox', true)
            ->active()
            ->orderBy('title')
            ->get();

        return $groups->map(function (TelegramGroup $group) {
            $chatId = (string) $group->telegram_group_id;
            $lastMessage = WhatsAppMessage::forChat($chatId)->latest()->first();

            return [
                'phone' => null,
                'chat_id' => $chatId,
                'is_whatsapp_group' => true,
                'ig_user_id' => null,
                'tiktok_open_id' => null,
                'name' => (string) ($group->title ?: $chatId),
                'customer_id' => null,
                'avatar' => null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => WhatsAppMessage::forChat($chatId)
                    ->where('direction', 'incoming')
                    ->whereNull('read_at')
                    ->count(),
                'message_count' => WhatsAppMessage::forChat($chatId)->count(),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $primary
     * @param  Collection<int, array<string, mixed>>  $secondary
     * @return Collection<int, array<string, mixed>>
     */
    protected function mergeConversations(Collection $primary, Collection $secondary): Collection
    {
        $byKey = [];

        foreach ($secondary as $row) {
            $key = $this->conversationKey($row);
            if ($key !== '') {
                $byKey[$key] = $row;
            }
        }

        foreach ($primary as $row) {
            $key = $this->conversationKey($row);
            if ($key === '') {
                continue;
            }
            $byKey[$key] = array_merge($byKey[$key] ?? [], $row);
        }

        return collect(array_values($byKey))
            ->sortByDesc(fn ($c) => $c['last_message_at'] ?? null)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function conversationKey(array $row): string
    {
        if (! empty($row['is_whatsapp_group']) && ! empty($row['chat_id'])) {
            return 'g:'.(string) $row['chat_id'];
        }

        $phone = (string) ($row['phone'] ?? '');
        if ($phone !== '') {
            return 'p:'.$phone;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $settings
     */
    protected function upsertGroupMessageFromApiRow(array $row, int $organizationId, string $groupChatId, array $settings): void
    {
        $messageId = (string) ($row['id'] ?? $row['messageId'] ?? $row['message_id'] ?? '');
        $fromMe = (bool) ($row['fromMe'] ?? $row['from_me'] ?? false);
        $direction = $fromMe ? 'outgoing' : 'incoming';
        $linePhone = WhatsAppYarApiService::normalizePhoneDigits((string) ($settings['line_phone'] ?? ''));

        $authorRaw = (string) ($row['author'] ?? $row['from'] ?? $row['sender'] ?? '');
        $senderPhone = WhatsAppYarApiService::chatIdToPhone($authorRaw);
        if ($senderPhone === '' && $authorRaw !== '' && ! WhatsAppYarApiService::isGroupChatId($authorRaw)) {
            $senderPhone = WhatsAppYarApiService::normalizePhoneDigits($authorRaw);
        }

        $fromPhone = $direction === 'incoming' ? $senderPhone : $linePhone;
        $toPhone = $direction === 'outgoing' ? $groupChatId : $linePhone;

        $body = $row['body'] ?? $row['text'] ?? $row['caption'] ?? '';
        $body = is_string($body) ? $body : '';

        $timestamp = $row['timestamp'] ?? $row['t'] ?? null;
        $createdAt = is_numeric($timestamp) ? \Carbon\Carbon::createFromTimestamp((int) $timestamp) : now();

        $attrs = [
            'chat_id' => $groupChatId,
            'from_phone' => $fromPhone,
            'to_phone' => $toPhone,
            'message' => $body,
            'message_type' => 'text',
            'direction' => $direction,
            'status' => $direction === 'incoming' ? 'received' : 'sent',
            'customer_id' => $senderPhone !== '' ? $this->findCustomerByPhone($senderPhone, $organizationId)?->id : null,
            'metadata' => ['source' => 'whatsappyar_api', 'is_group' => true, 'raw' => $row],
        ];

        if ($messageId !== '') {
            WhatsAppMessage::updateOrCreate(
                ['message_id' => $messageId],
                array_merge($attrs, ['created_at' => $createdAt, 'updated_at' => now()])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $settings
     */
    protected function upsertMessageFromApiRow(array $row, int $organizationId, string $chatId, string $peerPhone, array $settings): void
    {
        $messageId = (string) ($row['id'] ?? $row['messageId'] ?? $row['message_id'] ?? '');
        $fromMe = (bool) ($row['fromMe'] ?? $row['from_me'] ?? false);
        $direction = $fromMe ? 'outgoing' : 'incoming';
        $linePhone = WhatsAppYarApiService::normalizePhoneDigits((string) ($settings['line_phone'] ?? ''));

        $fromPhone = $direction === 'incoming' ? $peerPhone : ($linePhone ?: $peerPhone);
        $toPhone = $direction === 'outgoing' ? $peerPhone : ($linePhone ?: '');

        $body = $row['body'] ?? $row['text'] ?? $row['caption'] ?? '';
        $body = is_string($body) ? $body : '';

        $timestamp = $row['timestamp'] ?? $row['t'] ?? null;
        $createdAt = is_numeric($timestamp) ? \Carbon\Carbon::createFromTimestamp((int) $timestamp) : now();

        $attrs = [
            'chat_id' => $chatId,
            'from_phone' => $fromPhone,
            'to_phone' => $toPhone,
            'message' => $body,
            'message_type' => 'text',
            'direction' => $direction,
            'status' => $direction === 'incoming' ? 'received' : 'sent',
            'customer_id' => $this->findCustomerByPhone($peerPhone, $organizationId)?->id,
            'metadata' => ['source' => 'whatsappyar_api', 'raw' => $row],
        ];

        if ($messageId !== '') {
            WhatsAppMessage::updateOrCreate(
                ['message_id' => $messageId],
                array_merge($attrs, ['created_at' => $createdAt, 'updated_at' => now()])
            );

            return;
        }
    }

  /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    protected function unwrapList(array $payload, array $keys): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : array_values($payload[$key]);
            }
        }

        return [];
    }

    protected function findCustomerByPhone(string $phone, int $organizationId): ?Customer
    {
        $phone = WhatsAppYarApiService::normalizePhoneDigits($phone);

        $contact = CustomerContact::query()
            ->where(function ($q) {
                $q->where('type', 'phone')->orWhere('type', 'whatsapp');
            })
            ->where(function ($q) use ($phone) {
                $q->where('value', $phone)
                    ->orWhere('value', '+'.$phone)
                    ->orWhere('value', '00'.$phone);
            })
            ->first();

        return $contact?->customer;
    }

    public function markChatReadOnDevice(string $peerOrChatId, ?int $organizationId = null): void
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId || ! WhatsAppSettings::isReady($organizationId)) {
            return;
        }

        $peerOrChatId = trim($peerOrChatId);
        if ($peerOrChatId === '') {
            return;
        }

        if (str_contains($peerOrChatId, '@')) {
            $chatId = $peerOrChatId;
        } else {
            $peerPhone = WhatsAppYarApiService::normalizePhoneDigits($peerOrChatId);
            if (! WhatsAppYarApiService::isValidInboxPeerPhone($peerPhone, WhatsAppSettings::get($organizationId)['line_phone'] ?? null)) {
                return;
            }
            $chatId = WhatsAppYarApiService::phoneToChatId($peerPhone);
        }

        $settings = WhatsAppSettings::get($organizationId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '') {
            return;
        }

        try {
            app(WhatsAppYarApiService::class)
                ->forOrganization((int) $organizationId)
                ->markChatRead($sessionId, $chatId);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp markChatRead failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
