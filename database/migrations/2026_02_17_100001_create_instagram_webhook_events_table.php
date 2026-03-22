<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_connection_id')->nullable()->constrained('instagram_connections')->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->string('mid', 512)->nullable()->index();
            $table->string('sender_id', 64)->nullable()->index();
            $table->string('recipient_id', 64)->nullable();
            $table->timestamp('event_timestamp')->nullable();
            $table->json('payload_redacted')->nullable();
            $table->timestamps();
        });

        Schema::table('instagram_messages', function (Blueprint $table) {
            $table->foreignId('instagram_connection_id')->nullable()->after('id')->constrained('instagram_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instagram_messages', function (Blueprint $table) {
            $table->dropForeign(['instagram_connection_id']);
        });
        Schema::dropIfExists('instagram_webhook_events');
    }
};
