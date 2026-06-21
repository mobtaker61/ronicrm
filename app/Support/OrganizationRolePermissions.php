<?php

namespace App\Support;

/**
 * Permission matrix for organization roles (organization_user.role_in_org).
 * Global Spatie roles (super_admin) bypass this via User::hasOrgPermission().
 */
class OrganizationRolePermissions
{
  /** @var array<string, list<string>> */
  public const ROLE_PERMISSIONS = [
    'org_admin' => ['*'],
    'org_manager' => [
      'customers.*',
      'campaigns.*',
      'reports.view',
      'manage industries',
      'inbox.*',
      'projects.*',
      'media.*',
      'telegram_groups.*',
    ],
    'org_agent' => [
      'customers.view',
      'customers.create',
      'customers.edit',
      'campaigns.view',
      'campaigns.create',
      'inbox.*',
      'reports.view',
    ],
  ];

  public static function allows(string $role, string $permission): bool
  {
    $permissions = self::ROLE_PERMISSIONS[$role] ?? [];

    foreach ($permissions as $allowed) {
      if ($allowed === '*' || $allowed === $permission) {
        return true;
      }

      if (str_ends_with($allowed, '.*')) {
        $prefix = substr($allowed, 0, -2);
        if ($permission === $prefix || str_starts_with($permission, $prefix.'.')) {
          return true;
        }
      }
    }

    return false;
  }

  /** @return list<string> */
  public static function permissionsForRole(string $role): array
  {
    return self::ROLE_PERMISSIONS[$role] ?? [];
  }
}
