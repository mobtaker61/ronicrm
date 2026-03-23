<?php

namespace App\Support;

use App\Models\Organization;

class OrganizationContext
{
    protected static ?int $organizationId = null;

    public static function setOrganizationId(?int $organizationId): void
    {
        self::$organizationId = $organizationId;
    }

    public static function getOrganizationId(): ?int
    {
        if (self::$organizationId) {
            return self::$organizationId;
        }

        $user = auth()->user();
        if ($user && $user->current_organization_id) {
            return (int) $user->current_organization_id;
        }

        return Organization::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    public static function hasOrganization(): bool
    {
        return self::getOrganizationId() !== null;
    }
}
