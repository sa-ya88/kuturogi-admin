<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\KuturogiApiClient::class);
        $this->app->singleton(\App\Services\KuturogiSyncService::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\InventoryUpdated::class,
            \App\Listeners\LogInventoryUpdate::class,
        );
    }
}
