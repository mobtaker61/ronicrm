<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            if (! Schema::hasColumn('languages', 'direction')) {
                $table->string('direction', 10)->default('ltr')->after('is_default');
            }
            if (! Schema::hasColumn('languages', 'font_family')) {
                $table->string('font_family', 120)->nullable()->after('direction');
            }
        });

        // Set sensible defaults for existing languages
        DB::table('languages')
            ->whereIn('code', ['fa', 'ar', 'ur', 'hi'])
            ->update(['direction' => 'rtl']);

        // Basic font defaults (override anytime from Superadmin UI)
        DB::table('languages')
            ->where('code', 'fa')
            ->update(['font_family' => DB::raw('COALESCE(font_family, "Vazirmatn")')]);

        DB::table('languages')
            ->where('code', 'ar')
            ->update(['font_family' => DB::raw('COALESCE(font_family, "Cairo")')]);

        DB::table('languages')
            ->where('code', 'tr')
            ->update(['font_family' => DB::raw('COALESCE(font_family, "Inter")')]);

        DB::table('languages')
            ->where('code', 'en')
            ->update(['font_family' => DB::raw('COALESCE(font_family, "Inter")')]);
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            if (Schema::hasColumn('languages', 'font_family')) {
                $table->dropColumn('font_family');
            }
            if (Schema::hasColumn('languages', 'direction')) {
                $table->dropColumn('direction');
            }
        });
    }
};

