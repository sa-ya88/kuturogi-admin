<?php

namespace App\Filament\Resources\PlanResource\Pages\Concerns;

use App\Models\Plan;
use App\Services\PlanImageService;

trait HandlesPlanImages
{
    protected array $pendingPlanImages = [];

    protected function extractPlanImagesFromFormData(array $data): array
    {
        $this->pendingPlanImages = $data['images'] ?? [];
        unset($data['images']);
        $data['images'] = [];

        return $data;
    }

    protected function stripImagePathsForForm(?array $images): array
    {
        if ($images === null || $images === []) {
            return [];
        }

        return array_map(
            fn (string $path): string => basename($path),
            $images
        );
    }

    protected function persistPendingPlanImages(Plan $plan): void
    {
        if ($this->pendingPlanImages === []) {
            return;
        }

        $images = app(PlanImageService::class)->syncFromFormState(
            $plan,
            $this->pendingPlanImages,
            $plan->images ?? []
        );

        $plan->update(['images' => $images]);
        $this->pendingPlanImages = [];
    }
}
