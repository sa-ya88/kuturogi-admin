<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Filament\Resources\RoomResource\Pages\Concerns\HandlesRoomImages;
use App\Models\Room;
use App\Services\KuturogiSyncService;
use App\Services\RoomAvailabilitySnapshot;
use App\Services\RoomImageService;
use App\Services\RoomInventoryService;
use App\Support\RoomDetails;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditRoom extends EditRecord
{
    use HandlesRoomImages;

    protected static string $resource = RoomResource::class;

    protected ?RoomAvailabilitySnapshot $previousAvailability = null;

    protected function getSavedNotificationTitle(): ?string
    {
        return '客室を更新しました';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['images'] = $this->stripImagePathsForForm($data['images'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousAvailability = RoomAvailabilitySnapshot::fromRoom($this->record);

        $data['images'] = app(RoomImageService::class)->syncFromFormState(
            $this->record,
            $data['images'] ?? [],
            $this->record->images ?? []
        );

        $data['stock_count'] = $this->record->inServiceUnitsCount();
        $data['details'] = RoomDetails::normalize($data['details'] ?? []);

        return $data;
    }

    protected function afterSave(): void
    {
        try {
            $room = $this->record->fresh(['plans']);
            app(KuturogiSyncService::class)->pushRoomToKuturogi($room);

            $inventoryCount = app(RoomInventoryService::class)->syncInventoriesForRoom(
                $room,
                $this->previousAvailability
            );

            $notification = Notification::make()
                ->title('kuturogi のお部屋一覧へ反映しました')
                ->success();

            if ($inventoryCount > 0) {
                $notification->body("在庫 {$inventoryCount} 日分を更新しました");
            }

            $notification->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('kuturogi への自動反映に失敗しました')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalDescription('予約履歴（過去・キャンセル済みを含む）がある客室タイプは削除できません。サイトから外す場合は「公開」をOFFにしてください。')
                ->action(function (Room $record) {
                    try {
                        app(KuturogiSyncService::class)->deleteRoomWithSync($record);

                        Notification::make()
                            ->title('客室を削除し、kuturogi からも削除しました')
                            ->success()
                            ->send();

                        $this->redirect(RoomResource::getUrl('index'));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('削除できません')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
        ];
    }
}
