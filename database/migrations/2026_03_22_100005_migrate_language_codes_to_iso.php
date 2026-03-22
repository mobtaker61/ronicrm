<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TO_NEW = [
        'persian' => 'fa',
        'english' => 'en',
        'kurdish' => 'ku',
        'turkish' => 'tr',
        'arabic' => 'ar',
        'hindi' => 'hi',
        'urdu' => 'ur',
    ];

    public function up(): void
    {
        foreach (self::OLD_TO_NEW as $old => $new) {
            DB::table('languages')->where('code', $old)->update(['code' => $new]);
        }
        foreach (self::OLD_TO_NEW as $old => $new) {
            DB::table('telegram_groups')->where('language', $old)->update(['language' => $new]);
        }
    }

    public function down(): void
    {
        $newToOld = array_flip(self::OLD_TO_NEW);
        foreach ($newToOld as $new => $old) {
            DB::table('telegram_groups')->where('language', $new)->update(['language' => $old]);
        }
        foreach ($newToOld as $new => $old) {
            DB::table('languages')->where('code', $new)->update(['code' => $old]);
        }
    }
};
