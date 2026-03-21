<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * وضعیت همگام‌سازی انبوه Google Contacts (برای polling در UI).
 */
class GoogleContactsBulkSyncState
{
    public const CACHE_KEY = 'google_contacts_bulk_sync_v1';

    public const TTL_SECONDS = 7200;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function put(array $data): void
    {
        Cache::put(self::CACHE_KEY, $data, self::TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $v = Cache::get(self::CACHE_KEY);

        return is_array($v) ? $v : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function startRunning(int $total): void
    {
        self::put([
            'status' => 'running',
            'total' => $total,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'errors' => [],
        ]);
    }

    /**
     * @param  array<int, string>  $errorsTail
     */
    public static function tick(int $processed, int $total, int $success, int $failed, array $errorsTail): void
    {
        $cur = self::get() ?? [];
        $cur['status'] = 'running';
        $cur['total'] = $total;
        $cur['processed'] = $processed;
        $cur['success'] = $success;
        $cur['failed'] = $failed;
        $cur['errors'] = $errorsTail;
        self::put($cur);
    }

    /**
     * @param  array{success: int, failed: int, errors: array<int, string>, total: int}  $result
     */
    public static function markDone(array $result): void
    {
        $prev = self::get() ?? [];
        self::put([
            'status' => 'done',
            'total' => $result['total'],
            'processed' => $result['total'],
            'success' => $result['success'],
            'failed' => $result['failed'],
            'started_at' => $prev['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'errors' => array_slice($result['errors'], 0, 50),
        ]);
    }

    public static function markFailed(string $message): void
    {
        self::put([
            'status' => 'failed',
            'total' => 0,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'started_at' => null,
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
            'errors' => [$message],
        ]);
    }
}
