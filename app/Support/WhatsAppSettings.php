<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Setting;
use App\Services\WhatsAppYarApiService;

class WhatsAppSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'session_id' => '',
            'session_name' => '',
            'line_phone' => '',
            'webhook_id' => '',
            'webhook_secret' => '',
            'webhook_pending' => false,
            'webhook_error' => '',
            'status' => '',
            'api_key' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(?int $organizationId = null): array
    {
        $defaults = self::defaults();
        $orgId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $orgId) {
            return $defaults;
        }

        $stored = Setting::getForOrganization('whatsapp', $defaults, $orgId);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function set(array $data, ?int $organizationId = null): void
    {
        $orgId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $orgId) {
            return;
        }

        Setting::setForOrganization('whatsapp', array_merge(self::get($orgId), $data), $orgId);
    }

    public static function webhookUrl(?Organization $organization = null): string
    {
        $organization = $organization ?? Organization::query()->find(OrganizationContext::getOrganizationId());
        $fromEnv = trim((string) config('services.whatsappyar.default_webhook_url', ''));

        if ($fromEnv !== '') {
            $base = rtrim($fromEnv, '/');
            if ($organization && filled($organization->slug) && ! str_contains($base, '/whatsapp-webhook')) {
                return $base.'/whatsapp-webhook/'.$organization->slug;
            }

            return $base;
        }

        if ($organization && filled($organization->slug)) {
            return rtrim((string) config('app.url', ''), '/').'/whatsapp-webhook/'.$organization->slug;
        }

        return rtrim((string) config('app.url', ''), '/').'/whatsapp-webhook';
    }

    public static function isWebhookUrlPubliclyReachable(?string $url = null): bool
    {
        $url = $url ?? self::webhookUrl();
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $blockedHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array($host, $blockedHosts, true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    public static function sessionNamePrefixForOrganization(Organization $organization): string
    {
        $slug = strtolower((string) $organization->slug);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? 'org';
        $slug = trim($slug, '-') ?: 'org';

        return 'ronicrm-'.$slug.'-'.$organization->id;
    }

    public static function sessionNameForOrganization(Organization $organization, ?string $suffix = null): string
    {
        $base = self::sessionNamePrefixForOrganization($organization);
        $name = ($suffix !== null && $suffix !== '') ? $base.'-'.$suffix : $base;

        return substr($name, 0, 50);
    }

    public static function resolveApiKey(?int $organizationId = null): string
    {
        $settings = self::get($organizationId);
        $fromOrg = trim((string) ($settings['api_key'] ?? ''));
        if ($fromOrg !== '') {
            return $fromOrg;
        }

        return trim((string) config('services.whatsappyar.api_key', ''));
    }

    public static function isConfigured(?int $organizationId = null): bool
    {
        return self::resolveApiKey($organizationId) !== '';
    }

    public static function isReady(?int $organizationId = null): bool
    {
        $settings = self::get($organizationId);

        return ($settings['enabled'] ?? false)
            && filled($settings['session_id'] ?? null)
            && WhatsAppYarApiService::isSessionConnectedStatus((string) ($settings['status'] ?? ''));
    }
}
