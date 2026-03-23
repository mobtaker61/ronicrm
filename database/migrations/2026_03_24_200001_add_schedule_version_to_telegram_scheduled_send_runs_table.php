<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('telegram_scheduled_send_runs')) {
            return;
        }

        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_scheduled_send_runs', 'schedule_version')) {
                $table->unsignedInteger('schedule_version')->default(1)->after('telegram_scheduled_send_id');
            }
        });

        // Replace old uniqueness (schedule + date) with version-aware uniqueness.
        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            try {
                $table->dropUnique('tss_runs_send_date_unique');
            } catch (\Throwable) {
                // Index may already be absent in some environments.
            }
        });

        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            $table->unique(
                ['telegram_scheduled_send_id', 'schedule_version', 'run_date'],
                'tss_runs_send_ver_date_unq'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_scheduled_send_runs')) {
            return;
        }

        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            try {
                $table->dropUnique('tss_runs_send_ver_date_unq');
            } catch (\Throwable) {
                // Ignore.
            }
        });

        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_scheduled_send_runs', 'schedule_version')) {
                $table->dropColumn('schedule_version');
            }
        });

        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            $table->unique(['telegram_scheduled_send_id', 'run_date'], 'tss_runs_send_date_unique');
        });
    }
};

