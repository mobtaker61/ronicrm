<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Messaging API (Meta). Requires Meta App, Instagram Business/Creator account, OAuth and App Review.
 * Stub returns "not configured" until Meta app is connected.
 */
class InstagramMessagingService
{
    protected bool $enabled;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $settings = Setting::get('instagram', []);
        $this->enabled = $settings['enabled'] ?? false;
        $this->accessToken = $settings['access_token'] ?? null;
    }

    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->accessToken);
    }

    public function sendMessage(string $igUserId, string $message, ?string $fileUrl = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Instagram Messaging is not configured. Connect your Meta App and complete App Review.',
            ];
        }
        try {
            $url = 'https://graph.facebook.com/v18.0/me/messages';
            $payload = [
                'recipient' => ['id' => $igUserId],
                'message' => ['text' => $message],
            ];
            $response = Http::withToken($this->accessToken)->timeout(30)->post($url, $payload);
            $data = $response->json();
            if ($response->successful() && empty($data['error'])) {
                return ['success' => true, 'message_id' => $data['message_id'] ?? null];
            }
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown error'];
        } catch (\Exception $e) {
            Log::error('Instagram sendMessage error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
