<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'location',
        'share_token',
        'is_share_enabled',
        'allow_excel_export',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_share_enabled' => 'boolean',
            'allow_excel_export' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->share_token)) {
                $project->share_token = bin2hex(random_bytes(24));
            }
        });
    }

    public function getShareUrlAttribute(): string
    {
        return url()->route('public.project.share', $this->share_token);
    }
}
