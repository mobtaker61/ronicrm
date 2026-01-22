<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSocialMedia extends Model
{
    protected $fillable = [
        'customer_id',
        'social_media_type_id',
        'handle',
        'url',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function socialMediaType(): BelongsTo
    {
        return $this->belongsTo(SocialMediaType::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($socialMedia) {
            if ($socialMedia->socialMediaType && !$socialMedia->url) {
                $socialMedia->url = $socialMedia->socialMediaType->getFullUrl($socialMedia->handle);
            }
        });
    }
}
