<?php

namespace App\Services;

use App\Models\InstagramConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Instagram API (Business Login + Messaging).
 * Uses Instagram App ID/Secret from env; no Facebook Page required for Instagram Login flow.
 */
class MetaInstagramService
{
    protected string $appId;
    protected string $appSecret;
    protected string $redirectUri;
    protected string $graphVersion;

    public function __construct()
    {
        $this->appId = config('services.meta_instagram.client_id', '');
        $this->appSecret = config('services.meta_instagram.client_secret', '');
        $this->redirectUri = config('services.meta_instagram.redirect_uri', '');
        $this->graphVersion = config('services.meta_instagram.graph_version', 'v24.0');
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->appSecret !== '';
    }

    /**
     * Build Instagram OAuth authorization URL (Business Login).
     */
    public function getAuthorizationUrl(string $state, ?string $redirectUri = null): string
    {
        $effectiveRedirectUri = $this->resolveRedirectUri($redirectUri);
        $params = http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $effectiveRedirectUri,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_manage_messages',
            'state' => $state,
        ]);
        return 'https://www.instagram.com/oauth/authorize?' . $params;
    }

    /**
     * Exchange authorization code for short-lived token, then long-lived token and profile.
     */
    public function exchangeCodeForConnection(string $code, ?int $userId = null, ?string $redirectUri = null): array
    {
        $effectiveRedirectUri = $this->resolveRedirectUri($redirectUri);
        $shortLived = $this->exchangeCodeForShortLivedToken($code, $effectiveRedirectUri);
        if (isset($shortLived['error'])) {
            return $shortLived;
        }
        $longLived = $this->exchangeForLongLivedToken($shortLived['access_token']);
        if (isset($longLived['error'])) {
            return $longLived;
        }
        $igUserId = $shortLived['user_id'] ?? null;
        $permissions = $shortLived['permissions'] ?? [];
        $profile = $this->getProfile($longLived['access_token']);
        if (isset($profile['error']) && !isset($profile['username'])) {
            $profile = ['username' => null, 'profile_picture_url' => null];
        }
        $expiresIn = (int) ($longLived['expires_in'] ?? 5183944);
        $connection = new InstagramConnection();
        $connection->user_id = $userId;
        $connection->ig_business_account_id = (string) $igUserId;
        $connection->ig_username = $profile['username'] ?? null;
        $connection->ig_profile_pic_url = $profile['profile_picture_url'] ?? null;
        $connection->page_id = null;
        $connection->setAccessToken($longLived['access_token']);
        $connection->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $connection->scopes_json = is_array($permissions)
            ? $permissions
            : array_map('trim', explode(',', (string) $permissions));
        $connection->save();
        return ['success' => true, 'connection' => $connection];
    }

    protected function exchangeCodeForShortLivedToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        $data = $response->json();
        if (!$response->successful()) {
            Log::channel('instagram')->warning('Instagram OAuth token exchange failed', [
                'response' => $data,
                'status' => $response->status(),
            ]);
            return ['error' => $data['error_message'] ?? $data['error'] ?? 'Token exchange failed'];
        }
        if (isset($data['data'][0])) {
            $data = $data['data'][0];
        }
        return $data;
    }

    protected function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $url = 'https://graph.instagram.com/access_token?' . http_build_query([
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->appSecret,
            'access_token' => $shortLivedToken,
        ]);
        $response = Http::get($url);
        $data = $response->json();
        if (!$response->successful()) {
            Log::channel('instagram')->warning('Instagram long-lived token exchange failed', ['response' => $data]);
            return ['error' => $data['error']['message'] ?? 'Long-lived token exchange failed'];
        }
        return $data;
    }

    /**
     * Get another user's profile by Instagram-scoped ID (e.g. sender from webhook).
     * Requires user to have messaged first (consent). Returns username, name, profile_pic.
     */
    public function getUserProfile(InstagramConnection $connection, string $igUserId): array
    {
        $token = $connection->getAccessToken();
        if (!$token) {
            return ['error' => 'No token'];
        }
        $url = 'https://graph.instagram.com/' . $this->graphVersion . '/' . $igUserId . '?' . http_build_query([
            'fields' => 'username,name,profile_pic',
            'access_token' => $token,
        ]);
        $response = Http::get($url);
        $data = $response->json();
        if (!$response->successful()) {
            return ['error' => $data['error']['message'] ?? 'Profile fetch failed'];
        }
        return $data;
    }

    /**
     * GET graph.instagram.com/me with token.
     */
    public function getProfile(string $accessToken): array
    {
        $url = 'https://graph.instagram.com/me?' . http_build_query([
            'fields' => 'username,profile_picture_url,account_type',
            'access_token' => $accessToken,
        ]);
        $response = Http::get($url);
        $data = $response->json();
        if (!$response->successful()) {
            return ['error' => $data['error']['message'] ?? 'Profile fetch failed'];
        }
        return $data;
    }

    /**
     * Refresh long-lived token (call before expiry).
     */
    public function refreshToken(InstagramConnection $connection): bool
    {
        $token = $connection->getAccessToken();
        if (!$token) {
            return false;
        }
        $url = 'https://graph.instagram.com/refresh_access_token?' . http_build_query([
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]);
        $response = Http::get($url);
        $data = $response->json();
        if (!$response->successful()) {
            Log::channel('instagram')->warning('Instagram token refresh failed', ['connection_id' => $connection->id]);
            return false;
        }
        $connection->setAccessToken($data['access_token'] ?? null);
        $expiresIn = (int) ($data['expires_in'] ?? 5183944);
        $connection->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $connection->save();
        return true;
    }

    /**
     * Send message (reply) to an Instagram user. Only valid for user-initiated conversations.
     */
    public function sendMessage(InstagramConnection $connection, string $recipientId, string $text, ?string $fileUrl = null): array
    {
        $token = $connection->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'No access token'];
        }
        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $text],
        ];
        $url = "https://graph.instagram.com/{$this->graphVersion}/me/messages";
        $response = Http::withToken($token)->timeout(30)->post($url, $payload);
        $data = $response->json();
        if ($response->successful() && empty($data['error'])) {
            Log::channel('instagram')->info('Instagram message sent', [
                'connection_id' => $connection->id,
                'recipient_id' => $recipientId,
                'message_id' => $data['message_id'] ?? null,
            ]);
            return ['success' => true, 'message_id' => $data['message_id'] ?? null];
        }
        $errMsg = $data['error']['message'] ?? $data['error_message'] ?? 'Unknown error';
        $errMsg = $this->normalizeSendErrorMessage($errMsg);
        Log::channel('instagram')->warning('Instagram send failed', [
            'connection_id' => $connection->id,
            'recipient_id' => $recipientId,
            'error' => $errMsg,
        ]);
        return ['success' => false, 'error' => $errMsg];
    }

    protected function resolveRedirectUri(?string $redirectUri = null): string
    {
        $resolved = trim((string) ($redirectUri ?? $this->redirectUri));
        if ($resolved !== '') {
            return $resolved;
        }

        return rtrim((string) config('app.url', ''), '/') . '/settings/instagram/callback';
    }

    protected function normalizeSendErrorMessage(string $error): string
    {
        $lowered = mb_strtolower($error, 'UTF-8');
        if (
            str_contains($lowered, 'допустимого окна') ||
            str_contains($lowered, 'outside of allowed window') ||
            str_contains($lowered, 'outside the allowed window')
        ) {
            return 'ارسال خارج از بازه مجاز اینستاگرام است. فقط تا ۲۴ ساعت بعد از آخرین پیام کاربر می‌توانید پاسخ دهید.';
        }

        return $error;
    }
}
