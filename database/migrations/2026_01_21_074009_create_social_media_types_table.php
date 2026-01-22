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
        Schema::create('social_media_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Instagram", "Facebook", "LinkedIn"
            $table->string('icon')->nullable(); // Icon class or SVG path
            $table->string('base_url')->nullable(); // e.g., "https://instagram.com/"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_types');
    }
};
