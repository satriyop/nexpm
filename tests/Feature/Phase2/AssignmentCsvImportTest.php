<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Services\AssignmentCsvImportService;
use Illuminate\Support\Facades\File;

function makeAssignmentCsv(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'assignments_');
    File::put($path, $body);

    return $path;
}

test('imports new assignments and seeds activity data records', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    $siteA = Site::factory()->for($project)->create(['site_code' => 'SITE-AAA']);
    $siteB = Site::factory()->for($project)->create(['site_code' => 'SITE-BBB']);

    $subA = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-100']);
    $subB = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-200']);

    $csv = "site_code,activity_type,subcontractor_code\n";
    $csv .= "SITE-AAA,SURVEY,SUB-100\n";
    $csv .= "SITE-BBB,CONSTRUCTION,SUB-200\n";

    $path = makeAssignmentCsv($csv);

    $result = (new AssignmentCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(2)
        ->and($result['updated'])->toBe(0)
        ->and($result['errors'])->toBe([]);

    $survey = Assignment::query()
        ->where('site_id', $siteA->id)
        ->where('activity_type', ActivityType::Survey)
        ->first();

    $construction = Assignment::query()
        ->where('site_id', $siteB->id)
        ->where('activity_type', ActivityType::Construction)
        ->first();

    expect($survey)->not->toBeNull()
        ->and($survey->subcontractor_id)->toBe($subA->id)
        ->and($survey->status)->toBe(AssignmentStatus::Pending)
        ->and($survey->surveyData)->not->toBeNull()
        ->and($construction)->not->toBeNull()
        ->and($construction->subcontractor_id)->toBe($subB->id)
        ->and($construction->constructionData)->not->toBeNull();

    File::delete($path);
});

test('upsert reassigns subcontractor on existing assignment', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();
    $site = Site::factory()->for($project)->create(['site_code' => 'SITE-XYZ']);

    $sub1 = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-OLD']);
    $sub2 = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-NEW']);

    Assignment::query()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $sub1->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Pending,
    ]);

    $csv = "site_code,activity_type,subcontractor_code\n";
    $csv .= "SITE-XYZ,SURVEY,SUB-NEW\n";

    $path = makeAssignmentCsv($csv);

    $result = (new AssignmentCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0])->toContain('SITE-XYZ SURVEY reassigned from')
        ->and($result['errors'])->toBe([]);

    $assignment = Assignment::query()
        ->where('site_id', $site->id)
        ->where('activity_type', ActivityType::Survey)
        ->first();

    expect($assignment->subcontractor_id)->toBe($sub2->id);

    File::delete($path);
});

test('import skips blank subcontractor rows so template can be partially filled', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();
    $site = Site::factory()->for($project)->create(['site_code' => 'SITE-XYZ']);

    $sub1 = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-SURVEY']);

    $csv = "site_code,activity_type,subcontractor_code\n";
    $csv .= "SITE-XYZ,SURVEY,SUB-SURVEY\n";
    $csv .= "SITE-XYZ,CONSTRUCTION,\n";
    $csv .= "SITE-XYZ,PLN_CONNECTION,\n";
    $csv .= "SITE-XYZ,BAST,\n";

    $path = makeAssignmentCsv($csv);

    $result = (new AssignmentCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(1)
        ->and($result['updated'])->toBe(0)
        ->and($result['skipped'])->toBe(3)
        ->and($result['warnings'])->toBe([])
        ->and($result['errors'])->toBe([]);

    expect(Assignment::query()->where('site_id', $site->id)->count())->toBe(1);

    File::delete($path);
});

test('later import can fill other activities from the same template', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();
    $site = Site::factory()->for($project)->create(['site_code' => 'SITE-XYZ']);

    $surveySub = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-SURVEY']);
    $constructionSub = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-CON']);
    $plnSub = Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-PLN']);

    $firstPath = makeAssignmentCsv("site_code,activity_type,subcontractor_code\nSITE-XYZ,SURVEY,SUB-SURVEY\nSITE-XYZ,CONSTRUCTION,\nSITE-XYZ,PLN_CONNECTION,\nSITE-XYZ,BAST,\n");
    $secondPath = makeAssignmentCsv("site_code,activity_type,subcontractor_code\nSITE-XYZ,SURVEY,SUB-SURVEY\nSITE-XYZ,CONSTRUCTION,SUB-CON\nSITE-XYZ,PLN_CONNECTION,SUB-PLN\nSITE-XYZ,BAST,\n");

    $firstResult = (new AssignmentCsvImportService)->import($firstPath, $project->id);
    $secondResult = (new AssignmentCsvImportService)->import($secondPath, $project->id);

    expect($firstResult['created'])->toBe(1)
        ->and($firstResult['updated'])->toBe(0)
        ->and($firstResult['skipped'])->toBe(3)
        ->and($secondResult['created'])->toBe(2)
        ->and($secondResult['updated'])->toBe(1)
        ->and($secondResult['skipped'])->toBe(1)
        ->and($secondResult['warnings'])->toBe([])
        ->and($secondResult['errors'])->toBe([])
        ->and(Assignment::query()->where('site_id', $site->id)->count())->toBe(3);

    expect(Assignment::query()
        ->where('site_id', $site->id)
        ->where('activity_type', ActivityType::Survey)
        ->value('subcontractor_id'))->toBe($surveySub->id)
        ->and(Assignment::query()
            ->where('site_id', $site->id)
            ->where('activity_type', ActivityType::Construction)
            ->value('subcontractor_id'))->toBe($constructionSub->id)
        ->and(Assignment::query()
            ->where('site_id', $site->id)
            ->where('activity_type', ActivityType::PlnConnection)
            ->value('subcontractor_id'))->toBe($plnSub->id);

    File::delete($firstPath);
    File::delete($secondPath);
});

test('reports row-level errors for invalid csv content', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();
    Site::factory()->for($project)->create(['site_code' => 'SITE-OK']);
    Subcontractor::factory()->forMainContractor($contractor)->create(['code' => 'SUB-OK']);

    $csv = "site_code,activity_type,subcontractor_code\n";
    $csv .= "SITE-OK,INVALID_TYPE,SUB-OK\n";
    $csv .= "MISSING-SITE,SURVEY,SUB-OK\n";
    $csv .= "SITE-OK,SURVEY,UNKNOWN-SUB\n";

    $path = makeAssignmentCsv($csv);

    $result = (new AssignmentCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(0)
        ->and($result['errors'])->toHaveCount(3);

    File::delete($path);
});
