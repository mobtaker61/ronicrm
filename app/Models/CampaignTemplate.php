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
        'whatsapp_settings',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'whatsapp_settings' => 'array',
            'type' => 'string',
        ];
    }
}
