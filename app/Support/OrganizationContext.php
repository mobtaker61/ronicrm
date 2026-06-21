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
        if ($user) {
            if ($user->current_organization_id) {
                return (int) $user->current_organization_id;
            }

            $membershipOrgId = $user->organizations()
                ->wherePivot('status', 'active')
                ->orderByDesc('organization_user.is_default')
                ->orderBy('organizations.id')
                ->value('organizations.id');

            return $membershipOrgId ? (int) $membershipOrgId : null;
        }

        // Guests (webhooks, public routes): fallback only when explicitly needed.
        return Organization::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    public static function hasOrganization(): bool
    {
        return self::getOrganizationId() !== null;
    }
}
