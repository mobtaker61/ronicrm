<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramScheduledSendRun extends Model
{
    protected $fillable = [
        'telegram_scheduled_send_id',
        'run_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TelegramScheduledSend::class, 'telegram_scheduled_send_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TelegramScheduledSendItem::class, 'telegram_scheduled_send_run_id');
    }

    public function pendingItems(): HasMany
    {
        return $this->items()->where('status', 'pending');
    }

    public function hasPendingItems(): bool
    {
        return $this->pendingItems()->exists();
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }
}
