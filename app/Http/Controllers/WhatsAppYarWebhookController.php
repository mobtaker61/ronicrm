<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\TelegramGroup;
use App\Models\WhatsAppMessage;
use App\Support\OrganizationContext;
use App\Support\WhatsAppSettings;
use App\Services\WhatsAppYarApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppYarWebhookController extends Controller
{
    public function handle(Request $request, ?string $organization = null)
    {
        try {
            $payload = $request->all();
            Log::info('WhatsAppYar webhook received', [
                'organization_slug' => $organization,
                'event' => $payload['event'] ?? $payload['type'] ?? null,
            ]);

            if (! $this->verifyWebhookSignature($request)) {
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            $event = (string) ($payload['event'] ?? $payload['type'] ?? '');
            $sessionId = (string) ($payload['sessionId'] ?? $payload['session_id'] ?? $payload['data']['sessionId'] ?? '');

            $organizationId = $this->resolveOrganizationId($request, $organization, $sessionId);
            if ($organizationId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization could not be resolved from webhook',
                ], 422);
            }

            OrganizationContext::setOrganizationId($organizationId);

            if (in_array($event, ['group.join', 'group.update', 'group.leave'], true)) {
                return $this->handleGroupEvent($payload, $organizationId);
            }

            if (in_array($event, ['session.status', 'session.authenticated', 'session.disconnected'], true)) {
                return $this->handleSessionEvent($payload, $organizationId);
            }

            if ($event === 'message.received' || $event === '' || isset($payload['data']['body']) || isset($payload['body'])) {
                return $this->handleIncomingMessage($payload, $organizationId, $sessionId);
            }

            return response()->json(['success' => true, 'message' => 'Event ignored']);
        } catch (\Throwable $e) {
            Log::error('WhatsAppYar webhook error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    protected function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('X-Hub-Signature-256');

        if (! is_string($signature) || $signature === '') {
            return true;
        }

        $sessionId = (string) ($request->input('sessionId') ?? $request->input('session_id') ?? '');
        $secret = $this->findWebhookSecret($sessionId);
        if ($secret === '') {
            return true;
        }

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals($expected, $provided);
    }

    protected function findWebhookSecret(string $sessionId): string
    {
        if ($sessionId === '') {
            return '';
        }

        $rows = OrganizationSetting::query()
            ->withoutGlobalScopes()
            ->where('key', 'whatsapp')
            ->get();

        foreach ($rows as $row) {
            $val = $row->value;
            if (! is_array($val)) {
                continue;
            }
            if ((string) ($val['session_id'] ?? '') === $sessionId) {
                return (string) ($val['webhook_secret'] ?? '');
            }
        }

        return '';
    }

    protected function resolveOrganizationId(Request $request, ?string $organizationSlug, string $sessionId): ?int
    {
        $override = $request->input('organization_id');
        if ($override !== null && $override !== '') {
            $id = (int) $override;
            if ($id > 0 && Organization::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        if (is_string($organizationSlug) && $organizationSlug !== '') {
            $id = Organization::query()
                ->where('slug', $organizationSlug)
                ->where('is_active', true)
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($sessionId !== '') {
            $row = OrganizationSetting::query()
                ->withoutGlobalScopes()
                ->where('key', 'whatsapp')
                ->get()
                ->first(function ($row) use ($sessionId) {
                    $val = $row->value;

                    return is_array($val) && (string) ($val['session_id'] ?? '') === $sessionId;
                });
            if ($row) {
                return (int) $row->organization_id;
            }
        }

        return OrganizationContext::getOrganizationId();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSessionEvent(array $payload, int $organizationId): \Illuminate\Http\JsonResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $status = strtolower((string) ($data['status'] ?? $payload['status'] ?? ''));
        $phone = $data['phone'] ?? null;
        $phoneDigits = is_string($phone) ? preg_replace('/\D+/', '', $phone) : (is_numeric($phone) ? (string) $phone : null);

        $update = ['status' => $status];
        if ($phoneDigits) {
            $update['line_phone'] = $phoneDigits;
        }
        if (in_array($status, ['ready', 'authenticated', 'connected'], true)) {
            $update['enabled'] = true;
        }
        if (in_array($status, ['disconnected', 'failed'], true)) {
            $update['enabled'] = false;
        }

        WhatsAppSettings::set($update, $organizationId);

        return response()->json(['success' => true, 'message' => 'Session status updated']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleGroupEvent(array $payload, int $organizationId): \Illuminate\Http\JsonResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $groupJid = (string) ($data['groupId'] ?? $data['chatId'] ?? $data['id'] ?? '');
        if ($groupJid === '' || ! WhatsAppYarApiService::isGroupChatId($groupJid)) {
            return response()->json(['success' => true, 'message' => 'No group id in payload']);
        }

        TelegramGroup::withoutGlobalScope('organization')->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'channel' => 'whatsapp',
                'telegram_group_id' => $groupJid,
            ],
            [
                'telegram_user_connection_id' => null,
                'title' => $data['subject'] ?? $data['name'] ?? $data['title'] ?? null,
                'type' => 'group',
                'is_active' => ($payload['event'] ?? '') !== 'group.leave',
                'last_synced_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Group metadata stored']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleIncomingMessage(array $payload, int $organizationId, string $sessionId): \Illuminate\Http\JsonResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $chatId = (string) ($data['chatId'] ?? $data['from'] ?? $data['sender'] ?? '');
        if ($chatId !== '' && WhatsAppYarApiService::isGroupChatId($chatId)) {
            return $this->handleIncomingGroupMessage($data, $payload, $organizationId, $chatId);
        }

        if ($chatId !== '' && ! WhatsAppYarApiService::isPrivateDirectChatId($chatId)) {
            return response()->json(['success' => true, 'message' => 'Non-direct chat ignored for inbox']);
        }

        $fromPhone = $this->extractSenderPhone($data);
        if ($fromPhone === '') {
            return response()->json(['success' => false, 'message' => 'Missing sender phone'], 400);
        }

        $settings = WhatsAppSettings::get($organizationId);
        if (! WhatsAppYarApiService::isValidInboxPeerPhone($fromPhone, $settings['line_phone'] ?? null)) {
            return response()->json(['success' => true, 'message' => 'Sender not eligible for inbox']);
        }

        [$messageText, $messageType, $mediaMimeType, $mediaUrl] = $this->extractMessageContent($data);
        $storedFile = $this->downloadMediaIfNeeded($mediaUrl, $mediaMimeType);

        $customer = $this->findCustomerByPhone($fromPhone, $organizationId);
        $toPhone = $this->formatPhone((string) ($settings['line_phone'] ?? ''));

        $messageId = (string) ($data['id'] ?? $data['messageId'] ?? $data['message_id'] ?? null);
        $resolvedChatId = $chatId !== ''
            ? $chatId
            : WhatsAppYarApiService::phoneToChatId($fromPhone);

        WhatsAppMessage::updateOrCreate(
            ['message_id' => $messageId !== '' ? $messageId : null],
            [
                'chat_id' => $resolvedChatId,
                'from_phone' => $fromPhone,
                'to_phone' => $toPhone,
                'message' => $messageText ?? '',
                'message_type' => $messageType,
                'media_url' => $storedFile ?? $mediaUrl,
                'media_mime_type' => $mediaMimeType,
                'customer_id' => $customer?->id,
                'direction' => 'incoming',
                'status' => 'received',
                'metadata' => $payload,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Webhook processed successfully']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    protected function handleIncomingGroupMessage(array $data, array $payload, int $organizationId, string $groupChatId): \Illuminate\Http\JsonResponse
    {
        TelegramGroup::withoutGlobalScope('organization')->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'channel' => 'whatsapp',
                'telegram_group_id' => $groupChatId,
            ],
            [
                'telegram_user_connection_id' => null,
                'title' => $data['groupName'] ?? $data['subject'] ?? $data['name'] ?? $groupChatId,
                'type' => 'group',
                'is_active' => true,
                'last_synced_at' => now(),
            ]
        );

        $group = TelegramGroup::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('channel', 'whatsapp')
            ->where('telegram_group_id', $groupChatId)
            ->first();

        if (! $group || ! $group->at_inbox) {
            return response()->json(['success' => true, 'message' => 'Group message stored without inbox']);
        }

        $fromPhone = $this->extractSenderPhone($data);
        [$messageText, $messageType, $mediaMimeType, $mediaUrl] = $this->extractMessageContent($data);
        $storedFile = $this->downloadMediaIfNeeded($mediaUrl, $mediaMimeType);

        $settings = WhatsAppSettings::get($organizationId);
        $linePhone = $this->formatPhone((string) ($settings['line_phone'] ?? ''));
        $fromMe = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $messageId = (string) ($data['id'] ?? $data['messageId'] ?? $data['message_id'] ?? '');

        WhatsAppMessage::updateOrCreate(
            ['message_id' => $messageId !== '' ? $messageId : null],
            [
                'chat_id' => $groupChatId,
                'from_phone' => $fromMe ? $linePhone : $fromPhone,
                'to_phone' => $fromMe ? $groupChatId : $linePhone,
                'message' => $messageText ?? '',
                'message_type' => $messageType,
                'media_url' => $storedFile ?? $mediaUrl,
                'media_mime_type' => $mediaMimeType,
                'customer_id' => $fromPhone !== '' ? $this->findCustomerByPhone($fromPhone, $organizationId)?->id : null,
                'direction' => $fromMe ? 'outgoing' : 'incoming',
                'status' => $fromMe ? 'sent' : 'received',
                'metadata' => array_merge($payload, ['is_group' => true]),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Group message processed for inbox']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractSenderPhone(array $data): string
    {
        $candidates = [
            $data['from'] ?? null,
            $data['sender'] ?? null,
            $data['chatId'] ?? null,
            $data['author'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            if (WhatsAppYarApiService::isGroupChatId($candidate)) {
                continue;
            }
            $phone = WhatsAppYarApiService::chatIdToPhone($candidate);
            if (strlen($phone) >= 8) {
                return $this->formatPhone($phone);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: string, 2: ?string, 3: ?string}
     */
    protected function extractMessageContent(array $data): array
    {
        $typeRaw = strtolower((string) ($data['type'] ?? $data['messageType'] ?? 'text'));
        $body = $data['body'] ?? $data['text'] ?? $data['caption'] ?? null;
        $body = is_string($body) ? $body : null;

        $mediaUrl = $data['mediaUrl'] ?? $data['media_url'] ?? $data['url'] ?? null;
        $mediaUrl = is_string($mediaUrl) ? $mediaUrl : null;
        $mime = $data['mimetype'] ?? $data['mimeType'] ?? $data['media_mime_type'] ?? null;
        $mime = is_string($mime) ? $mime : null;

        $messageType = match (true) {
            str_contains($typeRaw, 'image') => 'image',
            str_contains($typeRaw, 'video') => 'video',
            str_contains($typeRaw, 'audio'), str_contains($typeRaw, 'ptt'), str_contains($typeRaw, 'voice') => 'audio',
            str_contains($typeRaw, 'document') => 'document',
            str_contains($typeRaw, 'sticker') => 'sticker',
            str_contains($typeRaw, 'location') => 'location',
            str_contains($typeRaw, 'contact'), str_contains($typeRaw, 'vcard') => 'contact',
            default => $mediaUrl ? 'document' : 'text',
        };

        return [$body, $messageType, $mime, $mediaUrl];
    }

    protected function downloadMediaIfNeeded(?string $mediaUrl, ?string $mediaMimeType): ?string
    {
        if ($mediaUrl === null || $mediaUrl === '') {
            return null;
        }

        try {
            $fileContent = file_get_contents($mediaUrl);
            if ($fileContent === false) {
                return null;
            }

            $ext = pathinfo(parse_url($mediaUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'bin';
            $fileName = 'wa_'.time().'_'.uniqid().'.'.$ext;
            $path = public_path('uploads/whatsapp/'.$fileName);

            if (! file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $fileContent);

            return 'uploads/whatsapp/'.$fileName;
        } catch (\Throwable $e) {
            Log::error('WhatsAppYar media download failed: '.$e->getMessage());

            return null;
        }
    }

    protected function findCustomerByPhone(string $phone, int $organizationId): ?Customer
    {
        $phone = $this->formatPhone($phone);

        $contact = CustomerContact::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
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

    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }
}
