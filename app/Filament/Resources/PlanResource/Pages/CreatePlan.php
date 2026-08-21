<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Concerns\ScrollsToTop;
use App\Filament\Resources\PlanResource;
use App\Filament\Resources\PlanResource\Pages\Concerns\HandlesPlanImages;
use App\Filament\Resources\PlanResource\Pages\Concerns\NormalizesPlanFormData;
use App\Services\KuturogiSyncService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    use HandlesPlanImages;
    use NormalizesPlanFormData;
    use ScrollsToTop;

    protected static string $resource = PlanResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'プランを追加しました';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizePlanFormData($data);

        return $this->extractPlanImagesFromFormData($data);
    }

    protected function afterCreate(): void
    {
        try {
            $syncService = app(KuturogiSyncService::class);
            $syncService->pushPlanToKuturogi($this->record->fresh(['rooms']));

            if ($this->pendingPlanImages !== []) {
                $this->persistPendingPlanImages($this->record->fresh());
                $syncService->pushPlanToKuturogi($this->record->fresh(['rooms']));
            }

            $pruned = $syncService->pruneUnlinkedKuturogiPlans();

            $notification = Notification::make()
                ->title('kuturogi へプランを反映しました')
                ->success();

            if (($pruned['deleted'] ?? 0) > 0 || ($pruned['detached'] ?? 0) > 0) {
                $notification->body("余剰プランを削除 {$pruned['deleted']} / 紐付け解除 {$pruned['detached']} 件");
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

    public function create(bool $another = false): void
    {
        parent::create($another);

        if ($another) {
            $this->scrollToTop();
        }
    }
}
