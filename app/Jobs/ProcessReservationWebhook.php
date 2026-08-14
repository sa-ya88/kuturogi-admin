<?php

namespace App\Jobs;

use App\Services\KuturogiSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessReservationWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $payload,
        public string $event,
    ) {}

    public function handle(KuturogiSyncService $syncService): void
    {
        if ($this->event === 'created') {
            $syncService->importReservation($this->payload);
            Log::info('Reservation imported from kuturogi.', ['id' => $this->payload['id'] ?? null]);

            return;
        }

        if ($this->event === 'cancelled' && isset($this->payload['id'])) {
            $syncService->cancelReservation((int) $this->payload['id']);
        }
    }
}
