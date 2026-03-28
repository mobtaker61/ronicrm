<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TikTok CDN avatar URLs can exceed 255 characters → SQLSTATE[22001] Data too long for column 'avatar_url'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tiktok_connections MODIFY avatar_url TEXT NULL');
        DB::statement('ALTER TABLE tiktok_messages MODIFY media_url TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tiktok_connections MODIFY avatar_url VARCHAR(255) NULL');
        DB::statement('ALTER TABLE tiktok_messages MODIFY media_url VARCHAR(255) NULL');
    }
};
