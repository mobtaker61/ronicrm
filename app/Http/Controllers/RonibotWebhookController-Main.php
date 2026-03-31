<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
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

            // Extract message data from webhook
            // Note: The structure may vary based on Ronibot's webhook format
            // Adjust these fields based on actual webhook payload
            $fromPhone = $this->formatPhone($data['from'] ?? $data['phone'] ?? $data['sender'] ?? '');
            $messageText = $data['message'] ?? $data['text'] ?? $data['body'] ?? '';
            $messageId = $data['message_id'] ?? $data['id'] ?? null;
            $messageType = $data['type'] ?? $data['message_type'] ?? 'text';
            $mediaUrl = $data['media_url'] ?? $data['file'] ?? $data['image'] ?? null;
            $mediaMimeType = $data['mime_type'] ?? $data['media_mime_type'] ?? null;
            $toPhone = $this->formatPhone($data['to'] ?? $data['receiver'] ?? '');

            if (empty($fromPhone) || empty($messageText)) {
                Log::warning('Ronibot webhook missing required fields', ['data' => $data]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields',
                ], 400);
            }

            // Find or create customer by phone number
            $customer = $this->findCustomerByPhone($fromPhone);

            // Save incoming message
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
                'customer_id' => $customer?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received and processed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Ronibot webhook error: ' . $e->getMessage(), [
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
     * Searches only in customer_contacts table
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $phone = $this->formatPhone($phone);

        // Search only in customer_contacts table
        $contact = CustomerContact::where(function ($q) {
            $q->where('type', 'phone')->orWhere('type', 'whatsapp');
        })
            ->where(function ($q) use ($phone) {
                $q->where('value', $phone)
                    ->orWhere('value', '+' . $phone)
                    ->orWhere('value', '00' . $phone);
            })
            ->first();

        return $contact?->customer;
    }

    /**
     * Format phone number (same as WhatsAppService)
     */
    protected function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading +
        $phone = ltrim($phone, '+');

        // Remove leading 00
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }
}
