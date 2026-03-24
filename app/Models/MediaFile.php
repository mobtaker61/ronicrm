<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read string $url
 */
class MediaFile extends Model
{
    public const SCOPE_ORGANIZATION = 'organization';
    public const SCOPE_SYSTEM = 'system';

    protected $fillable = ['organization_id', 'scope_type', 'folder_id', 'name', 'path', 'disk', 'mime_type', 'size', 'created_by'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'organization_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
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
