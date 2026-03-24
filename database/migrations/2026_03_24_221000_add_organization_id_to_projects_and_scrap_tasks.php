<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index(['organization_id', 'created_at'], 'projects_org_created_at_idx');
        });

        Schema::table('scrap_tasks', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index(['organization_id', 'created_at'], 'scrap_tasks_org_created_at_idx');
        });

        $defaultOrganizationId = DB::table('organizations')->where('slug', 'roni-plus')->value('id')
            ?? DB::table('organizations')->orderBy('id')->value('id');

        if (! $defaultOrganizationId) {
            return;
        }

        DB::table('projects')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        DB::table('scrap_tasks')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
    }

    public function down(): void
    {
        Schema::table('scrap_tasks', function (Blueprint $table) {
            $table->dropIndex('scrap_tasks_org_created_at_idx');
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_org_created_at_idx');
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
