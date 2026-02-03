<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrap_task_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrap_task_id')->constrained('scrap_tasks')->cascadeOnDelete();
            $table->text('url');
            $table->string('status', 20)->default('pending'); // pending, success, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_task_urls');
    }
};
