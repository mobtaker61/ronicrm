<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('telegram_scheduled_sends')) {
            return;
        }

        if (! Schema::hasColumn('telegram_scheduled_sends', 'version')) {
            Schema::table('telegram_scheduled_sends', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_scheduled_sends')) {
            return;
        }

        if (Schema::hasColumn('telegram_scheduled_sends', 'version')) {
            Schema::table('telegram_scheduled_sends', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};

