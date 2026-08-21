<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Concerns\ScrollsToTop;
use App\Filament\Resources\RoomResource;
use App\Filament\Resources\RoomResource\Pages\Concerns\HandlesRoomImages;
use App\Services\KuturogiSyncService;
use App\Services\RoomInventoryService;
use App\Support\RoomDetails;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    use HandlesRoomImages;
    use ScrollsToTop;

    protected static string $resource = RoomResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return '客室を登録しました';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractRoomImagesFromFormData($data);
        $data['details'] = RoomDetails::normalize($data['details'] ?? []);
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

            try {
                $pruned = $syncService->pruneUnlinkedKuturogiRooms();
            } catch (\Throwable) {
                $pruned = ['deleted' => 0, 'unpublished' => 0];
            }

            $body = $inventoryCount > 0 ? "在庫 {$inventoryCount} 日分を登録しました" : null;

            if (($pruned['deleted'] ?? 0) > 0 || ($pruned['unpublished'] ?? 0) > 0) {
                $body = trim(($body ? $body.'。' : '')."kuturogi の未連携客室を削除 {$pruned['deleted']} / 非公開 {$pruned['unpublished']} 件");
            }

            Notification::make()
                ->title('kuturogi のお部屋一覧へ反映しました')
                ->body($body)
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

    public function create(bool $another = false): void
    {
        parent::create($another);

        if ($another) {
            $this->scrollToTop();
        }
    }
}
