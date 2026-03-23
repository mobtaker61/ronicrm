<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramWebhookEvent extends Model
{
    use BelongsToOrganization;

    protected $table = 'instagram_webhook_events';

    protected $fillable = [
        'organization_id',
        'instagram_connection_id',
        'event_type',
        'mid',
        'sender_id',
        'recipient_id',
        'event_timestamp',
        'payload_redacted',
    ];

    protected function casts(): array
    {
        return [
            'event_timestamp' => 'datetime',
            'payload_redacted' => 'array',
        ];
    }

    public function instagramConnection(): BelongsTo
    {
        return $this->belongsTo(InstagramConnection::class);
    }
}
