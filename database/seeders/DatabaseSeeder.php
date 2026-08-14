<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@kuturogi.local'],
            [
                'name' => 'Admin',
                'login_id' => User::generateLoginId(),
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        if (blank($user->login_id)) {
            $user->update(['login_id' => User::generateLoginId()]);
        }

        if (! $user->isAdmin()) {
            $user->update(['role' => User::ROLE_ADMIN]);
        }
    }
}
