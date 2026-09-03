<?php

use App\Http\Controllers\Api\KuturogiWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/kuturogi')
    ->middleware('kuturogi.webhook')
    ->group(function () {
        Route::post('/reservations', [KuturogiWebhookController::class, 'reservationCreated'])
            ->name('webhooks.kuturogi.reservations.created');

        Route::post('/reservations/cancelled', [KuturogiWebhookController::class, 'reservationCancelled'])
            ->name('webhooks.kuturogi.reservations.cancelled');

        Route::post('/users', [KuturogiWebhookController::class, 'userRegistered'])
            ->name('webhooks.kuturogi.users.registered');
    });
