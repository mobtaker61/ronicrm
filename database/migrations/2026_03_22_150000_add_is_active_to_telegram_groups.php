<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_groups', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
