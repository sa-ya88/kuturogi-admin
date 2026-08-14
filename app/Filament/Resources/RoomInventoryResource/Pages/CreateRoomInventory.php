<?php

namespace App\Filament\Resources\RoomInventoryResource\Pages;

use App\Filament\Resources\RoomInventoryResource;
use App\Services\KuturogiSyncService;

class CreateRoomInventory extends \Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = RoomInventoryResource::class;

    protected function afterCreate(): void
    {
        app(KuturogiSyncService::class)->pushInventoryToKuturogi($this->record);
    }
}
