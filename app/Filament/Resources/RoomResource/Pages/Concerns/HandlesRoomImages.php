<?php

namespace App\Filament\Resources\RoomResource\Pages\Concerns;

trait HandlesRoomImages
{
    /** @var array<int, mixed> */
    protected array $pendingRoomImages = [];

    protected function extractRoomImagesFromFormData(array $data): array
    {
        $this->pendingRoomImages = $data['images'] ?? [];
        unset($data['images']);
        $data['images'] = null;

        return $data;
    }

    /**
     * @param  list<string>|null  $images
     * @return list<string>
     */
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

    protected function persistPendingRoomImages(\App\Models\Room $room): void
    {
        if ($this->pendingRoomImages === []) {
            return;
        }

        $images = app(\App\Services\RoomImageService::class)->syncFromFormState(
            $room,
            $this->pendingRoomImages,
            $room->images ?? []
        );

        $room->update(['images' => $images]);
        $this->pendingRoomImages = [];
    }
}
