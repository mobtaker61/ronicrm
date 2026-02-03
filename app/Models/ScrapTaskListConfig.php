<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapTaskListConfig extends Model
{
    protected $fillable = [
        'scrap_task_id',
        'selector_type',
        'selector_value',
        'value_kind',
        'value_attr',
    ];

    public function scrapTask(): BelongsTo
    {
        return $this->belongsTo(ScrapTask::class);
    }
}
