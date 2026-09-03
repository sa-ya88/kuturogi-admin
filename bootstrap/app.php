<?php

use App\Console\Commands\RefreshDemoDataCommand;
use App\Console\Commands\SyncKuturogiCommand;
use App\Http\Middleware\AllowFilamentAssetCors;
use App\Http\Middleware\VerifyKuturogiWebhook;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SyncKuturogiCommand::class,
        RefreshDemoDataCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->trustProxies(at: '*');
        $middleware->append(AllowFilamentAssetCors::class);
        $middleware->alias([
            'kuturogi.webhook' => VerifyKuturogiWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
