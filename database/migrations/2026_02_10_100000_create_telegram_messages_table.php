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
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_message_id')->nullable()->index();
            $table->string('chat_id')->index(); // Conversation partner (Telegram chat_id)
            $table->string('from_username')->nullable();
            $table->text('message')->nullable();
            $table->string('message_type')->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->enum('status', ['received', 'sent', 'delivered', 'read', 'failed'])->default('received');
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('created_at');
            $table->index(['direction', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
