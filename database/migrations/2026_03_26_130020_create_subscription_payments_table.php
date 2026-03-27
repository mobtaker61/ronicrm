<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('organization_subscription_id')->nullable()->constrained('organization_subscriptions')->nullOnDelete();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 10)->default('AED');
            $table->timestamp('paid_at')->nullable();
            $table->string('reference', 190)->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};

