<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_folders', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->enum('scope_type', ['organization', 'system'])
                ->default('organization')
                ->after('name');
            $table->index(['scope_type', 'organization_id', 'parent_id'], 'media_folders_scope_org_parent_idx');
        });

        Schema::table('media_files', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->enum('scope_type', ['organization', 'system'])
                ->default('organization')
                ->after('name');
            $table->index(['scope_type', 'organization_id', 'folder_id'], 'media_files_scope_org_folder_idx');
        });

        $defaultOrganizationId = DB::table('organizations')->where('slug', 'roni-plus')->value('id')
            ?? DB::table('organizations')->orderBy('id')->value('id');

        if ($defaultOrganizationId) {
            DB::table('media_folders')
                ->whereNull('organization_id')
                ->update([
                    'organization_id' => $defaultOrganizationId,
                    'scope_type' => 'organization',
                ]);

            DB::table('media_files')
                ->whereNull('organization_id')
                ->update([
                    'organization_id' => $defaultOrganizationId,
                    'scope_type' => 'organization',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex('media_files_scope_org_folder_idx');
            $table->dropColumn('scope_type');
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('media_folders', function (Blueprint $table) {
            $table->dropIndex('media_folders_scope_org_parent_idx');
            $table->dropColumn('scope_type');
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
