<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class TelegramUserConnection extends Model
{
    protected $table = 'telegram_user_connections';

    protected $fillable = [
        'user_id',
        'phone',
        'telegram_username',
        'session_path',
        'status',
        'api_id_encrypted',
        'api_hash_encrypted',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getApiId(): ?string
    {
        if (empty($this->api_id_encrypted)) {
            return config('services.telegram.api_id');
        }
        try {
            return Crypt::decryptString($this->api_id_encrypted);
        } catch (\Throwable) {
            return config('services.telegram.api_id');
        }
    }

    public function setApiId(?string $value): void
    {
        $this->attributes['api_id_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiHash(): ?string
    {
        if (empty($this->api_hash_encrypted)) {
            return config('services.telegram.api_hash');
        }
        try {
            return Crypt::decryptString($this->api_hash_encrypted);
        } catch (\Throwable) {
            return config('services.telegram.api_hash');
        }
    }

    public function setApiHash(?string $value): void
    {
        $this->attributes['api_hash_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSessionPath(): string
    {
        if ($this->session_path) {
            return storage_path('app/' . $this->session_path);
        }
        $dir = 'telegram-user-sessions';
        Storage::disk('local')->makeDirectory($dir);
        $path = $dir . '/session_' . $this->id . '.madeline';
        $this->update(['session_path' => $path]);
        return storage_path('app/' . $path);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Delete session files from disk and set status to pending for re-login.
     * Use when lightstate/session is corrupted (e.g. "Could not read the lightstate file").
     */
    public function resetSessionFiles(): bool
    {
        $path = $this->getSessionPath();
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
        $this->update(['status' => 'pending']);
        return true;
    }

    public static function getActive(): ?self
    {
        return self::where('status', 'connected')->orderBy('updated_at', 'desc')->first();
    }
}
