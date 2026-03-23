<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('telegram_scheduled_send_runs')) {
            return;
        }

        Schema::dropIfExists('telegram_scheduled_send_items');
        Schema::dropIfExists('telegram_scheduled_send_runs');
        Schema::create('telegram_scheduled_send_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_scheduled_send_id')->constrained('telegram_scheduled_sends')->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status', 20)->default('in_progress'); // in_progress | completed
            $table->timestamps();

            $table->unique(['telegram_scheduled_send_id', 'run_date'], 'tss_runs_send_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_scheduled_send_runs');
    }
};
