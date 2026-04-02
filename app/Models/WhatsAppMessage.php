<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToOrganization;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'organization_id',
        'message_id',
        'from_phone',
        'to_phone',
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

    /**
     * Full URL for inbox/UI: public/uploads (webhook) vs storage (outgoing uploads).
     */
    public function resolvedMediaUrl(): ?string
    {
        $url = $this->media_url;
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, 'uploads/')) {
            return asset($url);
        }

        return asset('storage/'.ltrim($url, '/'));
    }

    /**
     * Get the display name for the sender
     */
    public function getSenderNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }
        return $this->from_phone;
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now(), 'status' => 'read']);
        }
    }

    /**
     * Scope for unread messages
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for incoming messages
     */
    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    /**
     * Scope for messages from a specific phone
     */
    public function scopeFromPhone($query, string $phone)
    {
        return $query->where('from_phone', $phone);
    }
}
