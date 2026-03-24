<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class MediaFolder extends Model
{
    public const SCOPE_ORGANIZATION = 'organization';
    public const SCOPE_SYSTEM = 'system';

    protected $fillable = ['organization_id', 'scope_type', 'name', 'parent_id', 'created_by'];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $organizationId = $user->current_organization_id;

        return $query->where(function (Builder $builder) use ($organizationId): void {
            $builder->where('scope_type', self::SCOPE_SYSTEM)
                ->orWhere(function (Builder $scoped) use ($organizationId): void {
                    $scoped->where('scope_type', self::SCOPE_ORGANIZATION)
                        ->where('organization_id', $organizationId);
                });
        });
    }

    public function isSystemScope(): bool
    {
        return $this->scope_type === self::SCOPE_SYSTEM;
    }
}
