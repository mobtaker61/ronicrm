<?php

namespace App\Models\Concerns;

use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->organization_id)) {
                $model->organization_id = OrganizationContext::getOrganizationId();
            }
        });

        static::addGlobalScope('organization', function (Builder $builder): void {
            $organizationId = OrganizationContext::getOrganizationId();
            if ($organizationId) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $organizationId);
            }
        });
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->withoutGlobalScope('organization')
            ->where($this->getTable().'.organization_id', $organizationId);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
