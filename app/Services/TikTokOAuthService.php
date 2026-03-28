<?php

namespace App\Services;

use App\Models\TikTokConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Login Kit (OAuth 2.0) — authorize URL, token exchange, refresh, user info.
 *
 * @see https://developers.tiktok.com/doc/login-kit-web
 */
class TikTokOAuthService
{
    protected string $clientKey;

    protected string $clientSecret;

    protected string $scopes;

    public function __construct()
    {
        $this->clientKey = trim((string) config('services.tiktok.client_key', ''));
        $this->clientSecret = trim((string) config('services.tiktok.client_secret', ''));
        $this->scopes = trim((string) config('services.tiktok.scopes', 'user.info.basic'));
    }

    public function isConfigured(): bool
    {
        return $this->clientKey !== '' && $this->clientSecret !== '';
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_key' => $this->clientKey,
            'scope' => $this->scopes,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query($params);
    }

    /**
     * @return array{success: true, connection: TikTokConnection}|array{error: string}
     */
    public function exchangeCodeForConnection(string $code, ?int $userId, string $redirectUri): array
    {
        $token = $this->requestToken([
            'client_key' => $this->clientKey,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);
        if (isset($token['error'])) {
            return $token;
        }

        $accessToken = $token['access_token'] ?? null;
        $openId = $token['open_id'] ?? null;
        if (! is_string($accessToken) || $accessToken === '' || ! is_string($openId) || $openId === '') {
            return ['error' => 'Invalid token response from TikTok.'];
        }

        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $refreshToken = $token['refresh_token'] ?? null;
        $refreshExpiresIn = (int) ($token['refresh_expires_in'] ?? 0);
        $scopeRaw = $token['scope'] ?? '';
        $scopes = is_string($scopeRaw)
            ? array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $scopeRaw) ?: [])))
            : [];

        $profile = $this->fetchUserProfile($accessToken);
        $displayName = $profile['display_name'] ?? null;
        $avatarUrl = $profile['avatar_url'] ?? null;
        if (is_string($avatarUrl) && strlen($avatarUrl) > 60000) {
            $avatarUrl = mb_substr($avatarUrl, 0, 60000);
        }
        $unionId = $profile['union_id'] ?? null;

        $connection = new TikTokConnection;
        $connection->user_id = $userId;
        $connection->open_id = $openId;
        $connection->union_id = is_string($unionId) ? $unionId : null;
        $connection->display_name = is_string($displayName) ? $displayName : null;
        $connection->avatar_url = is_string($avatarUrl) ? $avatarUrl : null;
        $connection->setAccessToken($accessToken);
        if (is_string($refreshToken) && $refreshToken !== '') {
            $connection->setRefreshToken($refreshToken);
        }
        $connection->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $connection->refresh_expires_at = $refreshExpiresIn > 0 ? now()->addSeconds($refreshExpiresIn) : null;
        $connection->scopes_json = $scopes;
        $connection->save();

        return ['success' => true, 'connection' => $connection];
    }

    /**
     * Refresh access token using refresh_token (Login Kit).
     *
     * @return array{success: true}|array{error: string}
     */
    public function refreshConnection(TikTokConnection $connection): array
    {
        $refresh = $connection->getRefreshToken();
        if (! $refresh) {
            return ['error' => 'No refresh token stored.'];
        }
        $token = $this->requestToken([
            'client_key' => $this->clientKey,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
        ]);
        if (isset($token['error'])) {
            return $token;
        }
        $accessToken = $token['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            return ['error' => 'Invalid refresh response from TikTok.'];
        }
        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $newRefresh = $token['refresh_token'] ?? $refresh;
        $refreshExpiresIn = (int) ($token['refresh_expires_in'] ?? 0);

        $connection->setAccessToken($accessToken);
        if (is_string($newRefresh) && $newRefresh !== '') {
            $connection->setRefreshToken($newRefresh);
        }
        $connection->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        if ($refreshExpiresIn > 0) {
            $connection->refresh_expires_at = now()->addSeconds($refreshExpiresIn);
        }
        $connection->save();

        return ['success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestToken(array $body): array
    {
        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post('https://open.tiktokapis.com/v2/oauth/token/', $body);
        } catch (\Throwable $e) {
            Log::error('TikTok token request failed', ['message' => $e->getMessage()]);

            return ['error' => 'Network error contacting TikTok.'];
        }
        $json = $response->json();
        if (! $response->successful()) {
            $desc = $json['error_description'] ?? $json['error'] ?? $response->body();

            return ['error' => is_string($desc) ? $desc : 'TikTok token error.'];
        }
        if (! is_array($json)) {
            return ['error' => 'Invalid JSON from TikTok token endpoint.'];
        }
        if (isset($json['data']) && is_array($json['data'])) {
            $json = array_merge($json, $json['data']);
        }

        return $json;
    }

    /**
     * @return array{display_name?: string, avatar_url?: string, union_id?: string}
     */
    protected function fetchUserProfile(string $accessToken): array
    {
        $fields = 'open_id,union_id,avatar_url,display_name';
        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => $fields]);
        } catch (\Throwable $e) {
            return [];
        }
        $json = $response->json();
        if (! $response->successful() || ! is_array($json)) {
            return [];
        }
        $user = $json['data']['user'] ?? null;
        if (! is_array($user)) {
            return [];
        }

        return [
            'display_name' => isset($user['display_name']) ? (string) $user['display_name'] : null,
            'avatar_url' => isset($user['avatar_url']) ? (string) $user['avatar_url'] : null,
            'union_id' => isset($user['union_id']) ? (string) $user['union_id'] : null,
        ];
    }
}
