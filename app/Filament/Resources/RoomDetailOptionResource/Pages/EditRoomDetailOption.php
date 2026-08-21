<?php

namespace App\Filament\Resources\RoomDetailOptionResource\Pages;

use App\Filament\Resources\RoomDetailOptionResource;
use App\Models\RoomDetailOption;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditRoomDetailOption extends EditRecord
{
    protected static string $resource = RoomDetailOptionResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return '設備・アメニティを更新しました';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (RoomDetailOption $record): void {
                    if ($record->isUsedByRooms()) {
                        Notification::make()
                            ->title('削除できません')
                            ->body('この項目は客室で使用中です。無効化してください。')
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
        ];
    }
}
