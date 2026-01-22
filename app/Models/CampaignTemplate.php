<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'subject',
        'content',
        'image',
        'variables',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'type' => 'string',
        ];
    }
}
