<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapTask extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const TYPE_LIST = 'list';
    public const TYPE_DETAIL = 'detail';

    public function urls(): HasMany
    {
        return $this->hasMany(ScrapTaskUrl::class, 'scrap_task_id');
    }

    public function extractParams(): HasMany
    {
        return $this->hasMany(ScrapTaskExtractParam::class, 'scrap_task_id')->orderBy('sort_order');
    }

    public function listConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ScrapTaskListConfig::class, 'scrap_task_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ScrapTaskResult::class, 'scrap_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
