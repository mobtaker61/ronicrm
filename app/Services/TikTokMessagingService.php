<?php

namespace App\Services;

use App\Models\TikTokConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound TikTok DM / Business Messaging.
 *
 * Official HTTP routes vary by product approval. Configure when TikTok enables messaging for your app.
 */
class TikTokMessagingService
{
    public function isConfigured(): bool
    {
        $conn = TikTokConnection::getActive();

        return $conn && $conn->getAccessToken()
            && trim((string) config('services.tiktok.messaging_send_url', '')) !== '';
    }

    /**
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendMessage(string $peerOpenId, string $message, ?string $fileUrl = null): array
    {
        $conn = TikTokConnection::getActive();
        if (! $conn || ! $conn->getAccessToken()) {
            return [
                'success' => false,
                'error' => 'TikTok is not connected. Go to Settings → TikTok and connect your account.',
            ];
        }

        $sendUrl = trim((string) config('services.tiktok.messaging_send_url', ''));
        if ($sendUrl === '') {
            return [
                'success' => false,
                'error' => 'TikTok Business Messaging send URL is not configured. After TikTok approves DM for your app, set TIKTOK_MESSAGING_SEND_URL (and any required headers) in .env — see docs/tiktok-developer-form-copy.md.',
            ];
        }

        if ($conn->isTokenExpired()) {
            $oauth = app(TikTokOAuthService::class);
            $refreshed = $oauth->refreshConnection($conn);
            if (isset($refreshed['error'])) {
                return ['success' => false, 'error' => 'Token refresh failed: '.$refreshed['error']];
            }
            $conn->refresh();
        }

        $token = $conn->getAccessToken();
        $payload = array_filter([
            'recipient_open_id' => $peerOpenId,
            'text' => $message,
            'media_url' => $fileUrl,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = Http::withToken($token)
                ->timeout(45)
                ->acceptJson()
                ->asJson()
                ->post($sendUrl, $payload);
        } catch (\Throwable $e) {
            Log::error('TikTok send message HTTP error', ['message' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            $body = $response->body();
            Log::warning('TikTok send message failed', ['status' => $response->status(), 'body' => $body]);

            return ['success' => false, 'error' => 'TikTok API error: '.$body];
        }

        $json = $response->json();
        $mid = null;
        if (is_array($json)) {
            $mid = $json['message_id'] ?? $json['data']['message_id'] ?? null;
        }

        return [
            'success' => true,
            'message_id' => is_string($mid) ? $mid : null,
        ];
    }
}
