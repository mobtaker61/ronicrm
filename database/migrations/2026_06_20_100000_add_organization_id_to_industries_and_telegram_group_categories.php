<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    protected array $tables = ['industries', 'telegram_group_categories'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->cascadeOnDelete();
            });
        }

        $defaultOrganizationId = DB::table('organizations')
            ->where('slug', 'roni-plus')
            ->value('id') ?? DB::table('organizations')->orderBy('id')->value('id');

        if ($defaultOrganizationId) {
            foreach ($this->tables as $tableName) {
                DB::table($tableName)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $defaultOrganizationId]);
            }
        }

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->index(['organization_id', 'sort_order'], "{$tableName}_org_sort_idx");
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            foreach ($this->tables as $tableName) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `organization_id` BIGINT UNSIGNED NOT NULL");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("{$tableName}_org_sort_idx");
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }
};
