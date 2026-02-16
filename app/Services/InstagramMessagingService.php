<?php

namespace App\Services;

use App\Models\InstagramConnection;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Messaging API (Meta). Prefers OAuth connection; falls back to legacy Setting token.
 */
class InstagramMessagingService
{
    public function isConfigured(): bool
    {
        $conn = InstagramConnection::getActive();
        if ($conn && $conn->getAccessToken()) {
            return true;
        }
        $settings = Setting::get('instagram', []);
        return !empty($settings['enabled']) && !empty($settings['access_token']);
    }

    /**
     * Send message. Uses InstagramConnection token when available; otherwise legacy access_token from settings.
     */
    public function sendMessage(string $igUserId, string $message, ?string $fileUrl = null): array
    {
        $meta = app(MetaInstagramService::class);
        $conn = InstagramConnection::getActive();
        if ($conn && $conn->getAccessToken()) {
            if ($conn->isTokenExpired()) {
                $meta->refreshToken($conn);
            }
            return $meta->sendMessage($conn, $igUserId, $message, $fileUrl);
        }
        $settings = Setting::get('instagram', []);
        $token = $settings['access_token'] ?? null;
        if (empty($token)) {
            return [
                'success' => false,
                'error' => 'Instagram is not connected. Go to Settings → Instagram and connect your account.',
            ];
        }
        return $this->sendWithToken($token, $igUserId, $message);
    }

    protected function sendWithToken(string $token, string $igUserId, string $message): array
    {
        try {
            $url = 'https://graph.instagram.com/v21.0/me/messages';
            $payload = [
                'recipient' => ['id' => $igUserId],
                'message' => ['text' => $message],
            ];
            $response = \Illuminate\Support\Facades\Http::withToken($token)->timeout(30)->post($url, $payload);
            $data = $response->json();
            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'message_id' => $data['message_id'] ?? null];
            }
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown error'];
        } catch (\Exception $e) {
            Log::channel('instagram')->error('Instagram sendMessage error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
