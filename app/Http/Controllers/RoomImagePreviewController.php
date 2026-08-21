<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RoomImagePreviewController extends Controller
{
    public function __invoke(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless($request->user() !== null, 403);

        $basename = basename($filename);

        abort_unless((bool) preg_match('/^[A-Za-z0-9._-]+$/', $basename), 404);

        $disk = Storage::disk('kuturogi_images');

        abort_unless($disk->exists($basename), 404);

        return response()->file($disk->path($basename));
    }
}
