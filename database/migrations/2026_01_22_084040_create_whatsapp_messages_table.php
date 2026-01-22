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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique()->nullable(); // Message ID from Ronibot
            $table->string('from_phone'); // Sender phone number
            $table->string('to_phone')->nullable(); // Receiver phone number (our number)
            $table->text('message'); // Message content
            $table->string('message_type')->default('text'); // text, image, video, audio, document, etc.
            $table->string('media_url')->nullable(); // URL to media file if any
            $table->string('media_mime_type')->nullable(); // MIME type of media
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete(); // Linked customer if exists
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->enum('status', ['received', 'sent', 'delivered', 'read', 'failed'])->default('received');
            $table->timestamp('read_at')->nullable(); // When message was read
            $table->json('metadata')->nullable(); // Additional data from webhook
            $table->timestamps();

            // Indexes for better performance
            $table->index('from_phone');
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
        Schema::dropIfExists('whatsapp_messages');
    }
};
