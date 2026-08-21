<?php

namespace App\Filament\Resources\RoomFeatureOptionResource\Pages;

use App\Filament\Resources\RoomFeatureOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomFeatureOption extends CreateRecord
{
    protected static string $resource = RoomFeatureOptionResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'アピールポイントを追加しました';
    }
}
