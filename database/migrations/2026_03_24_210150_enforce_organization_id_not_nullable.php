<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    protected array $tables = [
        'customers',
        'customer_contacts',
        'customer_notes',
        'campaigns',
        'campaign_templates',
        'campaign_recipients',
        'campaign_logs',
        'telegram_messages',
        'instagram_messages',
        'instagram_webhook_events',
        'whatsapp_messages',
        'telegram_user_connections',
        'instagram_connections',
        'google_contacts_integrations',
        'telegram_groups',
        'telegram_scheduled_sends',
        'telegram_scheduled_send_runs',
        'telegram_scheduled_send_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->change();
            });
        }
    }
};
