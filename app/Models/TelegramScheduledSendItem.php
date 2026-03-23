<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramScheduledSendItem extends Model
{
    protected $fillable = [
        'telegram_scheduled_send_run_id',
        'telegram_group_id',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TelegramScheduledSendRun::class, 'telegram_scheduled_send_run_id');
    }

    public function markSent(): void
    {
        $this->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
