<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationSubscription extends Model
{
    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'started_at',
        'ends_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancel_at_period_end',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cancel_at_period_end' => 'boolean',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'organization_subscription_id');
    }
}

