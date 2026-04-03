<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToOrganization;
    use Concerns\ResolvesInboxMediaUrl;

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
        if (! $this->read_at) {
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

    /**
     * پیام‌های یک گفت‌وگو با «مخاطب» (شمارهٔ طرف مقابل).
     * ورودی: from_phone = مخاطب. خروجی (استاندارد فعلی): to_phone = مخاطب، from_phone = خط کسب‌وکار.
     * از ترکیب سادهٔ (from_phone = X or to_phone = X) استفاده نکنید — همان پیام را دو بار می‌گیرد یا خط را به‌عنوان مخاطب نشان می‌دهد.
     */
    public function scopeConversationWithPeer(Builder $query, string $phone): Builder
    {
        $phone = trim($phone);

        return $query->where(function ($q) use ($phone) {
            $q->where(function ($q2) use ($phone) {
                $q2->where('direction', 'incoming')->where('from_phone', $phone);
            })->orWhere(function ($q2) use ($phone) {
                $q2->where('direction', 'outgoing')->where('to_phone', $phone);
            });
        });
    }
}
