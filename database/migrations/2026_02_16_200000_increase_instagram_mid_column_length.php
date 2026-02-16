<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE instagram_webhook_events MODIFY mid VARCHAR(512) NULL');
        DB::statement('ALTER TABLE instagram_messages MODIFY instagram_message_id VARCHAR(512) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE instagram_webhook_events MODIFY mid VARCHAR(128) NULL');
        DB::statement('ALTER TABLE instagram_messages MODIFY instagram_message_id VARCHAR(255) NULL');
    }
};
