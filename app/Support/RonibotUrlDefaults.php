<?php

namespace App\Support;

/**
 * آدرس‌های Ronibot از RONIBOT_API_URL.
 *
 * RONIBOT_API_URL می‌تواند باشد:
 * - ریشهٔ سایت: https://ronibot.com → ارسال پیام: .../api/create-message ، Partner: .../api/partner/...
 * - با /api: https://ronibot.com/api → همان نتیجه
 * - کامل: https://ronibot.com/api/create-message → همان URL برای ارسال پیام
 */
class RonibotUrlDefaults
{
    /**
     * ریشهٔ دامنه بدون پسوند /api (برای چسباندن api/partner/...).
     */
    public static function siteBaseUrl(): string
    {
        $raw = rtrim(trim((string) config('services.ronibot.url', '')), '/');
        if ($raw === '') {
            return '';
        }
        if (str_ends_with($raw, '/api/create-message')) {
            return substr($raw, 0, -strlen('/api/create-message'));
        }
        if (str_ends_with($raw, '/api')) {
            return substr($raw, 0, -4);
        }

        return $raw;
    }

    /**
     * URL نهایی endpoint ارسال پیام واتساپ (create-message).
     */
    public static function createMessageUrl(): string
    {
        $raw = rtrim(trim((string) config('services.ronibot.url', '')), '/');
        if ($raw === '') {
            return '';
        }
        if (str_ends_with($raw, '/api/create-message')) {
            return $raw;
        }

        $root = self::siteBaseUrl();

        return $root !== '' ? $root.'/api/create-message' : '';
    }

    /**
     * اصلاح مقادیر قدیمی ذخیره‌شده در DB وقتی env خالی است.
     */
    public static function normalizeCreateMessageUrl(string $url): string
    {
        $b = rtrim(trim($url), '/');
        if ($b === '') {
            return '';
        }
        if (str_ends_with($b, '/api/create-message')) {
            return $b;
        }
        if (str_ends_with($b, '/api')) {
            return $b.'/create-message';
        }
        if (str_ends_with($b, '/create-message')) {
            $without = substr($b, 0, -strlen('/create-message'));

            return $without.'/api/create-message';
        }

        return $b.'/api/create-message';
    }

    public static function webhookUrl(): string
    {
        $w = trim((string) config('services.ronibot.default_webhook_url', ''));
        if ($w !== '') {
            return $w;
        }
        $base = rtrim((string) config('app.url', ''), '/');

        return $base !== '' ? $base.'/wpwebhook' : '';
    }
}
