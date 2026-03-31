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
            $mediaUrl = $data['mediaUrl'] ?? null;
            $mediaMimeType = null;
            $storedFile = null;

            if (isset($msg['message']['conversation'])) {
                $messageText = $msg['message']['conversation'];
            } elseif (isset($msg['message']['extendedTextMessage']['text'])) {
                $messageText = $msg['message']['extendedTextMessage']['text'];
            } elseif (isset($msg['message']['imageMessage'])) {
                $messageType = 'image';
                $messageText = $msg['message']['imageMessage']['caption'] ?? null;
                $mediaMimeType = $msg['message']['imageMessage']['mimetype'] ?? null;
            } elseif (isset($msg['message']['videoMessage'])) {
                $messageType = 'video';
                $messageText = $msg['message']['videoMessage']['caption'] ?? null;
                $mediaMimeType = $msg['message']['videoMessage']['mimetype'] ?? null;
            } elseif (isset($msg['message']['audioMessage'])) {
                $messageType = 'audio';
                $mediaMimeType = $msg['message']['audioMessage']['mimetype'] ?? null;
            } elseif (isset($msg['message']['documentMessage'])) {
                $messageType = 'document';
                $messageText = $msg['message']['documentMessage']['fileName'] ?? null;
                $mediaMimeType = $msg['message']['documentMessage']['mimetype'] ?? null;
            }

            // =========================
            // DOWNLOAD MEDIA (🔥 مهم)
            // =========================
            if ($mediaUrl) {
                try {
                    Log::info('Downloading media from', ['url' => $mediaUrl]);

                    $fileContent = file_get_contents($mediaUrl);

                    if ($fileContent !== false) {
                        $ext = pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';

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
