<?php

use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\Site;
use App\Models\SiteType;
use App\Services\BastReportExportService;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;

test('bast report photos are fitted inside their intended template area', function () {
    $photoPath = 'bast/export-placement-test.png';
    $absolutePhotoPath = storage_path('app/public/'.$photoPath);

    if (! is_dir(dirname($absolutePhotoPath))) {
        mkdir(dirname($absolutePhotoPath), 0755, true);
    }

    $image = imagecreatetruecolor(1200, 600);
    imagefilledrectangle($image, 0, 0, 1199, 599, imagecolorallocate($image, 30, 120, 220));
    imagepng($image, $absolutePhotoPath);
    imagedestroy($image);

    try {
        $assignment = Assignment::factory()
            ->bast()
            ->for(Site::factory()->state([
                'site_type_id' => SiteType::factory()->create(['name' => 'EVCS'])->id,
            ]))
            ->create();

        $bastData = AssignmentBastData::factory()->create(['assignment_id' => $assignment->id]);

        AssignmentBastPhoto::query()->create([
            'assignment_bast_data_id' => $bastData->id,
            'section' => 'required',
            'checkpoint_key' => 'kwh_kwh_meter',
            'photo_path' => $photoPath,
        ]);

        $spreadsheet = app(BastReportExportService::class)->generate($assignment);
        $sheet = $spreadsheet->getSheetByName('KWH,AC Panel, Cable');
        $drawing = $sheet?->getDrawingCollection()[0];

        expect($drawing->getCoordinates())->toBe('B3');

        $areaWidth = 0;
        foreach (['B', 'C', 'D'] as $column) {
            $areaWidth += SharedDrawing::cellDimensionToPixels(
                $sheet->getColumnDimension($column)->getWidth(),
                $spreadsheet->getDefaultStyle()->getFont()
            );
        }

        $areaHeight = SharedDrawing::pointsToPixels($sheet->getRowDimension(3)->getRowHeight());

        expect($drawing->getOffsetX())->toBeGreaterThanOrEqual(4)
            ->and($drawing->getOffsetY())->toBeGreaterThanOrEqual(4)
            ->and($drawing->getOffsetX() + $drawing->getWidth())->toBeLessThanOrEqual($areaWidth - 4)
            ->and($drawing->getOffsetY() + $drawing->getHeight())->toBeLessThanOrEqual($areaHeight - 4);
    } finally {
        if (file_exists($absolutePhotoPath)) {
            unlink($absolutePhotoPath);
        }
    }
});
