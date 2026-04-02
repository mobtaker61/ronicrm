<?php

namespace App\Services;

use App\Support\OrganizationContext;
use danog\MadelineProto\EventHandler\Media;
use Illuminate\Support\Facades\Log;

/**
 * وقتی getFile (بات) URL نمی‌دهد، رسانهٔ MTProto را روی دیسک عمومی ذخیره می‌کند تا اینباکس نمایش دهد.
 */
class TelegramMediaStorageService
{
    /**
     * @return string|null مسیر نسبی نسبت به ریشهٔ دیسک public (مثل telegram-media/1/tg_xxx.jpg) برای ذخیره در media_url
     */
    public static function downloadMediaObjectToPublicDisk(Media $media): ?string
    {
        $orgId = OrganizationContext::getOrganizationId();
        if ($orgId === null || $orgId === 0) {
            $orgId = 0;
        }

        $ext = $media->fileExt;
        if ($ext === '') {
            $ext = '.bin';
        }
        if ($ext !== '' && $ext[0] !== '.') {
            $ext = '.'.$ext;
        }

        $filename = 'tg_'.str_replace('.', '', uniqid('', true)).$ext;
        $relative = 'telegram-media/'.$orgId.'/'.$filename;
        $full = storage_path('app/public/'.$relative);
        $dir = dirname($full);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                Log::warning('TelegramMediaStorageService: cannot create directory', ['dir' => $dir]);

                return null;
            }
        }

        try {
            $media->downloadToFile($full);
        } catch (\Throwable $e) {
            Log::warning('TelegramMediaStorageService: downloadToFile failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_file($full) || filesize($full) === 0) {
            Log::warning('TelegramMediaStorageService: empty or missing file after download', ['path' => $full]);

            return null;
        }

        return $relative;
    }
}
