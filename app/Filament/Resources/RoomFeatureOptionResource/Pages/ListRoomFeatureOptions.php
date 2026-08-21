<?php

namespace App\Filament\Resources\RoomFeatureOptionResource\Pages;

use App\Filament\Resources\RoomFeatureOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoomFeatureOptions extends ListRecords
{
    protected static string $resource = RoomFeatureOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('アピールポイントを追加'),
        ];
    }
}
