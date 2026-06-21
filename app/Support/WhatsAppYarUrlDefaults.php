<?php

namespace App\Support;

/**
 * آدرس‌های پایه WhatsAppYar (OpenWA API) از env.
 */
class WhatsAppYarUrlDefaults
{
    public static function apiBaseUrl(): string
    {
        $raw = rtrim(trim((string) config('services.whatsappyar.url', '')), '/');
        if ($raw === '') {
            return 'https://api.whatsappyar.com';
        }

        if (str_ends_with($raw, '/api')) {
            return substr($raw, 0, -4);
        }

        return $raw;
    }

    public static function apiUrl(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if (! str_starts_with($path, '/api/')) {
            $path = '/api'.$path;
        }

        return self::apiBaseUrl().$path;
    }
}
