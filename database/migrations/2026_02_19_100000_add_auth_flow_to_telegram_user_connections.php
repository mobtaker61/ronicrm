<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_user_connections', function (Blueprint $table) {
            $table->string('auth_flow', 32)->nullable()->after('status')
                ->comment('qr=QR flow in progress, phone_otp=waiting OTP, phone_2fa=waiting 2FA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_user_connections', function (Blueprint $table) {
            $table->dropColumn('auth_flow');
        });
    }
};
