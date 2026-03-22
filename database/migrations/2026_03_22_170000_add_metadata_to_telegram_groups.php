<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_groups', 'member_count')) {
                $table->unsignedInteger('member_count')->nullable()->after('type');
            }
            if (! Schema::hasColumn('telegram_groups', 'public_username')) {
                $table->string('public_username', 64)->nullable()->after('member_count');
            }
            if (! Schema::hasColumn('telegram_groups', 'public_link')) {
                $table->string('public_link', 255)->nullable()->after('public_username');
            }
            if (! Schema::hasColumn('telegram_groups', 'description')) {
                $table->text('description')->nullable()->after('public_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            foreach (['member_count', 'public_username', 'public_link', 'description'] as $column) {
                if (Schema::hasColumn('telegram_groups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

