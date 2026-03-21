<?php

namespace App\Services;

use App\Models\GoogleContactsIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleContactsOAuthService
{
    public const SCOPES = 'openid https://www.googleapis.com/auth/contacts https://www.googleapis.com/auth/userinfo.email';

    public function isConfigured(): bool
    {
        $id = config('services.google_contacts.client_id');
        $secret = config('services.google_contacts.client_secret');
        $redirect = config('services.google_contacts.redirect_uri');

        return is_string($id) && $id !== ''
            && is_string($secret) && $secret !== ''
            && is_string($redirect) && $redirect !== '';
    }

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.google_contacts.client_id'),
            'redirect_uri' => config('services.google_contacts.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$params;
    }

    /**
     * @return array{success: bool, error?: string, integration?: GoogleContactsIntegration}
     */
    public function exchangeCodeAndSave(string $code, ?int $userId): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_contacts.client_id'),
            'client_secret' => config('services.google_contacts.client_secret'),
            'redirect_uri' => config('services.google_contacts.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            Log::warning('Google Contacts OAuth token exchange failed', [
                'body' => $response->body(),
            ]);

            return ['success' => false, 'error' => 'Token exchange failed: '.$response->body()];
        }

        $data = $response->json();
        $refresh = $data['refresh_token'] ?? null;
        if (empty($refresh)) {
            return [
                'success' => false,
                'error' => 'No refresh token received. Try revoking app access in Google Account and connect again with prompt=consent.',
            ];
        }

        $access = $data['access_token'] ?? '';
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        GoogleContactsIntegration::query()->delete();

        $email = $this->fetchPrimaryEmail($access);

        $integration = GoogleContactsIntegration::create([
            'refresh_token' => $refresh,
            'access_token' => $access,
            'access_token_expires_at' => now()->addSeconds(max(60, $expiresIn - 60)),
            'account_email' => $email,
            'connected_by' => $userId,
        ]);

        return ['success' => true, 'integration' => $integration];
    }

    protected function fetchPrimaryEmail(string $accessToken): ?string
    {
        $r = Http::withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if ($r->successful()) {
            $email = $r->json('email');
            if (is_string($email) && $email !== '') {
                return $email;
            }
        }

        $r2 = Http::withToken($accessToken)
            ->get('https://people.googleapis.com/v1/people/me', [
                'personFields' => 'emailAddresses',
            ]);

        if (! $r2->successful()) {
            return null;
        }

        $emails = $r2->json('emailAddresses') ?? [];

        return $emails[0]['value'] ?? null;
    }

    /**
     * Valid access token (refreshes if needed).
     */
    public function getValidAccessToken(): ?string
    {
        $integration = GoogleContactsIntegration::getSingleton();
        if (! $integration || empty($integration->refresh_token)) {
            return null;
        }

        if (! $integration->accessTokenExpired()) {
            return $integration->access_token;
        }

        return $this->refreshAccessToken($integration);
    }

    protected function refreshAccessToken(GoogleContactsIntegration $integration): ?string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_contacts.client_id'),
            'client_secret' => config('services.google_contacts.client_secret'),
            'refresh_token' => $integration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::warning('Google Contacts refresh token failed', ['body' => $response->body()]);

            return null;
        }

        $data = $response->json();
        $access = $data['access_token'] ?? '';
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        $integration->update([
            'access_token' => $access,
            'access_token_expires_at' => now()->addSeconds(max(60, $expiresIn - 60)),
        ]);

        return $access;
    }
}
