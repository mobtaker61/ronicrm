<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * تبدیل file_id بات تلگرام به URL قابل نمایش در مرورگر (getFile).
 */
class TelegramBotFileUrlService
{
    public static function urlForFileId(?string $fileId): ?string
    {
        if ($fileId === null || $fileId === '') {
            return null;
        }

        try {
            $settings = Setting::getScoped('telegram', []);
            $token = $settings['bot_token'] ?? '';
            if ($token === '') {
                return null;
            }

            $response = Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getFile", [
                'file_id' => $fileId,
            ]);
            $data = $response->json();
            if (! ($data['ok'] ?? false)) {
                Log::debug('Telegram getFile not ok', [
                    'file_id' => $fileId,
                    'description' => $data['description'] ?? null,
                ]);

                return null;
            }
            $path = $data['result']['file_path'] ?? null;
            if ($path === null || $path === '') {
                return null;
            }

            return "https://api.telegram.org/file/bot{$token}/{$path}";
        } catch (\Throwable $e) {
            Log::warning('Telegram getFile failed: '.$e->getMessage());

            return null;
        }
    }
}
