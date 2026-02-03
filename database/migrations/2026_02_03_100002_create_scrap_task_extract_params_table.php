<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrap_task_extract_params', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrap_task_id')->constrained('scrap_tasks')->cascadeOnDelete();
            $table->string('name'); // label e.g. "title", "price"
            $table->string('selector_type', 20); // xpath, class, id
            $table->string('selector_value'); // e.g. "//h1", ".price", "#product-title"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_task_extract_params');
    }
};
