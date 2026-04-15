<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class GoogleContactsPhotoBulkCancel
{
    public const CACHE_KEY = 'google_contacts_photo_bulk_cancel_v1';

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
