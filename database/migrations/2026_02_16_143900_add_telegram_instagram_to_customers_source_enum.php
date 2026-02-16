<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM('website', 'referral', 'advertisement', 'social_media', 'other', 'whatsapp', 'telegram', 'instagram') DEFAULT 'other'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum (existing rows with whatsapp/telegram/instagram would need to be updated first in production)
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM('website', 'referral', 'advertisement', 'social_media', 'other') DEFAULT 'other'");
    }
};
