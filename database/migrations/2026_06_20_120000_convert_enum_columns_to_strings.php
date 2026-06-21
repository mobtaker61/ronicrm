<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    protected array $columnsByTable = [
        'customers' => ['status', 'source', 'type', 'gender'],
        'customer_contacts' => ['type'],
        'campaigns' => ['type', 'status'],
        'campaign_templates' => ['type'],
        'campaign_recipients' => ['status'],
        'telegram_messages' => ['direction', 'status'],
        'whatsapp_messages' => ['direction', 'status'],
        'instagram_messages' => ['direction', 'status'],
        'tiktok_messages' => ['direction', 'status'],
        'telegram_user_connections' => ['status'],
        'media_folders' => ['scope_type'],
        'media_files' => ['scope_type'],
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columnsByTable as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $nullable = in_array($column, ['gender'], true) ? 'NULL' : 'NOT NULL';
                $default = match ($table.'.'.$column) {
                    'customers.status' => "DEFAULT 'lead'",
                    'customers.source' => "DEFAULT 'other'",
                    'customers.type' => "DEFAULT 'person'",
                    'campaigns.status' => "DEFAULT 'draft'",
                    'campaign_recipients.status' => "DEFAULT 'pending'",
                    'telegram_messages.direction', 'whatsapp_messages.direction', 'instagram_messages.direction', 'tiktok_messages.direction' => "DEFAULT 'incoming'",
                    'telegram_messages.status', 'whatsapp_messages.status', 'instagram_messages.status', 'tiktok_messages.status' => "DEFAULT 'received'",
                    'telegram_user_connections.status' => "DEFAULT 'pending'",
                    'media_folders.scope_type', 'media_files.scope_type' => "DEFAULT 'organization'",
                    default => '',
                };

                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(50) {$nullable} {$default}");
            }
        }
    }

    public function down(): void
    {
        // Intentionally omitted: reverting to ENUM requires per-column value lists.
    }
};
