<?php

namespace App\Console\Commands;

use App\Models\AssignmentBastPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillBastPhotosToJpeg extends Command
{
    protected $signature = 'bast-photos:backfill-to-jpeg
                            {--dry-run : Preview updates without saving}';

    protected $description = 'Re-encode already-stored BAST photos that are not GIF/JPEG/PNG/BMP (e.g. WebP) to JPEG, since PhpSpreadsheet cannot embed them into BAST Excel reports.';

    private const SUPPORTED_TYPES = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $photos = AssignmentBastPhoto::query()->orderBy('id')->get();

        if ($photos->isEmpty()) {
            $this->info('No BAST photos found.');

            return self::SUCCESS;
        }

        $converted = 0;
        $missing = 0;
        $skipped = 0;

        foreach ($photos as $photo) {
            $absolutePath = storage_path('app/public/'.$photo->photo_path);

            if (! file_exists($absolutePath)) {
                $missing++;

                continue;
            }

            $imageType = exif_imagetype($absolutePath);

            if (in_array($imageType, self::SUPPORTED_TYPES, true)) {
                $skipped++;

                continue;
            }

            if ($imageType !== IMAGETYPE_WEBP) {
                $this->warn("Photo #{$photo->id} ({$photo->photo_path}) has an unhandled image type ({$imageType}); skipping.");

                continue;
            }

            $this->line("Converting photo #{$photo->id}: {$photo->photo_path}");

            if ($isDryRun) {
                $converted++;

                continue;
            }

            $image = imagecreatefromwebp($absolutePath);

            ob_start();
            imagejpeg($image, null, 90);
            $contents = ob_get_clean();
            imagedestroy($image);

            $newPath = 'bast/'.Str::random(40).'.jpg';
            $disk->put($newPath, $contents);
            $disk->delete($photo->photo_path);

            $photo->update(['photo_path' => $newPath]);

            $converted++;
        }

        $this->info(sprintf(
            '%s%d converted, %d already supported, %d missing files.',
            $isDryRun ? '[dry-run] ' : '',
            $converted,
            $skipped,
            $missing
        ));

        return self::SUCCESS;
    }
}
