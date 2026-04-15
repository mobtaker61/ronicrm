<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * وضعیت به‌روزرسانی انبوه تصویر از Google برای مشتریان دارای google_people_resource_name.
 */
class GoogleContactsPhotoBulkState
{
    public const CACHE_KEY = 'google_contacts_photo_bulk_v1';

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

    public static function startRunning(int $total): void
    {
        $prev = self::get() ?? [];
        self::put([
            'status' => 'running',
            'total' => $total,
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'started_at' => $prev['started_at'] ?? now()->toIso8601String(),
            'finished_at' => null,
            'errors' => [],
            'last_tick_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<int, string>  $errorsTail
     */
    public static function tick(
        int $processed,
        int $total,
        int $updated,
        int $skipped,
        int $failed,
        array $errorsTail
    ): void {
        $cur = self::get() ?? [];
        $cur['status'] = 'running';
        $cur['total'] = $total;
        $cur['processed'] = $processed;
        $cur['updated'] = $updated;
        $cur['skipped'] = $skipped;
        $cur['failed'] = $failed;
        $cur['errors'] = $errorsTail;
        $cur['last_tick_at'] = now()->toIso8601String();
        self::put($cur);
    }

    /**
     * @param  array{
     *     updated: int,
     *     skipped: int,
     *     failed: int,
     *     errors: array<int, string>,
     *     total: int
     * }  $result
     */
    public static function markDone(array $result): void
    {
        $prev = self::get() ?? [];
        self::put([
            'status' => 'done',
            'total' => $result['total'],
            'processed' => $result['total'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
            'started_at' => $prev['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'errors' => array_slice($result['errors'], 0, 50),
            'last_tick_at' => now()->toIso8601String(),
        ]);
    }

    public static function markCancelled(int $processed, int $total, int $updated, int $skipped, int $failed, array $errorsTail): void
    {
        $prev = self::get() ?? [];
        self::put([
            'status' => 'cancelled',
            'total' => $total,
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'started_at' => $prev['started_at'] ?? null,
            'finished_at' => now()->toIso8601String(),
            'message' => 'Stopped by user.',
            'errors' => $errorsTail,
            'last_tick_at' => now()->toIso8601String(),
        ]);
    }

    public static function markFailed(string $message): void
    {
        self::put([
            'status' => 'failed',
            'total' => 0,
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'started_at' => null,
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
            'errors' => [$message],
            'last_tick_at' => now()->toIso8601String(),
        ]);
    }
}
