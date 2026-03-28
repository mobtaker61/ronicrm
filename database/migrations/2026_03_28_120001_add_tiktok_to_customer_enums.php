<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_contacts MODIFY COLUMN type ENUM('phone', 'email', 'whatsapp', 'telegram', 'instagram', 'tiktok') NOT NULL");
        // Must include every value from prior migrations (telegram_group_crawl, crawl, exhibition, direct) or existing rows fail.
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM(
            'website', 'referral', 'advertisement', 'social_media', 'other',
            'whatsapp', 'telegram', 'instagram', 'telegram_group_crawl',
            'crawl', 'exhibition', 'direct', 'tiktok'
        ) DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customer_contacts MODIFY COLUMN type ENUM('phone', 'email', 'whatsapp', 'telegram', 'instagram') NOT NULL");
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM(
            'website', 'referral', 'advertisement', 'social_media', 'other',
            'whatsapp', 'telegram', 'instagram', 'telegram_group_crawl',
            'crawl', 'exhibition', 'direct'
        ) DEFAULT 'other'");
    }
};
