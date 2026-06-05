<?php

use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\MainContractor;
use App\Models\Project;
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

test('bast report approval section uses main contractor details', function () {
    $mainContractor = MainContractor::factory()->create([
        'name' => 'CV Sigmatec',
        'pic' => 'Topan Gilas',
    ]);

    $project = Project::factory()->create([
        'main_contractor_id' => $mainContractor->id,
    ]);

    $assignment = Assignment::factory()
        ->bast()
        ->for(Site::factory()->state([
            'project_id' => $project->id,
            'site_type_id' => SiteType::factory()->create(['name' => 'EVCS'])->id,
        ]))
        ->create();

    AssignmentBastData::factory()->create(['assignment_id' => $assignment->id]);

    $spreadsheet = app(BastReportExportService::class)->generate($assignment);
    $sheet = $spreadsheet->getSheetByName('KWH,AC Panel, Cable');

    expect($sheet?->getCell('A30')->getValue())->toBe('Prepared By : Topan Gilas')
        ->and($sheet?->getCell('E30')->getValue())->toBe('CV Sigmatec');
});

test('bast report includes measurement photos in the testing section', function (string $siteType, array $checkpointKeys, array $expectedCoordinates) {
    $photoPath = "bast/{$siteType}-measurement-placement-test.png";
    $absolutePhotoPath = storage_path('app/public/'.$photoPath);

    if (! is_dir(dirname($absolutePhotoPath))) {
        mkdir(dirname($absolutePhotoPath), 0755, true);
    }

    $image = imagecreatetruecolor(800, 450);
    imagefilledrectangle($image, 0, 0, 799, 449, imagecolorallocate($image, 220, 120, 30));
    imagepng($image, $absolutePhotoPath);
    imagedestroy($image);

    try {
        $assignment = Assignment::factory()
            ->bast()
            ->for(Site::factory()->state([
                'site_type_id' => SiteType::factory()->create(['name' => $siteType])->id,
            ]))
            ->create();

        $bastData = AssignmentBastData::factory()->create(['assignment_id' => $assignment->id]);

        foreach ($checkpointKeys as $checkpointKey) {
            AssignmentBastPhoto::query()->create([
                'assignment_bast_data_id' => $bastData->id,
                'section' => 'measurements',
                'checkpoint_key' => $checkpointKey,
                'photo_path' => $photoPath,
            ]);
        }

        $spreadsheet = app(BastReportExportService::class)->generate($assignment);
        $sheet = $spreadsheet->getSheetByName('KWH,AC Panel, Cable');
        $coordinates = collect($sheet?->getDrawingCollection() ?? [])
            ->map(fn ($drawing) => $drawing->getCoordinates())
            ->values()
            ->all();

        expect($coordinates)->toBe($expectedCoordinates);
    } finally {
        if (file_exists($absolutePhotoPath)) {
            unlink($absolutePhotoPath);
        }
    }
})->with([
    'EVCS' => [
        'EVCS',
        [
            'measurement_voltage_rs',
            'measurement_voltage_rt',
            'measurement_voltage_st',
            'measurement_frequency',
            'measurement_voltage_ng',
            'measurement_grounding_ac',
        ],
        ['B14', 'F14', 'B16', 'F16', 'B18', 'F18'],
    ],
    'BSS' => [
        'BSS',
        [
            'measurement_voltage_rn',
            'measurement_voltage_rg',
            'measurement_frequency',
            'measurement_voltage_ng',
            'measurement_grounding_ac',
        ],
        ['B14', 'F14', 'B16', 'F16', 'B18'],
    ],
]);

test('bast report grounding photos match the excel grounding layout', function () {
    $photoPath = 'bast/grounding-placement-test.png';
    $absolutePhotoPath = storage_path('app/public/'.$photoPath);

    if (! is_dir(dirname($absolutePhotoPath))) {
        mkdir(dirname($absolutePhotoPath), 0755, true);
    }

    $image = imagecreatetruecolor(800, 450);
    imagefilledrectangle($image, 0, 0, 799, 449, imagecolorallocate($image, 30, 180, 120));
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

        foreach ([
            'grounding_rod_connection',
            'grounding_connected_to_device',
            'grounding_rod_to_earth_1',
            'grounding_rod_to_earth_2',
            'grounding_busbar_panel',
            'grounding_cable_route',
            'grounding_test_ac_panel',
        ] as $checkpointKey) {
            AssignmentBastPhoto::query()->create([
                'assignment_bast_data_id' => $bastData->id,
                'section' => 'grounding',
                'checkpoint_key' => $checkpointKey,
                'photo_path' => $photoPath,
            ]);
        }

        $spreadsheet = app(BastReportExportService::class)->generate($assignment);
        $sheet = $spreadsheet->getSheetByName('Grounding');
        $coordinates = collect($sheet?->getDrawingCollection() ?? [])
            ->map(fn ($drawing) => $drawing->getCoordinates())
            ->values()
            ->all();

        expect($coordinates)->toBe(['B3', 'F3', 'B4', 'F4', 'B5', 'F5']);
    } finally {
        if (file_exists($absolutePhotoPath)) {
            unlink($absolutePhotoPath);
        }
    }
});

test('bast grounding form has one grounding rod to earth field and excel cable route label', function () {
    $component = file_get_contents(resource_path('js/components/activities/BastCheckpoints.vue'));

    expect($component)->toContain("key: 'grounding_rod_to_earth_1'")
        ->and($component)->not->toContain("key: 'grounding_rod_to_earth_2'")
        ->and($component)->toContain("label: 'GROUNDING ROD CONNECTION'")
        ->and($component)->toContain("label: 'GROUNDING CONNECTED TO EV CHARGER'")
        ->and($component)->toContain("label: 'GROUNDING ROD TO EARTH'")
        ->and($component)->toContain("label: 'GROUNDING BUSBAR IN PANEL AC'")
        ->and($component)->toContain("label: 'GROUNDING CABLE ROUTE'")
        ->and($component)->toContain("label: 'GROUNDING TEST (OHM)'");
});
