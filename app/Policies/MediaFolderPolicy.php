<?php

namespace App\Policies;

use App\Models\MediaFolder;
use App\Models\User;

class MediaFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MediaFolder $folder): bool
    {
        return $this->canViewScope($user, $folder->scope_type, $folder->organization_id);
    }

    public function createForScope(User $user, string $scopeType, ?int $organizationId = null): bool
    {
        if ($scopeType === MediaFolder::SCOPE_SYSTEM) {
            return $user->hasRole('super_admin');
        }

        if (! $organizationId) {
            return false;
        }

        if ($user->hasGlobalAdminAccess()) {
            return true;
        }

        return $user->hasOrganizationRole('org_admin', $organizationId)
            || $user->hasOrganizationRole('org_manager', $organizationId)
            || $user->hasOrganizationRole('org_agent', $organizationId);
    }

    public function update(User $user, MediaFolder $folder): bool
    {
        return $this->createForScope($user, $folder->scope_type, $folder->organization_id);
    }

    public function delete(User $user, MediaFolder $folder): bool
    {
        return $this->update($user, $folder);
    }

    protected function canViewScope(User $user, string $scopeType, ?int $organizationId): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($scopeType === MediaFolder::SCOPE_SYSTEM) {
            return true;
        }

        return $organizationId !== null && $organizationId === (int) $user->current_organization_id;
    }
}
