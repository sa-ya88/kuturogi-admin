<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class RoomImageService
{
    public function __construct(
        protected KuturogiApiClient $apiClient,
        protected RoomImageStorageService $storage,
    ) {}

    public function syncFromFormState(Room $room, array $state, array $previousImages = []): array
    {
        if (count($state) > 5) {
            throw new RuntimeException('画像は最大5枚までです。');
        }

        if ($state === []) {
            $this->deleteRoomImages($room);

            return [];
        }

        $roomId = $room->kuturogi_room_id ?? $room->id;

        if ($this->canWriteDirectly()) {
            return $this->storage->syncImages($roomId, $state, $previousImages);
        }

        if (! $room->kuturogi_room_id) {
            throw new RuntimeException('kuturogi の客室 ID が未取得のため、画像を保存できません。');
        }

        return $this->syncViaApi($room, $state);
    }

    public function deleteRoomImages(Room $room): void
    {
        $roomId = $room->kuturogi_room_id ?? $room->id;
        $this->storage->deleteRoomImages($roomId, $room->images ?? []);
    }

    public function canWriteDirectly(): bool
    {
        $root = config('filesystems.disks.kuturogi_images.root');

        return is_string($root)
            && is_dir($root)
            && is_writable($root);
    }

    private function syncViaApi(Room $room, array $state): array
    {
        $response = $this->uploadRoomImages($room->kuturogi_room_id, $this->resolveUploadFiles($state));
        $response->throw();

        return $response->json('images') ?? [];
    }

    private function resolveUploadFiles(array $state): array
    {
        $files = [];

        foreach ($state as $item) {
            if ($item instanceof UploadedFile || $item instanceof TemporaryUploadedFile) {
                $files[] = $item;

                continue;
            }

            $basename = basename(str_replace('/images/', '', (string) $item));
            $disk = Storage::disk('kuturogi_images');

            if ($disk->exists($basename)) {
                $files[] = new UploadedFile(
                    $disk->path($basename),
                    $basename,
                    mime_content_type($disk->path($basename)) ?: null,
                    null,
                    true
                );

                continue;
            }

            $url = rtrim(config('kuturogi.base_url'), '/').'/images/'.$basename;
            $response = Http::timeout((int) config('kuturogi.timeout', 10))->get($url);
            $response->throw();

            $tempPath = tempnam(sys_get_temp_dir(), 'room_image_');
            file_put_contents($tempPath, $response->body());

            $files[] = new UploadedFile(
                $tempPath,
                $basename,
                $response->header('Content-Type'),
                null,
                true
            );
        }

        return $files;
    }

    public function uploadRoomImages(int $kuturogiRoomId, array $files): Response
    {
        $request = $this->apiClient->clientForMultipart();

        foreach ($files as $index => $file) {
            $request = $request->attach(
                "images[{$index}]",
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName()
            );
        }

        return $request->put(
            config('kuturogi.endpoints.rooms')."/{$kuturogiRoomId}/images"
        );
    }
}
