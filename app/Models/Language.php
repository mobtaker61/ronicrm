<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'is_active', 'is_default', 'direction', 'font_family'];

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'language_organization')
            ->withTimestamps();
    }
}
