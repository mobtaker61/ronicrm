<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE campaign_templates MODIFY COLUMN type ENUM('whatsapp', 'email', 'telegram') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE campaign_templates MODIFY COLUMN type ENUM('whatsapp', 'email') NOT NULL");
    }
};
