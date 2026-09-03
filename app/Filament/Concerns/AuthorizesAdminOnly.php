<?php

namespace App\Filament\Concerns;

use App\Support\DemoMode;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesAdminOnly
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return DemoMode::allowsDeletes()
            && (auth()->user()?->isAdmin() ?? false);
    }

    public static function canDeleteAny(): bool
    {
        return DemoMode::allowsDeletes()
            && (auth()->user()?->isAdmin() ?? false);
    }
}
