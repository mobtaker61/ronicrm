<?php

namespace App\Http\Controllers;

use App\Models\TikTokConnection;
use App\Models\TikTokMessage;
use App\Models\TikTokWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Developer Portal webhooks.
 *
 * @see https://developers.tiktok.com/doc/webhooks-overview
 * @see https://developers.tiktok.com/doc/webhooks-verification
 */
class TikTokWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        return response('', 200);
    }

    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signatureHeader = $request->header('TikTok-Signature', '')
            ?: $request->header('Tiktok-Signature', '');
        $clientSecret = trim((string) config('services.tiktok.client_secret', ''));

        if ($clientSecret !== '') {
            if (! $this->validateSignature($rawBody, $signatureHeader, $clientSecret)) {
                Log::warning('TikTok webhook signature invalid');

                return response('Forbidden', 403);
            }
        } else {
            Log::warning('TikTok webhook accepted without signature verification (TIKTOK_CLIENT_SECRET empty)');
        }

        $data = $request->all();
        if (! is_array($data) || empty($data['event'])) {
            return response('', 200);
        }

        $eventType = (string) $data['event'];
        $userOpenid = isset($data['user_openid']) ? (string) $data['user_openid'] : null;
        $createTime = isset($data['create_time']) ? (int) $data['create_time'] : null;
        $contentRaw = isset($data['content']) ? (string) $data['content'] : null;

        $contentDecoded = null;
        if ($contentRaw !== null && $contentRaw !== '') {
            $contentDecoded = json_decode($contentRaw, true);
            if (! is_array($contentDecoded)) {
                $contentDecoded = null;
            }
        }

        $connection = null;
        if ($userOpenid) {
            $connection = TikTokConnection::withoutGlobalScopes()
                ->where('open_id', $userOpenid)
                ->orderByDesc('updated_at')
                ->first();
        }

        TikTokWebhookEvent::create([
            'tiktok_connection_id' => $connection?->id,
            'event_type' => $eventType,
            'user_openid' => $userOpenid,
            'create_time' => $createTime,
            'content_raw' => $contentRaw,
            'payload' => $data,
        ]);

        if ($connection) {
            $connection->update(['last_webhook_event_at' => now()]);
        }

        if ($eventType === 'authorization.removed' && $userOpenid) {
            TikTokConnection::withoutGlobalScopes()
                ->where('open_id', $userOpenid)
                ->delete();

            return response('', 200);
        }

        $this->tryIngestMessagingEvent($eventType, $contentDecoded, $userOpenid, $connection);

        return response('', 200);
    }

    protected function validateSignature(string $rawBody, string $header, string $clientSecret): bool
    {
        if ($header === '') {
            return false;
        }
        $timestamp = null;
        $signature = null;
        foreach (array_map('trim', explode(',', $header)) as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $part, 2);
            if ($k === 't') {
                $timestamp = $v;
            }
            if ($k === 's') {
                $signature = $v;
            }
        }
        if ($timestamp === null || $signature === null) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 600) {
            return false;
        }
        $signedPayload = $timestamp.'.'.$rawBody;
        $expected = hash_hmac('sha256', $signedPayload, $clientSecret);

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    /**
     * Best-effort: when TikTok sends DM-related events, map payload into tiktok_messages.
     * Field names may change per product; adjust when official messaging event docs are wired.
     */
    protected function tryIngestMessagingEvent(string $eventType, ?array $content, ?string $eventUserOpenid, ?TikTokConnection $connection): void
    {
        if ($content === null || $connection === null) {
            return;
        }
        if (! str_contains(strtolower($eventType), 'message') && ! str_contains(strtolower($eventType), 'im')) {
            return;
        }

        $peerOpenId = $content['sender_open_id'] ?? $content['from_open_id'] ?? $content['user_open_id'] ?? null;
        $text = $content['text'] ?? $content['message'] ?? $content['content'] ?? null;
        $messageId = $content['message_id'] ?? $content['id'] ?? null;
        $conversationId = $content['conversation_id'] ?? null;

        if (! is_string($peerOpenId) || $peerOpenId === '') {
            return;
        }
        if ($eventUserOpenid && $peerOpenId === $eventUserOpenid) {
            return;
        }

        $body = is_string($text) ? $text : '';

        if (is_string($messageId) && $messageId !== '') {
            $dup = TikTokMessage::withoutGlobalScopes()
                ->where('organization_id', $connection->organization_id)
                ->where('tiktok_message_id', $messageId)
                ->exists();
            if ($dup) {
                return;
            }
        }

        TikTokMessage::create([
            'organization_id' => $connection->organization_id,
            'tiktok_connection_id' => $connection->id,
            'tiktok_message_id' => is_string($messageId) ? $messageId : null,
            'conversation_id' => is_string($conversationId) ? $conversationId : null,
            'tiktok_open_id' => $peerOpenId,
            'from_display_name' => isset($content['sender_display_name']) ? (string) $content['sender_display_name'] : null,
            'message' => $body !== '' ? $body : null,
            'message_type' => 'text',
            'customer_id' => null,
            'direction' => 'incoming',
            'status' => 'received',
            'metadata' => $content,
        ]);
    }
}
