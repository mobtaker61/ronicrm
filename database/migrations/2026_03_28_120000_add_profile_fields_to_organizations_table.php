<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('owner_user_id');
            }
            if (! Schema::hasColumn('organizations', 'legal_name')) {
                $table->string('legal_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('organizations', 'address_line1')) {
                $table->text('address_line1')->nullable()->after('legal_name');
            }
            if (! Schema::hasColumn('organizations', 'address_line2')) {
                $table->string('address_line2')->nullable()->after('address_line1');
            }
            if (! Schema::hasColumn('organizations', 'city')) {
                $table->string('city')->nullable()->after('address_line2');
            }
            if (! Schema::hasColumn('organizations', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
            if (! Schema::hasColumn('organizations', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('region');
            }
            if (! Schema::hasColumn('organizations', 'country')) {
                $table->string('country', 2)->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('organizations', 'phone')) {
                $table->string('phone')->nullable()->after('country');
            }
            if (! Schema::hasColumn('organizations', 'public_email')) {
                $table->string('public_email')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('organizations', 'website')) {
                $table->string('website')->nullable()->after('public_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            foreach ([
                'logo_path', 'legal_name', 'address_line1', 'address_line2', 'city', 'region',
                'postal_code', 'country', 'phone', 'public_email', 'website',
            ] as $col) {
                if (Schema::hasColumn('organizations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
