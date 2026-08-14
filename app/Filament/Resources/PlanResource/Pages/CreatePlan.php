<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Filament\Resources\PlanResource\Pages\Concerns\NormalizesPlanFormData;
use App\Services\KuturogiSyncService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    use NormalizesPlanFormData;

    protected static string $resource = PlanResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'プランを追加しました';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizePlanFormData($data);
    }

    protected function afterCreate(): void
    {
        try {
            app(KuturogiSyncService::class)->pushPlanToKuturogi($this->record->fresh(['rooms']));

            Notification::make()
                ->title('kuturogi へプランを反映しました')
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
