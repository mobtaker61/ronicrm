<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('status', 30)->default('trial'); // trial|active|grace|expired|canceled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['ends_at']);
            $table->unique(['organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};

