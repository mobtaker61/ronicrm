<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_scheduled_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // template | forward
            $table->foreignId('campaign_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('post_link', 500)->nullable();
            $table->foreignId('telegram_group_category_id')->constrained()->cascadeOnDelete();
            $table->time('send_at_time');
            $table->unsignedSmallInteger('days_count')->default(1);
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->string('status', 20)->default('active'); // active | stopped | completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_scheduled_sends');
    }
};
