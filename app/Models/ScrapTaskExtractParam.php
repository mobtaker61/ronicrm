<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapTaskExtractParam extends Model
{
    protected $fillable = [
        'scrap_task_id',
        'name',
        'selector_type',
        'selector_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function scrapTask(): BelongsTo
    {
        return $this->belongsTo(ScrapTask::class);
    }
}
