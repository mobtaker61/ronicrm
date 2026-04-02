<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use App\Services\CustomerMatchService;
use App\Services\TelegramBotFileUrlService;
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
            if (! $message) {
                return response()->json(['ok' => true]);
            }

            $from = $message['from'] ?? null;
            $chat = $message['chat'] ?? null;
            if (! $from || ! $chat) {
                return response()->json(['ok' => true]);
            }

            $chatId = (string) ($chat['id'] ?? '');
            if ($chatId === '') {
                return response()->json(['ok' => true]);
            }

            $telegramMessageId = $message['message_id'] ?? null;
            if ($telegramMessageId !== null
                && TelegramMessage::where('chat_id', $chatId)
                    ->where('telegram_message_id', (string) $telegramMessageId)
                    ->exists()) {
                return response()->json(['ok' => true]);
            }

            $fromUsername = $from['username'] ?? null;
            $firstName = (string) ($from['first_name'] ?? '');
            $lastName = (string) ($from['last_name'] ?? '');
            $displayName = trim($firstName.' '.$lastName) ?: ($fromUsername ?? $chatId);

            $messageText = $message['text'] ?? $message['caption'] ?? '';
            $resolved = $this->resolveMediaFromBotMessage($message);

            $fromPhone = $from['phone_number'] ?? null;
            $customer = null;
            try {
                $customer = $this->findOrCreateCustomerByTelegram($chatId, $fromUsername, $displayName, $fromPhone);
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
                'message_type' => $resolved['type'],
                'media_url' => $resolved['url'],
                'media_mime_type' => $resolved['mime'],
                'customer_id' => $customer?->id,
                'direction' => 'incoming',
                'status' => 'received',
                'metadata' => $metadata,
            ]);

            Log::info('Telegram message saved', ['chat_id' => $chatId, 'customer_id' => $customer?->id]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['ok' => true]);
        }
    }

    /**
     * @return array{type: string, url: ?string, mime: ?string}
     */
    protected function resolveMediaFromBotMessage(array $message): array
    {
        if (! empty($message['photo'])) {
            $photos = $message['photo'];
            $largest = end($photos);
            $fileId = $largest['file_id'] ?? null;

            return [
                'type' => 'image',
                'url' => TelegramBotFileUrlService::urlForFileId($fileId),
                'mime' => null,
            ];
        }
        if (! empty($message['video'])) {
            $v = $message['video'];

            return [
                'type' => 'video',
                'url' => TelegramBotFileUrlService::urlForFileId($v['file_id'] ?? null),
                'mime' => $v['mime_type'] ?? null,
            ];
        }
        if (! empty($message['animation'])) {
            $v = $message['animation'];

            return [
                'type' => 'animation',
                'url' => TelegramBotFileUrlService::urlForFileId($v['file_id'] ?? null),
                'mime' => $v['mime_type'] ?? null,
            ];
        }
        if (! empty($message['video_note'])) {
            $v = $message['video_note'];

            return [
                'type' => 'video',
                'url' => TelegramBotFileUrlService::urlForFileId($v['file_id'] ?? null),
                'mime' => null,
            ];
        }
        if (! empty($message['voice'])) {
            $v = $message['voice'];

            return [
                'type' => 'audio',
                'url' => TelegramBotFileUrlService::urlForFileId($v['file_id'] ?? null),
                'mime' => $v['mime_type'] ?? null,
            ];
        }
        if (! empty($message['audio'])) {
            $v = $message['audio'];

            return [
                'type' => 'audio',
                'url' => TelegramBotFileUrlService::urlForFileId($v['file_id'] ?? null),
                'mime' => $v['mime_type'] ?? null,
            ];
        }
        if (! empty($message['sticker'])) {
            $v = $message['sticker'];
            $fileId = $v['file_id'] ?? ($v['thumb']['file_id'] ?? null);

            return [
                'type' => 'sticker',
                'url' => TelegramBotFileUrlService::urlForFileId($fileId),
                'mime' => $v['mime_type'] ?? null,
            ];
        }
        if (! empty($message['document'])) {
            $v = $message['document'];

            return [
                'type' => 'document',
                'url' => TelegramBotFileUrlService::urlForFileId($v['file_id'] ?? null),
                'mime' => $v['mime_type'] ?? null,
            ];
        }

        return ['type' => 'text', 'url' => null, 'mime' => null];
    }

    /**
     * Find customer by chat_id, Telegram username, or phone; otherwise create new.
     * Uses CustomerMatchService to prevent duplicates.
     */
    protected function findOrCreateCustomerByTelegram(string $chatId, ?string $username, string $displayName, ?string $phone = null): ?Customer
    {
        $existing = CustomerMatchService::findExistingByTelegram($chatId, $username, $phone);
        if ($existing) {
            $this->ensureTelegramContact($existing, $chatId);

            return $existing;
        }

        $customer = Customer::create([
            'name' => $displayName,
            'type' => 'person',
            'status' => 'lead',
            'source' => 'telegram',
            'created_by' => null,
        ]);

        $this->ensureTelegramContact($customer, $chatId);
        if ($phone) {
            $customer->update(['phone' => $phone]);
            if (! CustomerContact::where('customer_id', $customer->id)->where('type', 'phone')->exists()) {
                $customer->contacts()->create(['type' => 'phone', 'value' => $phone, 'is_primary' => false]);
            }
        }

        return $customer;
    }

    protected function ensureTelegramContact(Customer $customer, string $chatId): void
    {
        $contact = CustomerContact::where('customer_id', $customer->id)->where('type', 'telegram')->first();
        if ($contact) {
            if ($contact->value !== $chatId) {
                $contact->update(['value' => $chatId]);
            }
        } else {
            $customer->contacts()->create([
                'type' => 'telegram',
                'value' => $chatId,
                'is_primary' => true,
            ]);
        }
    }
}
