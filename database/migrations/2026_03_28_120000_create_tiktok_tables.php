<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('open_id')->index();
            $table->string('union_id')->nullable()->index();
            $table->string('display_name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token_encrypted');
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->json('scopes_json')->nullable();
            $table->timestamp('webhook_verified_at')->nullable();
            $table->timestamp('last_webhook_event_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'open_id'], 'tiktok_connections_org_open_id_unique');
        });

        Schema::create('tiktok_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('tiktok_connection_id')->nullable()->constrained('tiktok_connections')->nullOnDelete();
            $table->string('tiktok_message_id', 512)->nullable()->index();
            $table->string('conversation_id')->nullable()->index();
            $table->string('tiktok_open_id')->index();
            $table->string('from_display_name')->nullable();
            $table->text('message')->nullable();
            $table->string('message_type')->default('text');
            $table->text('media_url')->nullable();
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

        Schema::create('tiktok_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_connection_id')->nullable()->constrained('tiktok_connections')->nullOnDelete();
            $table->string('event_type', 128)->index();
            $table->string('user_openid')->nullable()->index();
            $table->unsignedBigInteger('create_time')->nullable();
            $table->text('content_raw')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_webhook_events');
        Schema::dropIfExists('tiktok_messages');
        Schema::dropIfExists('tiktok_connections');
    }
};
