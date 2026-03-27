<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TranslationValue;

class TranslationKey extends Model
{
    protected $fillable = [
        'namespace',
        'key',
        'description',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(TranslationValue::class);
    }
}

