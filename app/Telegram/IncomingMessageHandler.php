<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Jobs\TelegramSaveIncomingMessageJob;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;

/**
 * MadelineProto EventHandler for real-time incoming Telegram DMs.
 * Receives updateNewMessage / updateShortMessage via the built-in feed.
 * Run via: php artisan telegram:listen-incoming
 * Keep running (e.g. screen, supervisor) for 24/7 inbox sync.
 */
class IncomingMessageHandler extends SimpleEventHandler
{
    public function getReportPeers()
    {
        return [];
    }

    #[Handler]
    public function handleIncomingPrivateMessage(Incoming & PrivateMessage $message): void
    {
        if ($message->out) {
            return;
        }

        Log::info('Telegram IncomingMessageHandler: private incoming', [
            'sender_id' => $message->senderId ?? null,
            'msg_id' => $message->id ?? null,
        ]);

        $chatId = (string) $message->senderId;
        $msgId = (string) $message->id;
        $text = $message->message ?? '';

        $username = null;
        try {
            $info = $message->getClient()->getInfo($message->senderId);
            $username = $info['User']['username'] ?? null;
            if ($username !== null && $username !== '') {
                $username = '@' . ltrim($username, '@');
            }
        } catch (\Throwable) {
            // ignore
        }

        $mediaUrl = null;
        $mediaMimeType = null;
        $messageType = 'text';
        // Media download can be added later; for now we only persist text

        $payload = [
            'telegram_message_id' => $msgId,
            'chat_id' => $chatId,
            'from_username' => $username,
            'message' => $text,
            'message_type' => $messageType,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
        ];

        try {
            dispatch_sync(new TelegramSaveIncomingMessageJob($payload));
        } catch (\Throwable $e) {
            Log::error('IncomingMessageHandler: failed to save message', [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
