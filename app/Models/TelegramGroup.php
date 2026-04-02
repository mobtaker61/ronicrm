<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramGroup extends Model
{
    use BelongsToOrganization;

    protected $table = 'telegram_groups';

    protected $fillable = [
        'organization_id',
        'channel',
        'telegram_user_connection_id',
        'telegram_group_id',
        'title',
        'type',
        'member_count',
        'public_username',
        'public_link',
        'description',
        'telegram_group_category_id',
        'language',
        'can_post',
        'last_error',
        'last_crawled_message_id',
        'last_synced_at',
        'is_active',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TelegramGroupCategory::class, 'telegram_group_category_id');
    }

    protected function casts(): array
    {
        return [
            'can_post' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TelegramUserConnection::class, 'telegram_user_connection_id');
    }

    public static function findOrCreateForConnection(int $connectionId, string $groupId, ?string $title = null, ?string $type = null): self
    {
        $g = self::firstOrCreate(
            [
                'telegram_user_connection_id' => $connectionId,
                'telegram_group_id' => $groupId,
                'channel' => 'telegram',
            ],
            [
                'title' => $title,
                'type' => $type,
                'can_post' => true,
                'is_active' => true,
                'last_synced_at' => now(),
            ]
        );
        $g->update([
            'title' => $title ?? $g->title,
            'type' => $type ?? $g->type,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        return $g;
    }

    public static function upsertFromTelegramPayload(int $connectionId, array $payload): self
    {
        $groupId = (string) ($payload['id'] ?? '');
        $g = self::findOrCreateForConnection(
            $connectionId,
            $groupId,
            $payload['title'] ?? null,
            $payload['type'] ?? null
        );

        $username = $payload['public_username'] ?? null;
        $username = is_string($username) ? ltrim(trim($username), '@') : null;

        $g->update([
            'member_count' => isset($payload['member_count']) ? (int) $payload['member_count'] : null,
            'public_username' => $username ?: null,
            'public_link' => $payload['public_link'] ?? ($username ? ('https://t.me/'.$username) : null),
            'description' => $payload['description'] ?? null,
        ]);

        return $g;
    }

    public function markCannotPost(string $error): void
    {
        $this->update(['can_post' => false, 'last_error' => $error]);
    }

    public function markCanPost(): void
    {
        $this->update(['can_post' => true, 'last_error' => null]);
    }
}
