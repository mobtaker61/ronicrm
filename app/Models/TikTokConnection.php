<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class TikTokConnection extends Model
{
    use BelongsToOrganization;

    protected $table = 'tiktok_connections';

    protected $fillable = [
        'organization_id',
        'user_id',
        'open_id',
        'union_id',
        'display_name',
        'avatar_url',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'token_expires_at',
        'refresh_expires_at',
        'scopes_json',
        'webhook_verified_at',
        'last_webhook_event_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'webhook_verified_at' => 'datetime',
            'last_webhook_event_at' => 'datetime',
            'scopes_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TikTokMessage::class, 'tiktok_connection_id');
    }

    public function getAccessToken(): ?string
    {
        if (empty($this->access_token_encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->access_token_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setAccessToken(?string $plainToken): void
    {
        $this->attributes['access_token_encrypted'] = $plainToken ? Crypt::encryptString($plainToken) : null;
    }

    public function getRefreshToken(): ?string
    {
        if (empty($this->refresh_token_encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->refresh_token_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setRefreshToken(?string $plainToken): void
    {
        $this->attributes['refresh_token_encrypted'] = $plainToken ? Crypt::encryptString($plainToken) : null;
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public static function getActive(?int $organizationId = null): ?self
    {
        $query = self::query()->orderBy('updated_at', 'desc');
        if ($organizationId) {
            $query->forOrganization($organizationId);
        }

        return $query->first();
    }
}
