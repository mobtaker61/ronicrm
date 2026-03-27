<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'billing_period',
        'billing_interval',
        'price_cents',
        'currency',
        'limits_json',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'limits_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }
}

