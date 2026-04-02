<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\TelegramGroup;
use App\Models\WhatsAppMessage;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RonibotWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp messages from Ronibot webhook
     */
    public function handle(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('Ronibot webhook received', [
                'data' => $data,
            ]);

            $payload = $data['payload'] ?? null;

            if (! $payload || ! isset($payload['data'][0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payload structure',
                ], 400);
            }

            $msg = $payload['data'][0];

            $remoteJid = $msg['key']['remoteJid'] ?? '';
            if (str_ends_with((string) $remoteJid, '@g.us')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Group chat ignored for inbox',
                ]);
            }

            // Ronibot BulkController sends top-level mediaUrl; raw Baileys forward uses data[0].media.url
            $mediaUrl = $data['mediaUrl'] ?? ($msg['media']['url'] ?? null);

            // =========================
            // PHONES
            // =========================
            $fromPhone = $this->formatPhone(
                $data['sender']
                ?? ($msg['key']['remoteJidAlt'] ?? '')
            );

            $receiverRaw = (string) ($data['receiver'] ?? '');
            $toPhone = $this->formatPhone($receiverRaw);

            $organizationId = $this->resolveOrganizationIdFromWebhook($request, $fromPhone, $receiverRaw);
            if ($organizationId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization could not be resolved from webhook (receiver / customer / history)',
                ], 422);
            }

            OrganizationContext::setOrganizationId($organizationId);

            // =========================
            // MESSAGE
            // =========================
            $messageText = null;
            $messageType = 'text';
            $mediaMimeType = null;
            $storedFile = null;

            $inner = $this->extractBaileysInnerMessage($msg['message'] ?? null);
            if ($inner === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No message body',
                ], 400);
            }

            if (isset($inner['conversation'])) {
                $messageText = $inner['conversation'];
            } elseif (isset($inner['extendedTextMessage']['text'])) {
                $messageText = $inner['extendedTextMessage']['text'];
            } elseif (isset($inner['imageMessage'])) {
                $messageType = 'image';
                $messageText = $inner['imageMessage']['caption'] ?? null;
                $mediaMimeType = $inner['imageMessage']['mimetype'] ?? null;
            } elseif (isset($inner['videoMessage'])) {
                $messageType = 'video';
                $messageText = $inner['videoMessage']['caption'] ?? null;
                $mediaMimeType = $inner['videoMessage']['mimetype'] ?? null;
            } elseif (isset($inner['ptvMessage'])) {
                $messageType = 'video';
                $messageText = $inner['ptvMessage']['caption'] ?? null;
                $mediaMimeType = $inner['ptvMessage']['mimetype'] ?? null;
            } elseif (isset($inner['audioMessage'])) {
                $messageType = 'audio';
                $mediaMimeType = $inner['audioMessage']['mimetype'] ?? null;
            } elseif (isset($inner['documentMessage'])) {
                $messageType = 'document';
                $messageText = $inner['documentMessage']['fileName'] ?? null;
                $mediaMimeType = $inner['documentMessage']['mimetype'] ?? null;
            } elseif (isset($inner['stickerMessage'])) {
                $messageType = 'sticker';
                $mediaMimeType = $inner['stickerMessage']['mimetype'] ?? null;
            } elseif (isset($inner['locationMessage'])) {
                $messageType = 'location';
                $loc = $inner['locationMessage'];
                $lat = $loc['degreesLatitude'] ?? null;
                $lng = $loc['degreesLongitude'] ?? null;
                if ($lat !== null && $lng !== null) {
                    $messageText = 'https://www.google.com/maps?q='.$lat.','.$lng;
                }
            } elseif (isset($inner['contactMessage'])) {
                $messageType = 'contact';
                $messageText = $inner['contactMessage']['displayName']
                    ?? ($inner['contactMessage']['vcard'] ?? null);
            }

            // =========================
            // DOWNLOAD MEDIA (🔥 مهم)
            // =========================
            if ($mediaUrl) {
                try {
                    Log::info('Downloading media from', ['url' => $mediaUrl]);

                    $fileContent = file_get_contents($mediaUrl);

                    if ($fileContent !== false) {
                        $ext = $this->safeExtensionForStoredMedia($mediaUrl, $mediaMimeType);

                        $fileName = 'wa_'.time().'_'.uniqid().'.'.$ext;

                        $path = public_path('uploads/whatsapp/'.$fileName);

                        if (! file_exists(dirname($path))) {
                            mkdir(dirname($path), 0755, true);
                        }

                        file_put_contents($path, $fileContent);

                        $storedFile = 'uploads/whatsapp/'.$fileName;

                        Log::info('Media saved', ['file' => $storedFile]);
                    }

                } catch (\Exception $e) {
                    Log::error('Media download failed: '.$e->getMessage());
                }
            }

            // =========================
            // VALIDATION
            // =========================
            if (empty($fromPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing sender phone',
                ], 400);
            }

            // =========================
            // CUSTOMER
            // =========================
            $customer = $this->findCustomerByPhone($fromPhone, $organizationId);
            $messageText = $messageText ?? '';

            // =========================
            // SAVE
            // =========================
            $whatsappMessage = WhatsAppMessage::updateOrCreate(
                [
                    'message_id' => $msg['key']['id'] ?? null,
                ],
                [
                    'from_phone' => $fromPhone,
                    'to_phone' => $toPhone,
                    'message' => $messageText ?? '',
                    'message_type' => $messageType,
                    'media_url' => $storedFile,
                    'media_mime_type' => $mediaMimeType,
                    'customer_id' => $customer?->id,
                    'direction' => 'incoming',
                    'status' => 'received',
                    'metadata' => $data,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Ronibot webhook error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * پسوند فایل ذخیره‌شده: URLهای مدیا گاهی به‌صورت «.ogg; codecs=opus» هستند؛
     * pathinfo در PHP پسوند را «ogg; codecs=opus» می‌گیرد و نام فایل روی دیسک با DB هم‌خوان نمی‌شود.
     */
    protected function safeExtensionForStoredMedia(string $mediaUrl, ?string $mimeType): string
    {
        $path = parse_url($mediaUrl, PHP_URL_PATH) ?? '';
        $basename = basename($path);
        $basename = rawurldecode($basename);
        $rawExt = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION) ?: '');

        $first = preg_split('/[;\s]/', $rawExt, 2)[0] ?? '';
        $ext = preg_replace('/[^a-z0-9]/', '', $first);

        $allowed = ['ogg', 'opus', 'mp3', 'm4a', 'aac', 'wav', 'webm', 'mp4', 'm4v', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'bin'];
        if ($ext !== '' && strlen($ext) <= 8 && in_array($ext, $allowed, true)) {
            return $ext === 'opus' ? 'ogg' : $ext;
        }

        $mime = strtolower((string) ($mimeType ?? ''));
        $mime = preg_replace('/;.*$/', '', trim($mime));
        $mimeMap = [
            'audio/ogg' => 'ogg',
            'audio/opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'audio/wav' => 'wav',
            'audio/webm' => 'webm',
            'video/mp4' => 'mp4',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        return $mimeMap[$mime] ?? 'bin';
    }

    /**
     * همان منطق Baileys normalizeMessageContent: فورواردها و پیام‌های زمان‌دار داخل لایه‌هایی مثل ephemeralMessage هستند.
     *
     * @param  array<string, mixed>|null  $content
     * @return array<string, mixed>|null
     */
    protected function extractBaileysInnerMessage(?array $content): ?array
    {
        if ($content === null) {
            return null;
        }

        for ($i = 0; $i < 5; $i++) {
            $next = null;
            if (isset($content['ephemeralMessage']['message'])) {
                $next = $content['ephemeralMessage']['message'];
            } elseif (isset($content['viewOnceMessage']['message'])) {
                $next = $content['viewOnceMessage']['message'];
            } elseif (isset($content['viewOnceMessageV2']['message'])) {
                $next = $content['viewOnceMessageV2']['message'];
            } elseif (isset($content['viewOnceMessageV2Extension']['message'])) {
                $next = $content['viewOnceMessageV2Extension']['message'];
            } elseif (isset($content['documentWithCaptionMessage']['message'])) {
                $next = $content['documentWithCaptionMessage']['message'];
            } elseif (isset($content['editedMessage']['message'])) {
                $next = $content['editedMessage']['message'];
            } elseif (isset($content['associatedChildMessage']['message'])) {
                $next = $content['associatedChildMessage']['message'];
            }

            if ($next === null) {
                break;
            }
            $content = $next;
        }

        return $content;
    }

    /**
     * Find customer by phone number (وب‌هوک بدون کاربر لاگین: بدون اسکوپ سازمان، با فیلتر اختیاری سازمان).
     */
    protected function findCustomerByPhone(string $phone, ?int $organizationId = null): ?Customer
    {
        $phone = $this->formatPhone($phone);

        $query = CustomerContact::withoutGlobalScope('organization')
            ->where(function ($q) {
                $q->where('type', 'phone')->orWhere('type', 'whatsapp');
            })
            ->where(function ($q) use ($phone) {
                $q->where('value', $phone)
                    ->orWhere('value', '+'.$phone)
                    ->orWhere('value', '00'.$phone);
            });

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $contact = $query->first();

        return $contact?->customer;
    }

    /**
     * تشخیص سازمان از payload وب‌هوک (receiver = شماره خط یا session، تاریخچهٔ پیام، تماس مشتری).
     */
    protected function resolveOrganizationIdFromWebhook(Request $request, string $fromPhone, string $receiverRaw): ?int
    {
        $override = $request->input('organization_id');
        if ($override !== null && $override !== '') {
            $id = (int) $override;
            if ($id > 0 && Organization::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $receiverTrim = trim($receiverRaw);
        $receiverDigits = $this->formatPhone(preg_replace('/\D+/', '', $receiverTrim) ?: '');
        $receiverLooksLikePhone = $receiverDigits !== '' && strlen($receiverDigits) >= 8;

        if ($receiverLooksLikePhone) {
            $byLine = $this->findOrganizationIdByRonibotLinePhone($receiverDigits);
            if ($byLine !== null) {
                return $byLine;
            }
        }

        if ($receiverTrim !== '' && ! $receiverLooksLikePhone) {
            $bySession = $this->findOrganizationIdByRonibotSessionId($receiverTrim);
            if ($bySession !== null) {
                return $bySession;
            }
        }

        if ($receiverDigits !== '') {
            $byHistory = $this->findOrganizationIdFromWhatsAppHistory($receiverDigits);
            if ($byHistory !== null) {
                return $byHistory;
            }
        }

        $contacts = CustomerContact::withoutGlobalScope('organization')
            ->where(function ($q) {
                $q->where('type', 'phone')->orWhere('type', 'whatsapp');
            })
            ->where(function ($q) use ($fromPhone) {
                $p = $this->formatPhone($fromPhone);
                $q->where('value', $p)
                    ->orWhere('value', '+'.$p)
                    ->orWhere('value', '00'.$p);
            })
            ->get();

        if ($contacts->count() === 1) {
            return (int) $contacts->first()->organization_id;
        }

        if ($contacts->count() > 1 && $receiverDigits !== '') {
            foreach ($contacts as $c) {
                $exists = WhatsAppMessage::withoutGlobalScope('organization')
                    ->where('organization_id', $c->organization_id)
                    ->where(function ($q) use ($receiverDigits) {
                        $q->where('to_phone', $receiverDigits)
                            ->orWhere('from_phone', $receiverDigits);
                    })
                    ->exists();
                if ($exists) {
                    return (int) $c->organization_id;
                }
            }

            return (int) $contacts->first()->organization_id;
        }

        if ($contacts->count() > 1) {
            Log::warning('Ronibot webhook: duplicate customer phone across organizations without receiver match', [
                'from_phone' => $fromPhone,
                'receiver' => $receiverRaw,
            ]);

            return (int) $contacts->first()->organization_id;
        }

        $fallback = OrganizationContext::getOrganizationId();
        if ($fallback !== null) {
            Log::warning('Ronibot webhook: organization resolved via default fallback; configure line_phone / wa_session_id or ensure prior messages exist', [
                'from_phone' => $fromPhone,
                'receiver' => $receiverRaw,
                'organization_id' => $fallback,
            ]);
        }

        return $fallback;
    }

    protected function findOrganizationIdByRonibotLinePhone(string $normalizedDigits): ?int
    {
        $rows = OrganizationSetting::query()
            ->withoutGlobalScopes()
            ->where('key', 'ronibot')
            ->get();

        foreach ($rows as $row) {
            $val = $row->value;
            if (! is_array($val)) {
                continue;
            }
            foreach (['line_phone', 'wa_line_phone', 'connected_line_phone'] as $key) {
                $line = $val[$key] ?? null;
                if ($line === null || $line === '') {
                    continue;
                }
                if ($this->formatPhone((string) $line) === $normalizedDigits) {
                    return (int) $row->organization_id;
                }
            }
        }

        return null;
    }

    protected function findOrganizationIdByRonibotSessionId(string $sessionId): ?int
    {
        $rows = OrganizationSetting::query()
            ->withoutGlobalScopes()
            ->where('key', 'ronibot')
            ->get();

        foreach ($rows as $row) {
            $val = $row->value;
            if (! is_array($val)) {
                continue;
            }
            foreach (['wa_session_id', 'session_id', 'device_session_id'] as $key) {
                $sid = $val[$key] ?? null;
                if ($sid === null || $sid === '') {
                    continue;
                }
                if (trim((string) $sid) === $sessionId) {
                    return (int) $row->organization_id;
                }
            }
        }

        return null;
    }

    protected function findOrganizationIdFromWhatsAppHistory(string $normalizedPhone): ?int
    {
        $row = WhatsAppMessage::withoutGlobalScope('organization')
            ->whereNotNull('organization_id')
            ->where(function ($q) use ($normalizedPhone) {
                $q->where('to_phone', $normalizedPhone)
                    ->orWhere('from_phone', $normalizedPhone);
            })
            ->orderByDesc('id')
            ->first();

        return $row ? (int) $row->organization_id : null;
    }

    /**
     * Format phone number
     */
    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }

    /**
     * همگام‌سازی سبک گروه‌های واتساپ (بدون ذخیره در اینباکس) — برای کمتر کردن ترافیک وب‌هوک اصلی.
     */
    public function groupSync(Request $request)
    {
        try {
            $validated = $request->validate([
                'groupJid' => 'required|string|max:191',
                'title' => 'nullable|string|max:512',
                'receiver' => 'nullable|string|max:128',
                'participantSender' => 'nullable|string|max:64',
                'organization_id' => 'nullable|integer|exists:organizations,id',
            ]);

            $receiverRaw = (string) ($validated['receiver'] ?? '');
            $participantRaw = (string) ($validated['participantSender'] ?? '');
            $fromHint = $participantRaw !== ''
                ? $this->formatPhone(preg_replace('/\D+/', '', $participantRaw) ?: $participantRaw)
                : '';

            $orgId = $this->resolveOrganizationIdFromWebhook($request, $fromHint, $receiverRaw);
            if ($orgId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization could not be resolved from webhook (receiver / history / participant)',
                ], 422);
            }

            OrganizationContext::setOrganizationId($orgId);

            TelegramGroup::withoutGlobalScope('organization')->updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'channel' => 'whatsapp',
                    'telegram_group_id' => $validated['groupJid'],
                ],
                [
                    'telegram_user_connection_id' => null,
                    'title' => $validated['title'] ?? null,
                    'type' => 'group',
                    'is_active' => true,
                    'last_synced_at' => now(),
                ]
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Ronibot group webhook: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
