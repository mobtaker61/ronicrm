<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix contacts with empty type that have Instagram ID (long numeric value).
     */
    public function up(): void
    {
        $updated = DB::table('customer_contacts')
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '');
            })
            ->whereNotNull('value')
            ->whereRaw("value REGEXP '^[0-9]{10,20}$'")
            ->update(['type' => 'instagram']);

        if ($updated > 0) {
            \Illuminate\Support\Facades\Log::info("fix_instagram_contacts: updated $updated contacts to type=instagram");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely revert - we don't know which were originally empty
    }
};
