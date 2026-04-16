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
                Log::warning('Ronibot webhook: group message received', [
                    'receiver' => (string) ($data['receiver'] ?? ''),
                    'message_id' => (string) ($msg['key']['id'] ?? ''),
                    'group_jid' => (string) $remoteJid,
                    'participant' => (string) ($msg['key']['participant'] ?? ''),
                ]);

                // Group message: do not store in inbox (whatsapp_messages).
                // Instead, upsert basic group info so group lists stay in sync.
                $receiverRaw = (string) ($data['receiver'] ?? '');
                $participant = (string) ($msg['key']['participant'] ?? '');
                $participantDigits = $this->extractDigitsFromWaAddress($participant);

                // For groups, if multiple orgs share the same receiver (same WA session/line),
                // we must upsert the group for ALL matching orgs.
                [$orgIds, $receiverDebug] = $this->resolveOrganizationIdsFromReceiver($request, $receiverRaw, true);

                if ($orgIds !== []) {
                    $groupTitle = $data['groupTitle'] ?? $data['title'] ?? null;
                    if (! is_string($groupTitle) || trim($groupTitle) === '') {
                        $groupTitle = null;
                    }

                    Log::info('Ronibot webhook: group org resolution', [
                        'groupJid' => (string) $remoteJid,
                        'receiver' => $receiverRaw,
                        'participant' => $participant,
                        'participant_digits' => $participantDigits,
                        'resolved_org_ids' => $orgIds,
                        'receiver_debug' => $receiverDebug,
                        'group_title' => $groupTitle,                        
                    ]);

                    if (count($orgIds) > 1) {
                        Log::info('Ronibot webhook: group receiver matched multiple organizations; upserting for all', [
                            'groupJid' => (string) $remoteJid,
                            'receiver' => $receiverRaw,
                            'org_ids' => $orgIds,
                        ]);
                    }

                    foreach (array_unique($orgIds) as $organizationId) {
                        OrganizationContext::setOrganizationId((int) $organizationId);
                        TelegramGroup::withoutGlobalScope('organization')->updateOrCreate(
                            [
                                'organization_id' => (int) $organizationId,
                                'channel' => 'whatsapp',
                                'telegram_group_id' => (string) $remoteJid,
                            ],
                            [
                                'telegram_user_connection_id' => null,
                                'title' => $groupTitle,
                                'type' => 'group',
                                'is_active' => true,
                                'last_synced_at' => now(),
                            ]
                        );
                    }
                } else {
                    // For group messages, DO NOT fallback to contact/history-based org resolution.
                    // A shared receiver/webhook can map to multiple orgs and fallback is often wrong,
                    // causing groups to be registered under the wrong organization.
                    Log::warning('Ronibot webhook: group message received but receiver did not match any organization; group not stored', [
                        'groupJid' => (string) $remoteJid,
                        'receiver' => $receiverRaw,
                        'participant' => $participant,
                        'receiver_debug' => $receiverDebug,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => $orgIds !== []
                        ? 'Group chat stored (metadata only) and ignored for inbox'
                        : 'Group chat ignored for inbox (unmapped receiver)',
                ]);
            }

            // Ronibot BulkController sends top-level mediaUrl; raw Baileys forward uses data[0].media.url
            $mediaUrl = $data['mediaUrl'] ?? ($msg['media']['url'] ?? null);

            // =========================
            // PHONES
            // =========================
            $fromPhone = $this->extractInboundSenderPhone($data, $msg);

            $receiverRaw = (string) ($data['receiver'] ?? '');

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

            $messageKeyId = isset($msg['key']['id']) ? (string) $msg['key']['id'] : null;
            $toPhone = $this->resolveInboundBusinessLinePhone($organizationId, $receiverRaw, $messageKeyId);

            // =========================
            // SAVE
            // =========================
            $whatsappMessage = WhatsAppMessage::updateOrCreate(
                [
                    'message_id' => $messageKeyId,
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
     * Ronibot (Baileys) may send `sender` (derived from remoteJidAlt) as a 15-digit LID for some accounts
     * (often WhatsApp Business / privacy). Inbox matching and replies require the real phone/MSISDN.
     *
     * Strategy:
     * - Prefer extracting digits from standard JIDs (`remoteJid`, `participant`, `remoteJidAlt`) when they contain @.
     * - Prefer "phone-like" lengths (10-14 digits). Avoid 15-digit LID if a better candidate exists.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $msg
     */
    protected function extractInboundSenderPhone(array $data, array $msg): string
    {
        $candidates = [];

        $pushCandidate = function (?string $value, string $source) use (&$candidates): void {
            $value = $value === null ? '' : trim($value);
            if ($value === '') {
                return;
            }
            $digits = $this->extractDigitsFromWaAddress($value);
            if ($digits === '') {
                return;
            }
            $candidates[] = [
                'source' => $source,
                'value' => $value,
                'digits' => $digits,
                'len' => strlen($digits),
            ];
        };

        // Standard places (remoteJid usually remains the real number even when remoteJidAlt becomes @lid)
        $pushCandidate($msg['key']['remoteJid'] ?? null, 'msg.key.remoteJid');
        $pushCandidate($msg['key']['participant'] ?? null, 'msg.key.participant');
        $pushCandidate($msg['key']['remoteJidAlt'] ?? null, 'msg.key.remoteJidAlt');

        // Ronibot wrapper fields
        $pushCandidate(isset($data['sender']) ? (string) $data['sender'] : null, 'data.sender');
        $pushCandidate(isset($data['from']) ? (string) $data['from'] : null, 'data.from');

        if ($candidates === []) {
            return '';
        }

        // Rank: prefer 10-14 digits (common MSISDN lengths), then 8-9, then 15 (LID-ish), then others.
        usort($candidates, function ($a, $b) {
            $score = function (int $len): int {
                if ($len >= 10 && $len <= 14) {
                    return 0;
                }
                if ($len >= 8 && $len <= 9) {
                    return 1;
                }
                if ($len === 15) {
                    return 3;
                }
                return 2;
            };

            $sa = $score((int) $a['len']);
            $sb = $score((int) $b['len']);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            // Tie-break: prefer values that look like a JID (contain '@') over plain digits
            $ajid = str_contains((string) $a['value'], '@') ? 0 : 1;
            $bjid = str_contains((string) $b['value'], '@') ? 0 : 1;
            if ($ajid !== $bjid) {
                return $ajid <=> $bjid;
            }

            // Finally: prefer shorter (phone) over longer (ids)
            return ((int) $a['len']) <=> ((int) $b['len']);
        });

        $best = (string) ($candidates[0]['digits'] ?? '');

        // If we ended up with a 15-digit id but another candidate is 10-14, switch and log.
        if (strlen($best) === 15) {
            foreach ($candidates as $c) {
                $len = (int) ($c['len'] ?? 0);
                if ($len >= 10 && $len <= 14) {
                    Log::warning('Ronibot webhook: sender looked like LID; using phone-like candidate instead', [
                        'lid_digits' => $best,
                        'chosen_digits' => (string) $c['digits'],
                        'chosen_source' => (string) ($c['source'] ?? ''),
                        'all_sources' => array_map(fn ($x) => (string) ($x['source'] ?? ''), $candidates),
                    ]);
                    $best = (string) $c['digits'];
                    break;
                }
            }
        }

        return $this->formatPhone($best);
    }

    /**
     * Extract digits from:
     * - plain phone strings ("+98912...")
     * - WhatsApp JIDs ("98912@s.whatsapp.net", "98912@c.us", "...@lid")
     */
    protected function extractDigitsFromWaAddress(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // JID-like
        if (str_contains($value, '@')) {
            $value = explode('@', $value, 2)[0] ?? $value;
        }

        // Keep digits only
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        // Normalize leading 00 (international prefix)
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
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

    /**
     * Resolve org using receiver only (strict).
     * - receiver as phone line digits -> org by line_phone/wa_line_phone/connected_line_phone
     * - receiver as session id        -> org by wa_session_id/session_id/device_session_id
     *
     * This is useful for WhatsApp group messages where participant is not a reliable org hint
     * and contact-based fallbacks can mis-attribute to a different organization.
     */
    protected function resolveOrganizationIdFromReceiverFirst(Request $request, string $receiverRaw): ?int
    {
        $override = $request->input('organization_id');
        if ($override !== null && $override !== '') {
            $id = (int) $override;
            if ($id > 0 && Organization::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $receiverTrim = trim((string) $receiverRaw);
        if ($receiverTrim === '') {
            return null;
        }

        $receiverDigits = $this->formatPhone(preg_replace('/\D+/', '', $receiverTrim) ?: '');
        if ($receiverDigits !== '' && strlen($receiverDigits) >= 8) {
            return $this->findOrganizationIdByRonibotLinePhone($receiverDigits);
        }

        // Non-phone receiver: treat as session id
        return $this->findOrganizationIdByRonibotSessionId($receiverTrim);
    }

    /**
     * Resolve possibly-multiple org ids from receiver (line digits or session id).
     * When multiple orgs are configured with the same WA session/line, group messages must be
     * registered for all of them.
     *
     * @return array<int, int> organization ids
     */
    /**
     * @return array{0: array<int, int>, 1: array<string, mixed>}
     */
    protected function resolveOrganizationIdsFromReceiver(Request $request, string $receiverRaw, bool $withDebug = false): array
    {
        $override = $request->input('organization_id');
        if ($override !== null && $override !== '') {
            $id = (int) $override;
            if ($id > 0 && Organization::query()->whereKey($id)->exists()) {
                return [[$id], ['override' => $id]];
            }
        }

        $receiverTrim = trim((string) $receiverRaw);
        if ($receiverTrim === '') {
            return [[], ['reason' => 'empty_receiver']];
        }

        $receiverDigits = $this->formatPhone(preg_replace('/\D+/', '', $receiverTrim) ?: '');
        $looksLikePhone = $receiverDigits !== '' && strlen($receiverDigits) >= 8;

        $rows = OrganizationSetting::query()
            ->withoutGlobalScopes()
            ->where('key', 'ronibot')
            ->get();

        $matched = [];
        $debugMatches = [];
        foreach ($rows as $row) {
            $val = $row->value;
            if (! is_array($val)) {
                continue;
            }

            if ($looksLikePhone) {
                foreach (['line_phone', 'wa_line_phone', 'connected_line_phone'] as $k) {
                    $line = $val[$k] ?? null;
                    if ($line === null || $line === '') {
                        continue;
                    }
                    if ($this->formatPhone((string) $line) === $receiverDigits) {
                        $matched[] = (int) $row->organization_id;
                        if ($withDebug) {
                            $debugMatches[] = [
                                'org_id' => (int) $row->organization_id,
                                'match_type' => 'line_phone',
                                'key' => $k,
                                'receiver_digits' => $receiverDigits,
                                'stored' => (string) $line,
                            ];
                        }
                        break;
                    }
                }
            } else {
                foreach (['wa_session_id', 'session_id', 'device_session_id'] as $k) {
                    $sid = $val[$k] ?? null;
                    if ($sid === null || $sid === '') {
                        continue;
                    }
                    if (trim((string) $sid) === $receiverTrim) {
                        $matched[] = (int) $row->organization_id;
                        if ($withDebug) {
                            $debugMatches[] = [
                                'org_id' => (int) $row->organization_id,
                                'match_type' => 'session_id',
                                'key' => $k,
                                'receiver' => $receiverTrim,
                                'stored' => (string) $sid,
                            ];
                        }
                        break;
                    }
                }
            }
        }

        $orgIds = array_values(array_unique(array_filter($matched, fn ($id) => $id > 0)));

        $debug = [];
        if ($withDebug) {
            $debug = [
                'receiver_trim' => $receiverTrim,
                'receiver_digits' => $receiverDigits,
                'looks_like_phone' => $looksLikePhone,
                'matched' => $debugMatches,
                'total_settings_rows' => $rows->count(),
            ];
        }

        return [$orgIds, $debug];
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
     * شمارهٔ خط «ما» برای پیام ورودی: Ronibot گاهی receiver را device_id کوتاه می‌فرستد؛
     * نباید to_phone را با آن بازنویسی کرد (همان رکورد با updateOrCreate دوباره خورده می‌شود).
     */
    protected function resolveInboundBusinessLinePhone(int $organizationId, string $receiverRaw, ?string $messageIdKey): string
    {
        $candidate = $this->formatPhone($receiverRaw);

        if ($this->receiverDigitsLookLikePhoneLine($candidate)) {
            return $candidate;
        }

        $line = $this->getRonibotLinePhoneDigitsForOrganization($organizationId);
        if ($line !== '') {
            return $line;
        }

        if ($messageIdKey !== null && $messageIdKey !== '') {
            $existing = WhatsAppMessage::withoutGlobalScope('organization')
                ->where('organization_id', $organizationId)
                ->where('message_id', $messageIdKey)
                ->first();
            if ($existing !== null) {
                $prev = $this->formatPhone((string) ($existing->to_phone ?? ''));
                if ($this->receiverDigitsLookLikePhoneLine($prev)) {
                    return $prev;
                }
            }
        }

        return $candidate;
    }

    protected function receiverDigitsLookLikePhoneLine(string $digits): bool
    {
        return $digits !== '' && strlen($digits) >= 8;
    }

    protected function getRonibotLinePhoneDigitsForOrganization(int $organizationId): string
    {
        $row = OrganizationSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('key', 'ronibot')
            ->first();

        if ($row === null || ! is_array($row->value)) {
            return '';
        }

        $val = $row->value;
        foreach (['line_phone', 'wa_line_phone', 'connected_line_phone'] as $key) {
            $line = $val[$key] ?? null;
            if ($line !== null && $line !== '') {
                return $this->formatPhone((string) $line);
            }
        }

        return '';
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
