<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_org')->default('org_agent');
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('organizations')
                ->nullOnDelete();
        });

        $now = now();
        $defaultSlug = Str::slug('Roni Plus');
        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'Roni Plus',
            'slug' => $defaultSlug !== '' ? $defaultSlug : 'roni-plus',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userIds = DB::table('users')->pluck('id');
        foreach ($userIds as $userId) {
            DB::table('organization_user')->insert([
                'organization_id' => $orgId,
                'user_id' => $userId,
                'role_in_org' => 'org_admin',
                'is_default' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->update(['current_organization_id' => $orgId]);

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId) {
            DB::table('organizations')->where('id', $orgId)->update([
                'owner_user_id' => $firstUserId,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
        });

        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
