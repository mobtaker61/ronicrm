<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguagesAndCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $langs = [
            ['code' => 'fa', 'name' => 'Persian', 'sort_order' => 1],
            ['code' => 'en', 'name' => 'English', 'sort_order' => 2],
            ['code' => 'ckb', 'name' => 'Kurdish (Sorani)', 'sort_order' => 3],
            ['code' => 'ku', 'name' => 'Kurdish (Sorani)', 'sort_order' => 8],
            ['code' => 'tr', 'name' => 'Turkish', 'sort_order' => 4],
            ['code' => 'ar', 'name' => 'Arabic', 'sort_order' => 5],
            ['code' => 'hi', 'name' => 'Hindi', 'sort_order' => 6],
            ['code' => 'ur', 'name' => 'Urdu', 'sort_order' => 7],
            ['code' => 'other', 'name' => 'Other', 'sort_order' => 99],
        ];
        foreach ($langs as $l) {
            if (DB::table('languages')->where('code', $l['code'])->exists()) {
                DB::table('languages')->where('code', $l['code'])->update([
                    'name' => $l['name'],
                    'sort_order' => $l['sort_order'],
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('languages')->insert([
                    'code' => $l['code'],
                    'name' => $l['name'],
                    'sort_order' => $l['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

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
        foreach ($cats as $c) {
            $existing = DB::table('telegram_group_categories')->where('name', $c['name'])->first();
            if (! $existing) {
                DB::table('telegram_group_categories')->insert([
                    'name' => $c['name'],
                    'sort_order' => $c['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
