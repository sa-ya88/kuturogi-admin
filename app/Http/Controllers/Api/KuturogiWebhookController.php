<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessReservationWebhook;
use App\Services\KuturogiSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KuturogiWebhookController extends Controller
{
    public function reservationCreated(Request $request): JsonResponse
    {
        $payload = $request->input('payload', $request->all());

        ProcessReservationWebhook::dispatch($payload, 'created');

        return response()->json(['status' => 'accepted'], 202);
    }

    public function reservationCancelled(Request $request, KuturogiSyncService $syncService): JsonResponse
    {
        $payload = $request->input('payload', $request->all());
        $id = $payload['id'] ?? null;

        if (! $id) {
            abort(422, 'Reservation id is required.');
        }

        $syncService->cancelReservation((int) $id);

        return response()->json(['status' => 'ok']);
    }

    public function userRegistered(Request $request, KuturogiSyncService $syncService): JsonResponse
    {
        $payload = $request->input('payload', $request->all());

        $validated = validator($payload, [
            'id' => 'required|integer',
            'name' => 'required|string',
            'email' => 'required|email',
        ])->validate();

        $customer = $syncService->importCustomer($payload);

        return response()->json([
            'status' => 'ok',
            'customer_id' => $customer->id,
        ]);
    }
}
