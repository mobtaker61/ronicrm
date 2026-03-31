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

            // =========================
            // VALIDATE STRUCTURE
            // =========================
            $payload = $data['payload'] ?? null;

            if (! $payload || ! isset($payload['data'][0])) {
                Log::warning('Invalid payload structure', $data);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payload structure',
                ], 400);
            }

            $msg = $payload['data'][0];

            // =========================
            // EXTRACT PHONES
            // =========================
            $fromPhone = $this->formatPhone(
                $data['sender']
                ?? ($msg['key']['remoteJidAlt'] ?? '')
            );

            $toPhone = $this->formatPhone(
                $data['receiver'] ?? ''
            );

            // =========================
            // EXTRACT MESSAGE
            // =========================
            $messageText = null;
            $messageType = 'text';
            $mediaUrl = null;
            $mediaMimeType = null;

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
            // MESSAGE ID
            // =========================
            $messageId = $msg['key']['id'] ?? null;

            // =========================
            // VALIDATION (FIXED)
            // =========================
            if (empty($fromPhone)) {
                Log::warning('Missing sender phone', $data);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing sender phone',
                ], 400);
            }

            // =========================
            // FIND CUSTOMER
            // =========================
            $customer = $this->findCustomerByPhone($fromPhone);

            // =========================
            // SAVE MESSAGE
            // =========================
            $whatsappMessage = WhatsAppMessage::create([
                'message_id' => $messageId,
                'from_phone' => $fromPhone,
                'to_phone' => $toPhone,
                'message' => $messageText,
                'message_type' => $messageType,
                'media_url' => $mediaUrl,
                'media_mime_type' => $mediaMimeType,
                'customer_id' => $customer?->id,
                'direction' => 'incoming',
                'status' => 'received',
                'metadata' => $data,
            ]);

            Log::info('WhatsApp message saved', [
                'message_id' => $whatsappMessage->id,
                'from' => $fromPhone,
                'type' => $messageType,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Ronibot webhook error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

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
