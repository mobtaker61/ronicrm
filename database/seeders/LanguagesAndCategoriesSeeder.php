<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\TelegramGroupCategory;
use Illuminate\Database\Seeder;

class LanguagesAndCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        if (Language::count() === 0) {
            $langs = [
                ['code' => 'fa', 'name' => 'فارسی', 'sort_order' => 1],
                ['code' => 'en', 'name' => 'English', 'sort_order' => 2],
                ['code' => 'ar', 'name' => 'العربية', 'sort_order' => 3],
                ['code' => 'tr', 'name' => 'Türkçe', 'sort_order' => 4],
            ];
            foreach ($langs as $l) {
                Language::create($l);
            }
        }

        if (TelegramGroupCategory::count() === 0) {
            $cats = [
                ['name' => 'فناوری', 'sort_order' => 1],
                ['name' => 'بازاریابی', 'sort_order' => 2],
                ['name' => 'عمومی', 'sort_order' => 3],
                ['name' => 'اخبار', 'sort_order' => 4],
                ['name' => 'جامعه', 'sort_order' => 5],
                ['name' => 'آموزش', 'sort_order' => 6],
                ['name' => 'کسب‌وکار', 'sort_order' => 7],
                ['name' => 'سایر', 'sort_order' => 99],
            ];
            foreach ($cats as $c) {
                TelegramGroupCategory::create($c);
            }
        }
    }
}
