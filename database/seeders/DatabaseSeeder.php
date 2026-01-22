<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@ronicrm.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        // Create regular user
        $user = User::factory()->create([
            'name' => 'User',
            'username' => 'user',
            'email' => 'user@ronicrm.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('user');

        // Note: To seed sample data, run: php artisan db:seed --class=SampleDataSeeder
        // Or run both commands separately:
        // 1. php artisan db:seed (for users and roles)
        // 2. php artisan db:seed --class=SampleDataSeeder (for sample data)
    }
}
