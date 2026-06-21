<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements CanResetPasswordContract, MustVerifyEmailContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanResetPassword, HasFactory, MustVerifyEmailTrait, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'current_organization_id',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['role_in_org', 'is_default', 'status'])
            ->withTimestamps();
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function hasGlobalAdminAccess(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function hasVerifiedEmail(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ! is_null($this->email_verified_at);
    }

    public function hasOrganizationRole(string $role, ?int $organizationId = null): bool
    {
        $orgId = $organizationId ?? $this->current_organization_id;
        if (! $orgId) {
            return false;
        }

        return $this->organizations()
            ->where('organizations.id', $orgId)
            ->wherePivot('role_in_org', $role)
            ->exists();
    }

    public function canManageOrganizationSettings(?int $organizationId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $orgId = $organizationId ?? $this->current_organization_id;
        if (! $orgId) {
            return false;
        }

        return $this->organizations()
            ->where('organizations.id', $orgId)
            ->wherePivot('role_in_org', 'org_admin')
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function canAccessSettings(?int $organizationId = null): bool
    {
        return $this->canManageOrganizationSettings($organizationId);
    }

    public function organizationRole(?int $organizationId = null): ?string
    {
        if ($this->isSuperAdmin()) {
            return 'org_admin';
        }

        $orgId = $organizationId ?? $this->current_organization_id;
        if (! $orgId) {
            return null;
        }

        $pivot = $this->organizations()
            ->where('organizations.id', $orgId)
            ->wherePivot('status', 'active')
            ->first()?->pivot;

        return $pivot?->role_in_org;
    }

    public function hasOrgPermission(string $permission, ?int $organizationId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $role = $this->organizationRole($organizationId);
        if (! $role) {
            return false;
        }

        return \App\Support\OrganizationRolePermissions::allows($role, $permission);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\Auth\ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\Auth\VerifyEmailNotification);
    }
}
