<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $table = 'telegram_messages';

    protected $fillable = [
        'telegram_message_id',
        'chat_id',
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

    public function getSenderNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }
        return $this->from_username ?? $this->chat_id;
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now(), 'status' => 'read']);
        }
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeForChat($query, string $chatId)
    {
        return $query->where('chat_id', $chatId);
    }
}
