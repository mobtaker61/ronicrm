<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function manage(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageOrganizationSettings((int) $organization->id);
    }
}
