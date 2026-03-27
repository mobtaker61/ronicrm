<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationValue extends Model
{
    protected $fillable = [
        'language_id',
        'translation_key_id',
        'value',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class);
    }
}

