<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\WhatsAppMessage;
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

            // Ronibot BulkController sends top-level mediaUrl; raw Baileys forward uses data[0].media.url
            $mediaUrl = $data['mediaUrl'] ?? ($msg['media']['url'] ?? null);

            // =========================
            // PHONES
            // =========================
            $fromPhone = $this->formatPhone(
                $data['sender']
                ?? ($msg['key']['remoteJidAlt'] ?? '')
            );

            $toPhone = $this->formatPhone(
                $data['receiver'] ?? ''
            );

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
            $customer = $this->findCustomerByPhone($fromPhone);
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
     * Find customer by phone number
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $phone = $this->formatPhone($phone);

        $contact = CustomerContact::where(function ($q) {
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
}
