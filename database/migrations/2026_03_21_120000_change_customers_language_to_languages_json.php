<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('gender');
        });

        $rows = DB::table('customers')->select('id', 'language')->get();
        foreach ($rows as $row) {
            $lang = isset($row->language) ? trim((string) $row->language) : '';
            if ($lang === '') {
                continue;
            }
            DB::table('customers')->where('id', $row->id)->update([
                'languages' => json_encode([$lang]),
            ]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('language', 32)->nullable()->after('gender');
        });

        $rows = DB::table('customers')->select('id', 'languages')->get();
        foreach ($rows as $row) {
            $decoded = json_decode($row->languages ?? 'null', true);
            $first = is_array($decoded) && count($decoded) > 0 ? (string) $decoded[0] : null;
            if ($first !== null && $first !== '') {
                DB::table('customers')->where('id', $row->id)->update([
                    'language' => mb_substr($first, 0, 32),
                ]);
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('languages');
        });
    }
};
