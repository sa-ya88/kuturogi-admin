<?php

namespace App\Filament\Resources\RoomDetailOptionResource\Pages;

use App\Filament\Resources\RoomDetailOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoomDetailOptions extends ListRecords
{
    protected static string $resource = RoomDetailOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('項目を追加'),
        ];
    }
}
