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
        Schema::table('scrap_task_list_configs', function (Blueprint $table) {
            $table->unsignedInteger('delay_seconds')->nullable()->after('value_attr')->comment('Delay before extraction to allow dynamic content to load');
            $table->string('pagination_type', 20)->nullable()->after('delay_seconds')->comment('next_page or load_more');
            $table->string('pagination_selector_type', 20)->nullable()->after('pagination_type')->comment('xpath, class, or id for pagination button/link');
            $table->string('pagination_selector_value')->nullable()->after('pagination_selector_type')->comment('Selector value for pagination element');
            $table->unsignedInteger('max_pages')->nullable()->after('pagination_selector_value')->comment('Maximum number of pages to scrape');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scrap_task_list_configs', function (Blueprint $table) {
            $table->dropColumn([
                'delay_seconds',
                'pagination_type',
                'pagination_selector_type',
                'pagination_selector_value',
                'max_pages',
            ]);
        });
    }
};
