<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('telegram_groups', 'category')) {
            Schema::table('telegram_groups', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
        if (! Schema::hasColumn('telegram_groups', 'telegram_group_category_id')) {
            Schema::table('telegram_groups', function (Blueprint $table) {
                $table->foreignId('telegram_group_category_id')->nullable()->after('type')->constrained('telegram_group_categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('telegram_group_category_id');
        });
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('type');
        });
    }
};
