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

    public function runs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TelegramScheduledSendRun::class, 'telegram_scheduled_send_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Schedules that are due: time passed, runs left, and either no run for today or today's run has pending items.
     */
    public function scopeDueNow($query)
    {
        $now = now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        return $query->where('status', 'active')
            ->whereColumn('runs_count', '<', 'days_count')
            ->whereRaw('send_at_time <= ?', [$currentTime])
            ->where(function ($q) use ($today) {
                $q->whereDoesntHave('runs', fn ($r) => $r->whereDate('run_date', $today))
                    ->orWhereHas('runs', fn ($r) => $r->whereDate('run_date', $today)->where('status', 'in_progress')->whereHas('items', fn ($i) => $i->where('status', 'pending')));
            });
    }

    public function stop(): void
    {
        $this->update(['status' => 'stopped']);
    }

}
