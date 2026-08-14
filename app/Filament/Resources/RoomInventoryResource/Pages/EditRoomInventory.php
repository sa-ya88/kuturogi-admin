<?php

namespace App\Filament\Resources\RoomInventoryResource\Pages;

use App\Filament\Resources\RoomInventoryResource;
use App\Services\KuturogiSyncService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoomInventory extends EditRecord
{
    protected static string $resource = RoomInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        app(KuturogiSyncService::class)->pushInventoryToKuturogi($this->record);
    }
}
