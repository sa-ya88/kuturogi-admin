<?php

namespace App\Listeners;

use App\Events\InventoryUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2: WebSocket / Filament リアルタイム更新のフックポイント。
 */
class LogInventoryUpdate
{
    public function handle(InventoryUpdated $event): void
    {
        Log::info('Inventory updated and synced to kuturogi.', [
            'room_id' => $event->inventory->room_id,
            'date' => $event->inventory->date->format('Y-m-d'),
            'remains' => $event->inventory->remains,
        ]);
    }
}
