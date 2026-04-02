<?php

namespace App\Services;

use App\Support\RonibotUrlDefaults;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * فراخوانی Partner API روی RoniBot (ثبت‌نام، دیوایس، QR، وضعیت، اپ).
 */
class RoniBotPartnerApiService
{
    public function __construct(
        protected I18nService $i18n
    ) {}

    protected function baseUrl(): string
    {
        return RonibotUrlDefaults::siteBaseUrl();
    }

    protected function serverKey(): string
    {
        return trim((string) config('services.ronibot.partner_server_key', ''));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->serverKey() !== '';
    }

    /**
     * پیام خطا برای UI وقتی env/config خوانده نشده (مثلاً بعد از تغییر .env بدون config:clear).
     */
    public function configurationErrorMessage(): string
    {
        $urlEmpty = $this->baseUrl() === '';
        $keyEmpty = $this->serverKey() === '';

        if ($urlEmpty && $keyEmpty) {
            return $this->i18n->translate('settings.ronibot_partner_config_error_both');
        }
        if ($urlEmpty) {
            return $this->i18n->translate('settings.ronibot_partner_config_error_url');
        }
        if ($keyEmpty) {
            return $this->i18n->translate('settings.ronibot_partner_config_error_key');
        }

        return $this->i18n->translate('settings.ronibot_partner_config_fallback');
    }

    /**
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException($this->i18n->translate('settings.ronibot_partner_error_api_internal_not_configured'));
        }

        $url = $this->baseUrl().'/'.ltrim($path, '/');

        /** @var Response $response */
        $response = Http::timeout(120)
            ->withHeaders([
                'X-Server-Key' => $this->serverKey(),
                'Accept' => 'application/json',
            ])
            ->post($url, $body);

        $json = is_array($response->json()) ? $response->json() : [];

        if ($response->status() === 409) {
            $err = $json['error'] ?? '';
            if ($err === 'duplicate_phone') {
                throw new \RuntimeException($this->i18n->translate('settings.ronibot_partner_error_duplicate_phone'));
            }
            $message = $json['message'] ?? (is_string($err) ? $err : null) ?? $response->body();
            Log::warning('RoniBot Partner API conflict', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(is_string($message) ? $message : $this->i18n->translate('settings.ronibot_partner_error_conflict'));
        }

        if (! $response->successful()) {
            $message = $json['message'] ?? $json['error'] ?? $response->body();
            Log::warning('RoniBot Partner API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(is_string($message) ? $message : $this->i18n->translate('settings.ronibot_partner_error_api_failed'));
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function register(string $name, string $email, string $password, string $phone, ?int $planId = null): array
    {
        $payload = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
        ];
        if ($planId !== null && $planId > 0) {
            $payload['plan_id'] = $planId;
        }

        return $this->post('api/partner/register', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDevice(string $authkey, string $name, string $webhookUrl, ?string $phone = null): array
    {
        $payload = [
            'authkey' => $authkey,
            'name' => $name,
            'webhook_url' => $webhookUrl,
        ];
        if ($phone !== null && $phone !== '') {
            $payload['phone'] = $phone;
        }

        return $this->post('api/partner/device', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function deviceQr(string $authkey, string $deviceUuid): array
    {
        return $this->post('api/partner/device/qr', [
            'authkey' => $authkey,
            'device_uuid' => $deviceUuid,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deviceStatus(string $authkey, string $deviceUuid): array
    {
        return $this->post('api/partner/device/status', [
            'authkey' => $authkey,
            'device_uuid' => $deviceUuid,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createApp(string $authkey, int $deviceId, string $title, string $website): array
    {
        return $this->post('api/partner/app', [
            'authkey' => $authkey,
            'device_id' => $deviceId,
            'title' => $title,
            'website' => $website,
        ]);
    }
}
