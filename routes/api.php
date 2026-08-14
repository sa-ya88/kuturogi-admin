<?php

use App\Http\Controllers\Api\KuturogiWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| kuturogi → kuturogi-admin Webhook 受信
|--------------------------------------------------------------------------
|
| kuturogi 顧客サイトから予約・在庫イベントを受信するエンドポイント。
| 署名検証: X-Kuturogi-Signature ヘッダ (HMAC-SHA256)
|
*/

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
