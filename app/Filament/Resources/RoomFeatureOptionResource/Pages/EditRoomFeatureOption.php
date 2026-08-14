<?php

namespace App\Filament\Resources\RoomFeatureOptionResource\Pages;

use App\Filament\Resources\RoomFeatureOptionResource;
use App\Models\RoomFeatureOption;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditRoomFeatureOption extends EditRecord
{
    protected static string $resource = RoomFeatureOptionResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return '設備・特徴を更新しました';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (RoomFeatureOption $record): void {
                    if ($record->isUsedByRooms()) {
                        Notification::make()
                            ->title('削除できません')
                            ->body('この設備・特徴は客室で使用中です。無効化してください。')
                            ->danger()
                            ->send();

                        throw new Halt();
                    }
                }),
        ];
    }
}
