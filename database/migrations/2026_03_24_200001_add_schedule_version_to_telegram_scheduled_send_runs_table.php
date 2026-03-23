<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // MariaDB may use the old unique index for FK coverage.
        // Ensure a dedicated index exists before dropping unique.
        if (empty(DB::select("SHOW INDEX FROM telegram_scheduled_send_runs WHERE Key_name = 'tss_runs_sched_fk_idx'"))) {
            Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
                $table->index('telegram_scheduled_send_id', 'tss_runs_sched_fk_idx');
            });
        }

        // Replace old uniqueness (schedule + date) with version-aware uniqueness.
        Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
            try {
                $table->dropUnique('tss_runs_send_date_unique');
            } catch (\Throwable) {
                // Index may already be absent in some environments.
            }
        });

        if (empty(DB::select("SHOW INDEX FROM telegram_scheduled_send_runs WHERE Key_name = 'tss_runs_send_ver_date_unq'"))) {
            Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
                $table->unique(
                    ['telegram_scheduled_send_id', 'schedule_version', 'run_date'],
                    'tss_runs_send_ver_date_unq'
                );
            });
        }
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

        if (empty(DB::select("SHOW INDEX FROM telegram_scheduled_send_runs WHERE Key_name = 'tss_runs_send_date_unique'"))) {
            Schema::table('telegram_scheduled_send_runs', function (Blueprint $table) {
                $table->unique(['telegram_scheduled_send_id', 'run_date'], 'tss_runs_send_date_unique');
            });
        }
    }
};

