<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * kuturogi の public/images 配下へ客室画像を保存する。
 */
class RoomImageStorageService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['webp', 'png', 'jpeg', 'jpg'];

    public function imagesDirectory(): string
    {
        return config('filesystems.disks.kuturogi_images.root')
            ?? throw new RuntimeException('kuturogi_images disk is not configured.');
    }

    private function tempDirectory(): string
    {
        $directory = storage_path('app/room-image-tmp');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }

    public function filename(int $roomId, int $sequence, string $extension): string
    {
        return sprintf('room_%d_%d.%s', $roomId, $sequence, $this->normalizeExtension($extension));
    }

    public function publicPath(string $filename): string
    {
        return '/images/'.$filename;
    }

    /**
     * @param  list<UploadedFile|TemporaryUploadedFile|string>  $items
     * @param  list<string>  $previousImages
     * @return list<string>
     */
    public function syncImages(int $roomId, array $items, array $previousImages = []): array
    {
        if (count($items) > 5) {
            throw new RuntimeException('画像は最大5枚までです。');
        }

        $directory = $this->imagesDirectory();

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $tempFiles = [];

        foreach ($items as $index => $item) {
            $sequence = $index + 1;
            $extension = $this->resolveExtension($item);
            $this->assertAllowedExtension($extension);

            $finalName = $this->filename($roomId, $sequence, $extension);
            $tempPath = $this->tempDirectory().'/'.Str::uuid().'.'.$extension;
            $finalPath = $directory.'/'.$finalName;

            if ($item instanceof UploadedFile || $item instanceof TemporaryUploadedFile) {
                File::put($tempPath, $item->get());
            } else {
                $sourcePath = $this->resolveSourcePath($directory, (string) $item);

                if (! File::exists($sourcePath)) {
                    throw new RuntimeException("画像ファイルが見つかりません: {$item}");
                }

                if ($sourcePath === $finalPath) {
                    $tempFiles[] = [
                        'temp' => null,
                        'final' => $finalPath,
                        'public' => $this->publicPath($finalName),
                    ];

                    continue;
                }

                File::move($sourcePath, $tempPath);
            }

            $tempFiles[] = [
                'temp' => $tempPath,
                'final' => $finalPath,
                'public' => $this->publicPath($finalName),
            ];
        }

        $finalNames = array_map(
            fn (array $file): string => basename($file['final']),
            $tempFiles
        );

        $this->deleteRoomImageFiles($roomId, $finalNames);

        $publicPaths = [];

        foreach ($tempFiles as $file) {
            if ($file['temp'] === null) {
                $publicPaths[] = $file['public'];

                continue;
            }

            if (File::exists($file['final'])) {
                File::delete($file['final']);
            }

            File::move($file['temp'], $file['final']);
            $publicPaths[] = $file['public'];
        }

        foreach ($previousImages as $oldPath) {
            if (in_array($oldPath, $publicPaths, true)) {
                continue;
            }

            $oldFile = $directory.'/'.basename($oldPath);

            if (File::exists($oldFile)) {
                File::delete($oldFile);
            }
        }

        return $publicPaths;
    }

    /**
     * @param  list<string>  $images
     */
    public function deleteRoomImages(int $roomId, array $images = []): void
    {
        $this->deleteRoomImageFiles($roomId);

        $directory = $this->imagesDirectory();

        foreach ($images as $path) {
            $file = $directory.'/'.basename($path);

            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    /**
     * @param  list<string>  $keepFilenames
     */
    private function deleteRoomImageFiles(int $roomId, array $keepFilenames = []): void
    {
        $directory = $this->imagesDirectory();

        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::glob($directory.'/room_'.$roomId.'_*') ?: [] as $path) {
            $basename = basename($path);

            if (! in_array($basename, $keepFilenames, true)) {
                File::delete($path);
            }
        }

        $this->cleanupLegacyTempFilesInImagesDirectory($roomId);
    }

    private function cleanupLegacyTempFilesInImagesDirectory(int $roomId): void
    {
        $directory = $this->imagesDirectory();

        foreach (File::glob($directory.'/room_'.$roomId.'_*_tmp_*') ?: [] as $path) {
            File::delete($path);
        }
    }

    private function resolveSourcePath(string $directory, string $item): string
    {
        return $directory.'/'.$this->normalizeBasename($item);
    }

    private function normalizeBasename(string $item): string
    {
        return ltrim(str_replace('/images/', '', basename($item)), '/');
    }

    private function resolveExtension(UploadedFile|TemporaryUploadedFile|string $item): string
    {
        if ($item instanceof UploadedFile || $item instanceof TemporaryUploadedFile) {
            $extension = $item->getClientOriginalExtension()
                ?: $item->extension()
                ?: 'webp';

            return $this->normalizeExtension($extension);
        }

        $extension = pathinfo($this->normalizeBasename($item), PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new RuntimeException("画像の拡張子を判定できません: {$item}");
        }

        return $this->normalizeExtension($extension);
    }

    private function normalizeExtension(string $extension): string
    {
        return strtolower(ltrim($extension, '.'));
    }

    private function assertAllowedExtension(string $extension): void
    {
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('webp / png / jpeg のみアップロードできます。');
        }
    }
}
