<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasTable('staff_users')) {
            Schema::rename('users', 'staff_users');
        }

        if (Schema::hasTable('staff_users')) {
            $this->reindexStaffUsersTable();
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_kana')->nullable();
                $table->string('email')->unique('site_users_email_unique');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->date('birthday')->nullable();
                $table->string('gender')->nullable();
                $table->string('zip_code')->nullable();
                $table->string('address')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('reservations', 'guest_zip_code')) {
                $table->string('guest_zip_code')->nullable()->after('guest_email');
            }
            if (! Schema::hasColumn('reservations', 'guest_address')) {
                $table->string('guest_address')->nullable()->after('guest_zip_code');
            }
            if (! Schema::hasColumn('reservations', 'guest_building')) {
                $table->string('guest_building')->nullable()->after('guest_address');
            }
        });

        if (! Schema::hasTable('news')) {
            Schema::create('news', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->date('published_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        $this->importPublicSiteRows();
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'user_id')) {
                $table->dropColumn('user_id');
            }
            foreach (['guest_zip_code', 'guest_address', 'guest_building'] as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('news');

        if (Schema::hasTable('users') && Schema::hasTable('staff_users')) {
            Schema::drop('users');
            Schema::rename('staff_users', 'users');
        }
    }

    private function importPublicSiteRows(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $legacy = dirname(base_path()).'/kuturogi/database/database.sqlite';

        if (! is_file($legacy)) {
            return;
        }

        $shared = database_path('database.sqlite');

        if (is_file($shared) && realpath($legacy) === realpath($shared)) {
            return;
        }

        $pdo = new PDO('sqlite:'.$legacy, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->copyTable($pdo, 'users', [
            'id', 'name', 'name_kana', 'email', 'email_verified_at', 'password',
            'birthday', 'gender', 'zip_code', 'address', 'remember_token',
            'created_at', 'updated_at',
        ]);
        $this->copyTable($pdo, 'news', [
            'id', 'title', 'content', 'published_at', 'created_at', 'updated_at',
        ]);
    }

    private function copyTable(PDO $source, string $table, array $columns): void
    {
        $existing = $source->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ".$source->quote($table))->fetchColumn();

        if (! $existing) {
            return;
        }

        $rows = $source->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $payload = [];
            foreach ($columns as $column) {
                $payload[$column] = $row[$column] ?? null;
            }

            $unique = isset($payload['email'])
                ? ['email' => $payload['email']]
                : ['id' => $payload['id']];

            DB::table($table)->updateOrInsert($unique, $payload);
        }
    }

    private function reindexStaffUsersTable(): void
    {
        foreach (Schema::getIndexes('staff_users') as $index) {
            $name = $index['name'] ?? '';

            if (! is_string($name) || ! str_starts_with($name, 'users_')) {
                continue;
            }

            if (! empty($index['primary'])) {
                continue;
            }

            Schema::table('staff_users', function (Blueprint $table) use ($index, $name) {
                if (! empty($index['unique'])) {
                    $table->dropUnique($name);
                } else {
                    $table->dropIndex($name);
                }
            });
        }

        if (! $this->staffUsersHasIndex('staff_users_email_unique')) {
            Schema::table('staff_users', function (Blueprint $table) {
                $table->unique('email', 'staff_users_email_unique');
            });
        }

        if (
            Schema::hasColumn('staff_users', 'login_id')
            && ! $this->staffUsersHasIndex('staff_users_login_id_unique')
        ) {
            Schema::table('staff_users', function (Blueprint $table) {
                $table->unique('login_id', 'staff_users_login_id_unique');
            });
        }
    }

    private function staffUsersHasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('staff_users'))->contains(
            fn (array $index) => ($index['name'] ?? '') === $name
        );
    }
};
