<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_groups', 'category')) {
                $table->string('category', 100)->nullable()->after('type');
            }
            if (! Schema::hasColumn('telegram_groups', 'language')) {
                $table->string('language', 10)->nullable()->after('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->dropColumn(['category', 'language']);
        });
    }
};
