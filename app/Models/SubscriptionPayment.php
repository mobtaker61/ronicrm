<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'organization_id',
        'organization_subscription_id',
        'amount_cents',
        'currency',
        'paid_at',
        'reference',
        'attachment_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'organization_subscription_id');
    }
}

