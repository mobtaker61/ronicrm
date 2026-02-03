<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scrap_tasks', function (Blueprint $table) {
            $table->string('type', 20)->default('detail')->after('description'); // list | detail
        });

        Schema::create('scrap_task_list_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrap_task_id')->constrained('scrap_tasks')->cascadeOnDelete();
            $table->string('selector_type', 20); // xpath, class, id
            $table->string('selector_value');
            $table->string('value_kind', 20)->default('text'); // text, attribute
            $table->string('value_attr', 50)->nullable(); // e.g. href, src (when value_kind = attribute)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_task_list_configs');
        Schema::table('scrap_tasks', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
