<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * وضعیت واردات انبوه از Google Contacts به CRM (برای polling در UI).
 */
class GoogleContactsImportBulkState
{
    public const CACHE_KEY = 'google_contacts_import_bulk_v1';

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

    public static function startRunning(): void
    {
        $prev = self::get() ?? [];
        self::put([
            'status' => 'running',
            'total' => (int) ($prev['total'] ?? 0),
            'processed' => 0,
            'imported' => 0,
            'skipped_duplicate' => 0,
            'skipped_empty' => 0,
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
        int $imported,
        int $skippedDuplicate,
        int $skippedEmpty,
        int $failed,
        array $errorsTail
    ): void {
        $cur = self::get() ?? [];
        $cur['status'] = 'running';
        $cur['total'] = $total;
        $cur['processed'] = $processed;
        $cur['imported'] = $imported;
        $cur['skipped_duplicate'] = $skippedDuplicate;
        $cur['skipped_empty'] = $skippedEmpty;
        $cur['failed'] = $failed;
        $cur['errors'] = $errorsTail;
        $cur['last_tick_at'] = now()->toIso8601String();
        self::put($cur);
    }

    /**
     * @param  array{
     *     imported: int,
     *     skipped_duplicate: int,
     *     skipped_empty: int,
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
            'imported' => $result['imported'],
            'skipped_duplicate' => $result['skipped_duplicate'],
            'skipped_empty' => $result['skipped_empty'],
            'failed' => $result['failed'],
            'started_at' => $prev['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'errors' => array_slice($result['errors'], 0, 50),
            'last_tick_at' => now()->toIso8601String(),
        ]);
    }

    public static function markFailed(string $message): void
    {
        self::put([
            'status' => 'failed',
            'total' => 0,
            'processed' => 0,
            'imported' => 0,
            'skipped_duplicate' => 0,
            'skipped_empty' => 0,
            'failed' => 0,
            'started_at' => null,
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
            'errors' => [$message],
        ]);
    }
}
