<?php

namespace App\Policies;

use App\Models\MediaFile;
use App\Models\User;

class MediaFilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MediaFile $file): bool
    {
        return $this->canViewScope($user, $file->scope_type, $file->organization_id);
    }

    public function createForScope(User $user, string $scopeType, ?int $organizationId = null): bool
    {
        if ($scopeType === MediaFile::SCOPE_SYSTEM) {
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

    public function update(User $user, MediaFile $file): bool
    {
        return $this->createForScope($user, $file->scope_type, $file->organization_id);
    }

    public function delete(User $user, MediaFile $file): bool
    {
        return $this->update($user, $file);
    }

    protected function canViewScope(User $user, string $scopeType, ?int $organizationId): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($scopeType === MediaFile::SCOPE_SYSTEM) {
            return true;
        }

        return $organizationId !== null && $organizationId === (int) $user->current_organization_id;
    }
}
