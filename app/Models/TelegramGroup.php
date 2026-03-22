<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramGroup extends Model
{
    protected $table = 'telegram_groups';

    protected $fillable = [
        'telegram_user_connection_id',
        'telegram_group_id',
        'title',
        'type',
        'telegram_group_category_id',
        'language',
        'can_post',
        'last_error',
        'last_crawled_message_id',
        'last_synced_at',
    ];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TelegramGroupCategory::class, 'telegram_group_category_id');
    }

    protected function casts(): array
    {
        return [
            'can_post' => 'boolean',
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
            ],
            [
                'title' => $title,
                'type' => $type,
                'can_post' => true,
                'last_synced_at' => now(),
            ]
        );
        if ($g->wasRecentlyCreated === false) {
            $g->update([
                'title' => $title ?? $g->title,
                'type' => $type ?? $g->type,
                'last_synced_at' => now(),
            ]);
        }
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
