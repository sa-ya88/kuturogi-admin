<?php

namespace App\Events;

use App\Models\RoomInventory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RoomInventory $inventory,
    ) {}
}
