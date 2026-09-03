<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('users')
            ->whereNotNull('login_id')
            ->pluck('login_id')
            ->filter(fn (?string $loginId): bool => is_string($loginId) && preg_match('/^k\d{6}$/', $loginId))
            ->flip()
            ->all();

        foreach (DB::table('users')->orderBy('id')->get(['id', 'login_id']) as $user) {
            if (is_string($user->login_id) && preg_match('/^k\d{6}$/', $user->login_id)) {
                continue;
            }

            do {
                $loginId = 'k'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (isset($existing[$loginId]));

            $existing[$loginId] = true;

            DB::table('users')->where('id', $user->id)->update([
                'login_id' => $loginId,
            ]);
        }
    }

    public function down(): void {}
};
