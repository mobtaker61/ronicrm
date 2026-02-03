<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapTaskResult extends Model
{
    protected $fillable = [
        'scrap_task_id',
        'scrap_task_url_id',
        'extracted_data',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
        ];
    }

    public function scrapTask(): BelongsTo
    {
        return $this->belongsTo(ScrapTask::class);
    }

    public function scrapTaskUrl(): BelongsTo
    {
        return $this->belongsTo(ScrapTaskUrl::class, 'scrap_task_url_id');
    }
}
