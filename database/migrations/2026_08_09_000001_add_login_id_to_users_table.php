<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_id', 7)->nullable()->after('name');
        });

        $existing = [];

        foreach (DB::table('users')->orderBy('id')->pluck('id') as $id) {
            do {
                $loginId = 'k'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (isset($existing[$loginId]));

            $existing[$loginId] = true;

            DB::table('users')->where('id', $id)->update([
                'login_id' => $loginId,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('login_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_id']);
            $table->dropColumn('login_id');
        });
    }
};
