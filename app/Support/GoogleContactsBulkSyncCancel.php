<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * درخواست توقف همگام‌سازی انبوه CRM → Google (حلقه در سرویس این فلگ را می‌خواند).
 */
class GoogleContactsBulkSyncCancel
{
    public const CACHE_KEY = 'google_contacts_bulk_sync_cancel_v1';

    public static function request(): void
    {
        Cache::put(self::CACHE_KEY, true, 7200);
    }

    public static function requested(): bool
    {
        return (bool) Cache::get(self::CACHE_KEY);
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
