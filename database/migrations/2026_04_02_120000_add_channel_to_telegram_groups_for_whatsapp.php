<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_groups')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);

        if ($driver === 'sqlite') {
            Schema::table('telegram_groups', function (Blueprint $table) {
                try {
                    $table->dropUnique('tg_groups_conn_group_unique');
                } catch (\Throwable $e) {
                    if (! str_contains($e->getMessage(), 'no such index')) {
                        throw $e;
                    }
                }
            });
            $this->sqliteRebuildWithNullableConnection();
        } else {
            // MariaDB/MySQL: ایندکس یکتا با کلید خارجی روی همان ستون قفل است؛ ابتدا FK بعد unique.
            if ($isMysqlFamily) {
                Schema::table('telegram_groups', function (Blueprint $table) {
                    $table->dropForeign(['telegram_user_connection_id']);
                });
            }

            Schema::table('telegram_groups', function (Blueprint $table) {
                try {
                    $table->dropUnique('tg_groups_conn_group_unique');
                } catch (\Throwable $e) {
                    if (! str_contains($e->getMessage(), 'doesn\'t exist')
                        && ! str_contains($e->getMessage(), 'Unknown key')) {
                        throw $e;
                    }
                }
            });

            if (! Schema::hasColumn('telegram_groups', 'channel')) {
                Schema::table('telegram_groups', function (Blueprint $table) {
                    $table->string('channel', 20)->default('telegram')->after('organization_id');
                });
            }

            DB::table('telegram_groups')->whereNull('channel')->update(['channel' => 'telegram']);

            if ($isMysqlFamily) {
                DB::statement('ALTER TABLE telegram_groups MODIFY telegram_user_connection_id BIGINT UNSIGNED NULL');
                Schema::table('telegram_groups', function (Blueprint $table) {
                    $table->foreign('telegram_user_connection_id')
                        ->references('id')
                        ->on('telegram_user_connections')
                        ->cascadeOnDelete();
                });
            }
        }

        Schema::table('telegram_groups', function (Blueprint $table) {
            try {
                $table->unique(
                    ['organization_id', 'channel', 'telegram_group_id'],
                    'tg_org_channel_group_unique'
                );
            } catch (\Throwable $e) {
                if (! str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_groups')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);

        Schema::table('telegram_groups', function (Blueprint $table) {
            try {
                $table->dropUnique('tg_org_channel_group_unique');
            } catch (\Throwable $e) {
                if (! str_contains($e->getMessage(), 'doesn\'t exist')
                    && ! str_contains($e->getMessage(), 'no such index')) {
                    throw $e;
                }
            }
        });

        if (Schema::hasColumn('telegram_groups', 'channel')) {
            Schema::table('telegram_groups', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }

        DB::table('telegram_groups')->whereNull('telegram_user_connection_id')->delete();

        if ($isMysqlFamily) {
            Schema::table('telegram_groups', function (Blueprint $table) {
                $table->dropForeign(['telegram_user_connection_id']);
            });
            DB::statement('ALTER TABLE telegram_groups MODIFY telegram_user_connection_id BIGINT UNSIGNED NOT NULL');
            Schema::table('telegram_groups', function (Blueprint $table) {
                $table->foreign('telegram_user_connection_id')
                    ->references('id')
                    ->on('telegram_user_connections')
                    ->cascadeOnDelete();
            });
        } elseif ($driver === 'sqlite') {
            $this->sqliteRebuildWithNotNullConnection();
        }

        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->unique(
                ['telegram_user_connection_id', 'telegram_group_id'],
                'tg_groups_conn_group_unique'
            );
        });
    }

    private function sqliteRebuildWithNullableConnection(): void
    {
        Schema::rename('telegram_groups', 'telegram_groups_old');

        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('telegram');
            $table->foreignId('telegram_user_connection_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('telegram_group_id', 50)->index();
            $table->string('title')->nullable();
            $table->string('type', 20)->nullable();
            $table->foreignId('telegram_group_category_id')->nullable()->constrained('telegram_group_categories')->nullOnDelete();
            $table->string('language', 10)->nullable();
            $table->unsignedInteger('member_count')->nullable();
            $table->string('public_username', 64)->nullable();
            $table->string('public_link', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('can_post')->default(true);
            $table->string('last_error')->nullable();
            $table->unsignedBigInteger('last_crawled_message_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $oldCols = Schema::getColumnListing('telegram_groups_old');
        $hasCategory = in_array('telegram_group_category_id', $oldCols, true);

        $selectParts = [
            'id',
            'organization_id',
            "'telegram' AS channel",
            'telegram_user_connection_id',
            'telegram_group_id',
            'title',
            'type',
        ];
        if ($hasCategory) {
            $selectParts[] = 'telegram_group_category_id';
        } else {
            $selectParts[] = 'NULL AS telegram_group_category_id';
        }
        $selectParts = array_merge($selectParts, [
            'language',
            'member_count',
            'public_username',
            'public_link',
            'description',
            'can_post',
            'last_error',
            'last_crawled_message_id',
            'last_synced_at',
            'is_active',
            'created_at',
            'updated_at',
        ]);

        $sql = 'INSERT INTO telegram_groups ('.implode(', ', [
            'id', 'organization_id', 'channel', 'telegram_user_connection_id', 'telegram_group_id',
            'title', 'type', 'telegram_group_category_id', 'language', 'member_count', 'public_username',
            'public_link', 'description', 'can_post', 'last_error', 'last_crawled_message_id',
            'last_synced_at', 'is_active', 'created_at', 'updated_at',
        ]).') SELECT '.implode(', ', $selectParts).' FROM telegram_groups_old';

        DB::statement($sql);

        Schema::drop('telegram_groups_old');
    }

    private function sqliteRebuildWithNotNullConnection(): void
    {
        Schema::rename('telegram_groups', 'telegram_groups_old');

        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_user_connection_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_group_id', 50)->index();
            $table->string('title')->nullable();
            $table->string('type', 20)->nullable();
            $table->foreignId('telegram_group_category_id')->nullable()->constrained('telegram_group_categories')->nullOnDelete();
            $table->string('language', 10)->nullable();
            $table->unsignedInteger('member_count')->nullable();
            $table->string('public_username', 64)->nullable();
            $table->string('public_link', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('can_post')->default(true);
            $table->string('last_error')->nullable();
            $table->unsignedBigInteger('last_crawled_message_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $oldCols = Schema::getColumnListing('telegram_groups_old');
        $hasChannel = in_array('channel', $oldCols, true);
        $hasCategory = in_array('telegram_group_category_id', $oldCols, true);

        $selectParts = [
            'id',
            'organization_id',
            'telegram_user_connection_id',
            'telegram_group_id',
            'title',
            'type',
        ];
        if ($hasCategory) {
            $selectParts[] = 'telegram_group_category_id';
        } else {
            $selectParts[] = 'NULL AS telegram_group_category_id';
        }
        $selectParts = array_merge($selectParts, [
            'language',
            'member_count',
            'public_username',
            'public_link',
            'description',
            'can_post',
            'last_error',
            'last_crawled_message_id',
            'last_synced_at',
            'is_active',
            'created_at',
            'updated_at',
        ]);

        $from = 'telegram_groups_old';
        if ($hasChannel) {
            $from = "(SELECT * FROM telegram_groups_old WHERE COALESCE(channel, 'telegram') = 'telegram') AS sub";
        }

        $sql = 'INSERT INTO telegram_groups ('.implode(', ', [
            'id', 'organization_id', 'telegram_user_connection_id', 'telegram_group_id',
            'title', 'type', 'telegram_group_category_id', 'language', 'member_count', 'public_username',
            'public_link', 'description', 'can_post', 'last_error', 'last_crawled_message_id',
            'last_synced_at', 'is_active', 'created_at', 'updated_at',
        ]).') SELECT '.implode(', ', $selectParts).' FROM '.$from;

        DB::statement($sql);

        Schema::drop('telegram_groups_old');
    }
};
