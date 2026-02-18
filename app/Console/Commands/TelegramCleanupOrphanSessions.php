<?php

namespace App\Console\Commands;

use App\Models\TelegramUserConnection;
use Illuminate\Console\Command;

class TelegramCleanupOrphanSessions extends Command
{
    protected $signature = 'telegram:cleanup-sessions';

    protected $description = 'Delete session folders for expired Telegram connections and orphan sessions on disk';

    public function handle(): int
    {
        $baseDir = storage_path('app/telegram-user-sessions');
        if (!is_dir($baseDir)) {
            $this->info('No telegram-user-sessions directory found.');
            return self::SUCCESS;
        }

        $expired = TelegramUserConnection::where('status', 'expired')->get();
        foreach ($expired as $conn) {
            if ($conn->session_path) {
                $path = storage_path('app/' . $conn->session_path);
                if (file_exists($path) || is_dir($path)) {
                    $this->deletePath($path);
                    $this->line("Deleted: {$conn->session_path}");
                }
            }
        }

        $validPaths = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
            ->whereNotNull('session_path')
            ->pluck('session_path')
            ->map(fn ($p) => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, storage_path('app/' . $p)))
            ->all();

        $entries = scandir($baseDir);
        $deleted = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $entry;
            $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
            if (!in_array($normalized, $validPaths, true) && (is_dir($fullPath) || file_exists($fullPath))) {
                $this->deletePath($fullPath);
                $this->line("Deleted orphan: $entry");
                $deleted++;
            }
        }

        $this->info('Cleanup complete.');
        return self::SUCCESS;
    }

    protected function deletePath(string $path): void
    {
        try {
            if (is_dir($path)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        } catch (\Throwable $e) {
            $this->warn("Could not delete $path: " . $e->getMessage());
        }
    }
}
