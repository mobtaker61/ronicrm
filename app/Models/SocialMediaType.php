<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaType extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'base_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function customerSocialMedia(): HasMany
    {
        return $this->hasMany(CustomerSocialMedia::class);
    }

    public function getFullUrl(string $handle): string
    {
        if ($this->base_url) {
            return rtrim($this->base_url, '/') . '/' . ltrim($handle, '@/');
        }
        return $handle;
    }
}
