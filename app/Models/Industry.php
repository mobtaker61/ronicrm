<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Industry extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'parent_id',
        'sort_order',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Industry::class, 'parent_id')->orderBy('sort_order');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        $visited = [$this->id => true];
        $maxDepth = 50;

        while ($parent && $maxDepth-- > 0) {
            if (isset($visited[$parent->id])) {
                break;
            }
            $visited[$parent->id] = true;
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }
}
