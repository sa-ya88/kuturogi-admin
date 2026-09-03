<?php

namespace App\Filament\Resources\RoomResource\Pages\Concerns;

use App\Models\Room;
use App\Services\RoomImageService;

trait HandlesRoomImages
{
    protected array $pendingRoomImages = [];

    protected function extractRoomImagesFromFormData(array $data): array
    {
        $this->pendingRoomImages = $data['images'] ?? [];
        unset($data['images']);
        $data['images'] = null;

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

    protected function persistPendingRoomImages(Room $room): void
    {
        if ($this->pendingRoomImages === []) {
            return;
        }

        $images = app(RoomImageService::class)->syncFromFormState(
            $room,
            $this->pendingRoomImages,
            $room->images ?? []
        );

        $room->update(['images' => $images]);
        $this->pendingRoomImages = [];
    }
}
