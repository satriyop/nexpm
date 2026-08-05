<?php

use App\Services\PhotoUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('non-webp photos are stored as-is', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('site.jpg');

    $path = app(PhotoUploadService::class)->store($file, 'bast');

    expect($path)->toEndWith('.jpg');
    Storage::disk('public')->assertExists($path);
});

test('webp photos are converted to jpeg on store', function () {
    Storage::fake('public');

    $image = imagecreatetruecolor(200, 150);
    imagefilledrectangle($image, 0, 0, 199, 149, imagecolorallocate($image, 10, 100, 200));
    $tmpPath = tempnam(sys_get_temp_dir(), 'photo-upload-test').'.webp';
    imagewebp($image, $tmpPath);
    imagedestroy($image);

    $file = new UploadedFile($tmpPath, 'photo.webp', 'image/webp', null, true);

    $path = app(PhotoUploadService::class)->store($file, 'bast');

    expect($path)->toEndWith('.jpg');
    Storage::disk('public')->assertExists($path);
    expect(exif_imagetype(Storage::disk('public')->path($path)))->toBe(IMAGETYPE_JPEG);

    unlink($tmpPath);
});
