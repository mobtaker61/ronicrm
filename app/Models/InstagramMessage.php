<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramMessage extends Model
{
    use BelongsToOrganization;
    use Concerns\ResolvesInboxMediaUrl;

    protected $table = 'instagram_messages';

    protected $fillable = [
        'organization_id',
        'instagram_connection_id',
        'instagram_message_id',
        'ig_user_id',
        'from_username',
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

    public function instagramConnection(): BelongsTo
    {
        return $this->belongsTo(InstagramConnection::class);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeForIgUser($query, string $igUserId)
    {
        return $query->where('ig_user_id', $igUserId);
    }
}
