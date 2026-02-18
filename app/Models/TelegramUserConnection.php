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
        'auth_flow',
        'api_id_encrypted',
        'api_hash_encrypted',
        'last_used_at',
    ];

    public const AUTH_FLOW_QR = 'qr';
    public const AUTH_FLOW_PHONE_OTP = 'phone_otp';
    public const AUTH_FLOW_PHONE_2FA = 'phone_2fa';

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
     * Check if session file/dir still exists on disk.
     * If user deletes session folder manually, we should treat as disconnected.
     */
    public function hasSessionOnDisk(): bool
    {
        if (!$this->session_path) {
            return false;
        }
        $path = storage_path('app/' . $this->session_path);
        return is_dir($path) || file_exists($path);
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
        $conn = self::where('status', 'connected')->orderBy('updated_at', 'desc')->first();
        if (!$conn || !$conn->hasSessionOnDisk()) {
            if ($conn) {
                $conn->update(['status' => 'expired']);
            }
            return null;
        }
        return $conn;
    }

    /**
     * Find connection that is in phone OTP or 2FA flow for current user.
     * Used by completePhoneLogin when conn_id is missing - DB is source of truth.
     */
    public static function findForPhoneOtpFlow(?int $userId): ?self
    {
        $q = self::whereIn('auth_flow', [self::AUTH_FLOW_PHONE_OTP, self::AUTH_FLOW_PHONE_2FA])
            ->whereIn('status', ['pending', 'connected']);
        if ($userId) {
            $q->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }
        return $q->orderByDesc('updated_at')->first();
    }

    /**
     * Find connection waiting for 2FA (from QR or phone flow).
     */
    public static function findFor2faFlow(?int $userId): ?self
    {
        $q = self::whereIn('auth_flow', [self::AUTH_FLOW_QR, self::AUTH_FLOW_PHONE_2FA])
            ->whereIn('status', ['pending', 'connected']);
        if ($userId) {
            $q->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }
        return $q->orderByDesc('updated_at')->first();
    }

    /**
     * Find connection that is in QR flow for polling/2FA.
     */
    public static function findForQrFlow(?int $userId, ?int $connId = null): ?self
    {
        if ($connId) {
            $conn = self::find($connId);
            if ($conn && $conn->auth_flow === self::AUTH_FLOW_QR) {
                return $conn;
            }
            if ($conn && in_array($conn->status, ['pending', 'connected'], true)) {
                return $conn;
            }
        }
        $q = self::where('auth_flow', self::AUTH_FLOW_QR)
            ->whereIn('status', ['pending', 'connected']);
        if ($userId) {
            $q->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }
        return $q->orderByDesc('updated_at')->first();
    }
}
