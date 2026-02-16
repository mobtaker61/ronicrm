<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram Bot API updates (webhook).
     * Payload: Telegram Update object (update_id, message, etc.)
     */
    public function handle(Request $request)
    {
        try {
            $data = $request->all();

            $message = $data['message'] ?? $data['edited_message'] ?? null;
            if (!$message) {
                return response()->json(['ok' => true]);
            }

            $from = $message['from'] ?? null;
            $chat = $message['chat'] ?? null;
            if (!$from || !$chat) {
                return response()->json(['ok' => true]);
            }

            $chatId = (string) ($chat['id'] ?? '');
            if ($chatId === '') {
                return response()->json(['ok' => true]);
            }

            $fromUsername = $from['username'] ?? null;
            $firstName = (string) ($from['first_name'] ?? '');
            $lastName = (string) ($from['last_name'] ?? '');
            $displayName = trim($firstName . ' ' . $lastName) ?: ($fromUsername ?? $chatId);

            $messageText = $message['text'] ?? $message['caption'] ?? '';
            $telegramMessageId = $message['message_id'] ?? null;
            $mediaUrl = null;
            $messageType = 'text';

            if (!empty($message['photo'])) {
                $photos = $message['photo'];
                $largest = end($photos);
                $fileId = $largest['file_id'] ?? null;
                if ($fileId) {
                    $mediaUrl = $this->getFileUrl($fileId);
                }
                $messageType = 'image';
            } elseif (!empty($message['document'])) {
                $fileId = $message['document']['file_id'] ?? null;
                if ($fileId) {
                    $mediaUrl = $this->getFileUrl($fileId);
                }
                $messageType = 'document';
            }

            $customer = null;
            try {
                $customer = $this->findOrCreateCustomerByTelegram($chatId, $fromUsername, $displayName);
            } catch (\Throwable $e) {
                Log::warning('Telegram webhook: could not find/create customer', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }

            $metadata = is_array($data) ? $data : [];
            TelegramMessage::create([
                'telegram_message_id' => $telegramMessageId,
                'chat_id' => $chatId,
                'from_username' => $fromUsername,
                'message' => $messageText !== '' ? $messageText : null,
                'message_type' => $messageType,
                'media_url' => $mediaUrl,
                'media_mime_type' => null,
                'customer_id' => $customer?->id,
                'direction' => 'incoming',
                'status' => 'received',
                'metadata' => $metadata,
            ]);

            Log::info('Telegram message saved', ['chat_id' => $chatId, 'customer_id' => $customer?->id]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Always return 200 so Telegram does not retry; we have logged the error
            return response()->json(['ok' => true]);
        }
    }

    protected function findOrCreateCustomerByTelegram(string $chatId, ?string $username, string $displayName): ?Customer
    {
        $contact = CustomerContact::where('type', 'telegram')
            ->where('value', $chatId)
            ->first();

        if ($contact) {
            return $contact->customer;
        }

        // Optional: create customer for new Telegram users (source = telegram)
        $customer = Customer::create([
            'name' => $displayName,
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

        return $customer;
    }

    /**
     * Get public URL for a file_id using Telegram getFile + bot token.
     */
    protected function getFileUrl(string $fileId): ?string
    {
        try {
            $settings = \App\Models\Setting::get('telegram', []);
            $token = $settings['bot_token'] ?? '';
            if ($token === '') {
                return null;
            }
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId]);
            $data = $response->json();
            $path = $data['result']['file_path'] ?? null;
            if ($path === null || $path === '') {
                return null;
            }
            return "https://api.telegram.org/file/bot{$token}/{$path}";
        } catch (\Throwable $e) {
            Log::warning('Telegram getFileUrl failed: ' . $e->getMessage());
            return null;
        }
    }
}
