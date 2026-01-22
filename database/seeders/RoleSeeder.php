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
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

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
            Permission::create(['name' => $permission]);
        }

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
