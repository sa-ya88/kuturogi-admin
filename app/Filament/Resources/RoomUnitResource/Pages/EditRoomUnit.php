<?php

namespace App\Filament\Resources\RoomUnitResource\Pages;

use App\Filament\Resources\RoomUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoomUnit extends EditRecord
{
    protected static string $resource = RoomUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
