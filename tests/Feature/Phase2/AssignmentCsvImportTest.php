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

    $subA = Subcontractor::factory()->for($contractor, 'mainContractor')->create(['code' => 'SUB-100']);
    $subB = Subcontractor::factory()->for($contractor, 'mainContractor')->create(['code' => 'SUB-200']);

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

    $sub1 = Subcontractor::factory()->for($contractor, 'mainContractor')->create(['code' => 'SUB-OLD']);
    $sub2 = Subcontractor::factory()->for($contractor, 'mainContractor')->create(['code' => 'SUB-NEW']);

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
        ->and($result['errors'])->toBe([]);

    $assignment = Assignment::query()
        ->where('site_id', $site->id)
        ->where('activity_type', ActivityType::Survey)
        ->first();

    expect($assignment->subcontractor_id)->toBe($sub2->id);

    File::delete($path);
});

test('reports row-level errors for invalid csv content', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();
    Site::factory()->for($project)->create(['site_code' => 'SITE-OK']);
    Subcontractor::factory()->for($contractor, 'mainContractor')->create(['code' => 'SUB-OK']);

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
