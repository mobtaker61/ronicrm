<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_contacts MODIFY COLUMN type ENUM('phone', 'email', 'whatsapp', 'telegram', 'instagram', 'tiktok') NOT NULL");
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM('website', 'referral', 'advertisement', 'social_media', 'other', 'whatsapp', 'telegram', 'instagram', 'tiktok') DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customer_contacts MODIFY COLUMN type ENUM('phone', 'email', 'whatsapp', 'telegram', 'instagram') NOT NULL");
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM('website', 'referral', 'advertisement', 'social_media', 'other', 'whatsapp', 'telegram', 'instagram') DEFAULT 'other'");
    }
};
