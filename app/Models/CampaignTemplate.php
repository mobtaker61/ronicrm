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
        'content_translations',
        'image',
        'variables',
        'whatsapp_settings',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'whatsapp_settings' => 'array',
            'content_translations' => 'array',
            'type' => 'string',
        ];
    }

    /**
     * Get content for a given language. Falls back to main content or first available.
     */
    public function getContentForLanguage(?string $langCode): string
    {
        $translations = $this->content_translations ?? [];
        if ($langCode && isset($translations[$langCode]) && trim((string) $translations[$langCode]) !== '') {
            return (string) $translations[$langCode];
        }
        if (trim((string) $this->content) !== '') {
            return (string) $this->content;
        }
        return (string) (reset($translations) ?: '');
    }
}
