<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class PlanImageService
{
    public function __construct(
        protected PlanImageStorageService $storage,
    ) {}

    /**
     * @param  array<int, UploadedFile|TemporaryUploadedFile|string>  $state
     * @param  list<string>  $previousImages
     * @return list<string>
     */
    public function syncFromFormState(Plan $plan, array $state, array $previousImages = []): array
    {
        if (count($state) > 5) {
            throw new RuntimeException('画像は最大5枚までです。');
        }

        if (! $this->canWriteDirectly()) {
            throw new RuntimeException('kuturogi の画像ディレクトリへ書き込めません。KUTUROGI_PUBLIC_IMAGES_PATH を確認してください。');
        }

        if ($state === []) {
            $this->deletePlanImages($plan);

            return [];
        }

        $planId = $plan->kuturogi_plan_id ?? $plan->id;

        return $this->storage->syncImages($planId, $state, $previousImages);
    }

    public function deletePlanImages(Plan $plan): void
    {
        if (! $this->canWriteDirectly()) {
            return;
        }

        $planId = $plan->kuturogi_plan_id ?? $plan->id;
        $this->storage->deletePlanImages($planId, $plan->images ?? []);
    }

    public function canWriteDirectly(): bool
    {
        $root = config('filesystems.disks.kuturogi_images.root');

        return is_string($root)
            && is_dir($root)
            && is_writable($root);
    }
}
