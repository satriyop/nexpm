<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoUploadService
{
    /**
     * Store an uploaded photo, re-encoding WebP images to JPEG.
     *
     * PhpSpreadsheet's Drawing class (used to embed photos into BAST Excel
     * reports) only supports GIF/JPEG/PNG/BMP. WebP passes Laravel's
     * `image` validation rule but embeds inconsistently across Excel/
     * LibreOffice versions, so it's normalized here at upload time.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        if ($file->getMimeType() !== 'image/webp') {
            return $file->store($directory, $disk);
        }

        $image = imagecreatefromwebp($file->getRealPath());

        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        $path = trim($directory, '/').'/'.Str::random(40).'.jpg';

        Storage::disk($disk)->put($path, $contents);

        return $path;
    }
}
