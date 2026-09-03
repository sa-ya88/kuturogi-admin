<?php

namespace App\Providers;

use App\Events\InventoryUpdated;
use App\Listeners\LogInventoryUpdate;
use App\Services\KuturogiApiClient;
use App\Services\KuturogiSyncService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KuturogiApiClient::class);
        $this->app->singleton(KuturogiSyncService::class);
    }

    public function boot(): void
    {
        Event::listen(
            InventoryUpdated::class,
            LogInventoryUpdate::class,
        );
    }
}
