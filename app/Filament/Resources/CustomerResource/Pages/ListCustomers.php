<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\KuturogiSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncCustomers')
                ->label('kuturogi から同期')
                ->icon('heroicon-o-arrow-path')
                ->action(function (KuturogiSyncService $syncService) {
                    $count = $syncService->syncCustomers();

                    Notification::make()
                        ->title('顧客同期完了')
                        ->body("{$count} 件を同期しました")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
