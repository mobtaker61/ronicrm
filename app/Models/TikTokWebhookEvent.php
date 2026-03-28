<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TikTok Developer Portal webhooks (no organization global scope; events may arrive without tenant context).
 */
class TikTokWebhookEvent extends Model
{
    protected $table = 'tiktok_webhook_events';

    protected $fillable = [
        'tiktok_connection_id',
        'event_type',
        'user_openid',
        'create_time',
        'content_raw',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function tiktokConnection(): BelongsTo
    {
        return $this->belongsTo(TikTokConnection::class);
    }
}
