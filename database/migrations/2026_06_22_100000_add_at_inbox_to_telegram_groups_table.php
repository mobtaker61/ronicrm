<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_groups', 'at_inbox')) {
                $table->boolean('at_inbox')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_groups', 'at_inbox')) {
                $table->dropColumn('at_inbox');
            }
        });
    }
};
