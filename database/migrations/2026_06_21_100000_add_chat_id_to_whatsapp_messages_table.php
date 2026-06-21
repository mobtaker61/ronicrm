<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'chat_id')) {
                $table->string('chat_id', 120)->nullable()->after('message_id');
                $table->index(['organization_id', 'chat_id'], 'whatsapp_messages_org_chat_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_messages', 'chat_id')) {
                $table->dropIndex('whatsapp_messages_org_chat_idx');
                $table->dropColumn('chat_id');
            }
        });
    }
};
