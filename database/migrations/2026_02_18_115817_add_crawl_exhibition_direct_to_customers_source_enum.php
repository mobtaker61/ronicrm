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
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM(
            'website', 'referral', 'advertisement', 'social_media', 'other',
            'whatsapp', 'telegram', 'instagram', 'telegram_group_crawl',
            'crawl', 'exhibition', 'direct'
        ) DEFAULT 'other'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN source ENUM(
            'website', 'referral', 'advertisement', 'social_media', 'other',
            'whatsapp', 'telegram', 'instagram', 'telegram_group_crawl'
        ) DEFAULT 'other'");
    }
};
