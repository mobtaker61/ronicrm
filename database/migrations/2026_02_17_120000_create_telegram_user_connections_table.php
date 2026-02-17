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
        Schema::create('telegram_user_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('session_path')->nullable();
            $table->enum('status', ['pending', 'connected', 'expired'])->default('pending');
            $table->text('api_id_encrypted')->nullable();
            $table->text('api_hash_encrypted')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_user_connections');
    }
};
