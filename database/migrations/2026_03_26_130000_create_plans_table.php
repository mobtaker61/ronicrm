<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 60)->unique();
            $table->string('billing_period', 20)->default('monthly'); // monthly|yearly|custom
            $table->unsignedInteger('billing_interval')->default(1);
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 10)->default('AED');
            $table->json('limits_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

