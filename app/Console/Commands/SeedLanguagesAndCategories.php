<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedLanguagesAndCategories extends Command
{
    protected $signature = 'seed:languages-categories';

    protected $description = 'Seed languages and telegram group categories (run if tables are empty)';

    public function handle(): int
    {
        $now = now();

        $langs = [
            ['code' => 'fa', 'name' => 'Persian', 'sort_order' => 1],
            ['code' => 'en', 'name' => 'English', 'sort_order' => 2],
            ['code' => 'ku', 'name' => 'Kurdish', 'sort_order' => 3],
            ['code' => 'tr', 'name' => 'Turkish', 'sort_order' => 4],
            ['code' => 'ar', 'name' => 'Arabic', 'sort_order' => 5],
            ['code' => 'hi', 'name' => 'Hindi', 'sort_order' => 6],
            ['code' => 'ur', 'name' => 'Urdu', 'sort_order' => 7],
            ['code' => 'other', 'name' => 'Other', 'sort_order' => 99],
        ];

        $cats = [
            ['name' => 'Technology', 'sort_order' => 1],
            ['name' => 'Marketing', 'sort_order' => 2],
            ['name' => 'General', 'sort_order' => 3],
            ['name' => 'News', 'sort_order' => 4],
            ['name' => 'Community', 'sort_order' => 5],
            ['name' => 'Education', 'sort_order' => 6],
            ['name' => 'Business', 'sort_order' => 7],
            ['name' => 'Other', 'sort_order' => 99],
        ];

        $langCount = 0;
        foreach ($langs as $l) {
            if (! DB::table('languages')->where('code', $l['code'])->exists()) {
                DB::table('languages')->insert([
                    'code' => $l['code'],
                    'name' => $l['name'],
                    'sort_order' => $l['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $langCount++;
            }
        }

        $catCount = 0;
        foreach ($cats as $c) {
            if (! DB::table('telegram_group_categories')->where('name', $c['name'])->exists()) {
                DB::table('telegram_group_categories')->insert([
                    'name' => $c['name'],
                    'sort_order' => $c['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $catCount++;
            }
        }

        $this->info("Added {$langCount} languages and {$catCount} categories.");
        $this->info('Total: ' . DB::table('languages')->count() . ' languages, ' . DB::table('telegram_group_categories')->count() . ' categories.');

        return self::SUCCESS;
    }
}
