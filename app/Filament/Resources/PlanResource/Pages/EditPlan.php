<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Filament\Resources\PlanResource\Pages\Concerns\HandlesPlanImages;
use App\Filament\Resources\PlanResource\Pages\Concerns\NormalizesPlanFormData;
use App\Models\Plan;
use App\Services\KuturogiSyncService;
use App\Services\PlanImageService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPlan extends EditRecord
{
    use HandlesPlanImages;
    use NormalizesPlanFormData;

    protected static string $resource = PlanResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return 'プランを更新しました';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['images'] = $this->stripImagePathsForForm($data['images'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizePlanFormData($data);

        $data['images'] = app(PlanImageService::class)->syncFromFormState(
            $this->record,
            $data['images'] ?? [],
            $this->record->images ?? []
        );

        return $data;
    }

    protected function afterSave(): void
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalDescription('予約履歴（過去・キャンセル済みを含む）があるプランは削除できません。サイトから外す場合は「公開」をOFFにしてください。')
                ->action(function (Plan $record) {
                    try {
                        app(KuturogiSyncService::class)->deletePlanWithSync($record);

                        Notification::make()
                            ->title('プランを削除し、kuturogi からも削除しました')
                            ->success()
                            ->send();

                        $this->redirect(PlanResource::getUrl('index'));
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
