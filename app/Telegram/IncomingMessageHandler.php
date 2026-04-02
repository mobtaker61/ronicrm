<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Jobs\TelegramSaveIncomingMessageJob;
use App\Services\TelegramBotFileUrlService;
use App\Services\TelegramMediaStorageService;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Media\Audio;
use danog\MadelineProto\EventHandler\Media\Document;
use danog\MadelineProto\EventHandler\Media\Gif;
use danog\MadelineProto\EventHandler\Media\MaskSticker;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\EventHandler\Media\RoundVideo;
use danog\MadelineProto\EventHandler\Media\Sticker;
use danog\MadelineProto\EventHandler\Media\Video;
use danog\MadelineProto\EventHandler\Media\Voice;
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
    public function handleIncomingPrivateMessage(Incoming&PrivateMessage $message): void
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
                $username = '@'.ltrim($username, '@');
            }
        } catch (\Throwable) {
            // ignore
        }

        $mediaUrl = null;
        $mediaMimeType = null;
        $messageType = 'text';
        $media = $message->media;
        if ($media !== null) {
            $mediaUrl = TelegramBotFileUrlService::urlForFileId($media->botApiFileId);
            if ($mediaUrl === null) {
                $mediaUrl = TelegramMediaStorageService::downloadMediaObjectToPublicDisk($media);
            }
            $mediaMimeType = $media->mimeType !== '' ? $media->mimeType : null;
            $messageType = match (true) {
                $media instanceof Photo => 'image',
                $media instanceof Video => 'video',
                $media instanceof Gif => 'animation',
                $media instanceof RoundVideo => 'video',
                $media instanceof Voice => 'audio',
                $media instanceof Audio => 'audio',
                $media instanceof Sticker, $media instanceof MaskSticker => 'sticker',
                $media instanceof Document => 'document',
                default => 'document',
            };
        }

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
