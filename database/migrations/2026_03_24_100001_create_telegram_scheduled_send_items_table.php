<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('telegram_scheduled_send_items')) {
            return;
        }

        Schema::create('telegram_scheduled_send_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_scheduled_send_run_id');
            $table->foreign('telegram_scheduled_send_run_id', 'tss_items_run_id_fk')->references('id')->on('telegram_scheduled_send_runs')->cascadeOnDelete();
            $table->string('telegram_group_id', 50);
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['telegram_scheduled_send_run_id', 'telegram_group_id'], 'tss_items_run_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_scheduled_send_items');
    }
};
