<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create permissions
        $permissions = [
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            'view campaigns',
            'create campaigns',
            'edit campaigns',
            'delete campaigns',
            'view reports',
            'manage industries',
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdminRole->givePermissionTo(Permission::all());
        // Assign all permissions to admin
        $adminRole->givePermissionTo(Permission::all());

        // Assign basic permissions to user
        $userRole->givePermissionTo([
            'view customers',
            'create customers',
            'edit customers',
            'view campaigns',
            'create campaigns',
            'view reports',
        ]);
    }
}
