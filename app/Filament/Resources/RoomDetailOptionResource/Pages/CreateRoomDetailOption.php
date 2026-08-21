<?php

namespace App\Filament\Resources\RoomDetailOptionResource\Pages;

use App\Filament\Resources\RoomDetailOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomDetailOption extends CreateRecord
{
    protected static string $resource = RoomDetailOptionResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return '設備・アメニティを追加しました';
    }
}
