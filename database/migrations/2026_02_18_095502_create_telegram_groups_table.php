<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('telegram_groups')) {
            Schema::table('telegram_groups', function (Blueprint $table) {
                try {
                    $table->unique(['telegram_user_connection_id', 'telegram_group_id'], 'tg_groups_conn_group_unique');
                } catch (\Throwable $e) {
                    if (!str_contains($e->getMessage(), 'Duplicate')) {
                        throw $e;
                    }
                }
            });
            return;
        }
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_connection_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_group_id', 50)->index();
            $table->string('title')->nullable();
            $table->string('type', 20)->nullable();
            $table->boolean('can_post')->default(true);
            $table->string('last_error')->nullable();
            $table->unsignedBigInteger('last_crawled_message_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['telegram_user_connection_id', 'telegram_group_id'], 'tg_groups_conn_group_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};
