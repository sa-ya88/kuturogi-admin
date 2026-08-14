<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Filament\Resources\RoomResource\Pages\Concerns\HandlesRoomImages;
use App\Services\KuturogiSyncService;
use App\Services\RoomInventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    use HandlesRoomImages;

    protected static string $resource = RoomResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return '客室を登録しました';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractRoomImagesFromFormData($data);
        $data['stock_count'] = 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        $syncService = app(KuturogiSyncService::class);

        try {
            $syncService->pushRoomToKuturogi($this->record->fresh(['plans']));

            if ($this->pendingRoomImages !== []) {
                $this->persistPendingRoomImages($this->record->fresh());
            }

            $syncService->pushRoomToKuturogi($this->record->fresh(['plans']));

            $inventoryCount = app(RoomInventoryService::class)->syncInventoriesForRoom($this->record->fresh());

            Notification::make()
                ->title('kuturogi のお部屋一覧へ反映しました')
                ->body($inventoryCount > 0 ? "在庫 {$inventoryCount} 日分を登録しました" : null)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('kuturogi への自動反映に失敗しました')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
