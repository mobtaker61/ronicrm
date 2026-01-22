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
            'email' => 'admin@ronicrm.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        // Create regular user
        $user = User::factory()->create([
            'name' => 'User',
            'email' => 'user@ronicrm.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('user');

        // Seed sample data if requested
        if ($this->command->option('with-sample-data')) {
            $this->call(SampleDataSeeder::class);
        }
    }
}
