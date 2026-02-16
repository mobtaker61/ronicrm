<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ig_business_account_id')->unique()->index();
            $table->string('ig_username')->nullable()->index();
            $table->string('ig_profile_pic_url', 1024)->nullable();
            $table->string('page_id')->nullable()->index();
            $table->text('access_token_encrypted');
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes_json')->nullable();
            $table->timestamp('webhook_verified_at')->nullable();
            $table->timestamp('last_webhook_event_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_connections');
    }
};
