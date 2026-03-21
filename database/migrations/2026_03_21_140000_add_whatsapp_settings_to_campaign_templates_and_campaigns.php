<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_templates', function (Blueprint $table) {
            $table->json('whatsapp_settings')->nullable()->after('variables');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('whatsapp_settings')->nullable()->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_templates', function (Blueprint $table) {
            $table->dropColumn('whatsapp_settings');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('whatsapp_settings');
        });
    }
};
