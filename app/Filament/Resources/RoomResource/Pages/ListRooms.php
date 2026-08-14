<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Filament\Resources\RoomUnitResource;
use App\Services\KuturogiSyncService;
use App\Services\RoomSortOrderService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    public function getTitle(): string
    {
        return '客室タイプ設定';
    }

    public function reorderTable(array $order): void
    {
        if (! auth()->user()?->isAdmin()) {
            return;
        }

        parent::reorderTable($order);

        try {
            app(RoomSortOrderService::class)->syncAllToKuturogi();

            Notification::make()
                ->title('並び順を kuturogi の HP に反映しました')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('並び順の kuturogi 反映に失敗しました')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('units')
                ->label('客室管理一覧へ')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(RoomUnitResource::getUrl('index')),
            Actions\Action::make('sync')
                ->label('kuturogi から同期')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->action(function (KuturogiSyncService $syncService) {
                    $rooms = $syncService->syncRooms();
                    $plans = $syncService->syncPlans();
                    $inventories = $syncService->syncInventories();

                    Notification::make()
                        ->title('同期完了')
                        ->body("客室 {$rooms} / プラン {$plans} / 在庫 {$inventories} 件")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()->label('客室タイプを追加'),
        ];
    }
}
