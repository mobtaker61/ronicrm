<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleContactsIntegration extends Model
{
    protected $fillable = [
        'refresh_token',
        'access_token',
        'access_token_expires_at',
        'account_email',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'access_token_expires_at' => 'datetime',
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public static function getSingleton(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function accessTokenExpired(): bool
    {
        if (empty($this->access_token) || ! $this->access_token_expires_at) {
            return true;
        }

        return $this->access_token_expires_at->isPast();
    }
}
