<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokMessage extends Model
{
    use BelongsToOrganization;
    use Concerns\ResolvesInboxMediaUrl;

    protected $table = 'tiktok_messages';

    protected $fillable = [
        'organization_id',
        'tiktok_connection_id',
        'tiktok_message_id',
        'conversation_id',
        'tiktok_open_id',
        'from_display_name',
        'message',
        'message_type',
        'media_url',
        'media_mime_type',
        'customer_id',
        'direction',
        'status',
        'read_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tiktokConnection(): BelongsTo
    {
        return $this->belongsTo(TikTokConnection::class);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeForOpenId($query, string $openId)
    {
        return $query->where('tiktok_open_id', $openId);
    }
}
