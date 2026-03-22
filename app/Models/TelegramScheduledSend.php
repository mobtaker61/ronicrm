<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramScheduledSend extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'campaign_template_id',
        'post_link',
        'telegram_group_category_id',
        'send_at_time',
        'days_count',
        'runs_count',
        'last_sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CampaignTemplate::class, 'campaign_template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TelegramGroupCategory::class, 'telegram_group_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDueNow($query)
    {
        $now = now();
        $currentTime = $now->format('H:i');

        return $query->where('status', 'active')
            ->whereRaw('TIME_FORMAT(send_at_time, "%H:%i") = ?', [$currentTime])
            ->where(function ($q) use ($now) {
                $q->whereNull('last_sent_at')
                    ->orWhereRaw('DATE(last_sent_at) < ?', [$now->toDateString()]);
            })
            ->whereColumn('runs_count', '<', 'days_count');
    }

    public function stop(): void
    {
        $this->update(['status' => 'stopped']);
    }

    public function markSent(): void
    {
        $this->increment('runs_count');
        $this->update(['last_sent_at' => now()]);
        if ($this->runs_count >= $this->days_count) {
            $this->update(['status' => 'completed']);
        }
    }
}
