<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Tenant keys that must live in organization_settings only. */
    protected array $tenantKeys = [
        'smtp',
        'whatsapp',
        'ronibot',
        'telegram',
        'instagram',
        'org_notifications',
    ];

    public function up(): void
    {
        $organizations = DB::table('organizations')->pluck('id');

        foreach ($this->tenantKeys as $key) {
            $global = DB::table('settings')->where('key', $key)->first();
            if (! $global || $global->value === null) {
                continue;
            }

            foreach ($organizations as $orgId) {
                $exists = DB::table('organization_settings')
                    ->where('organization_id', $orgId)
                    ->where('key', $key)
                    ->exists();

                if (! $exists) {
                    DB::table('organization_settings')->insert([
                        'organization_id' => $orgId,
                        'key' => $key,
                        'value' => $global->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Legacy global copy no longer used for tenant keys (platform_notifications stays).
            if ($key !== 'ronibot') {
                DB::table('settings')->where('key', $key)->delete();
            }
        }

        // Remove deprecated ronibot key from global settings if present.
        DB::table('settings')->where('key', 'ronibot')->delete();
    }

    public function down(): void
    {
        // Non-reversible: org-specific values cannot be merged back safely.
    }
};
