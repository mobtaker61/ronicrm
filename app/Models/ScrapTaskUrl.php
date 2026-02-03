<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScrapTaskUrl extends Model
{
    protected $fillable = [
        'scrap_task_id',
        'url',
        'status',
        'error_message',
    ];

    public function scrapTask(): BelongsTo
    {
        return $this->belongsTo(ScrapTask::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(ScrapTaskResult::class, 'scrap_task_url_id');
    }
}
