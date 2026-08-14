<?php

namespace App\Filament\Resources\RoomUnitResource\Pages;

use App\Filament\Resources\RoomUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomUnit extends CreateRecord
{
    protected static string $resource = RoomUnitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
