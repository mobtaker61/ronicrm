<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        $this->call([
            SampleDataSeeder::class,
            LanguagesAndCategoriesSeeder::class,
            SocialMediaTypeSeeder::class,
        ]);

        // PHP 8.4 + PDO: تراکنش یتیم در CLI commit نمی‌شود و باعث rollback می‌شود (CommitOrphanPdoTransaction فقط برای HTTP اجرا می‌شود)
        $this->commitOrphanPdoTransaction();
    }

    protected function commitOrphanPdoTransaction(): void
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            if ($pdo->inTransaction() && $connection->transactionLevel() === 0) {
                $pdo->commit();
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
