<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'staff_users';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const DEMO_EMAIL = 'demo@kuturogi.local';

    public const DEMO_LOGIN_ID = 'k000001';

    protected $fillable = [
        'name',
        'login_id',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->login_id)) {
                $user->login_id = static::generateLoginId();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function generateLoginId(): string
    {
        do {
            $loginId = 'k'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('login_id', $loginId)->exists());

        return $loginId;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isDemoGuest(): bool
    {
        return $this->email === self::DEMO_EMAIL
            || $this->login_id === self::DEMO_LOGIN_ID;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => '管理者',
            self::ROLE_STAFF => '一般',
            default => $this->role,
        };
    }
}
