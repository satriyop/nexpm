<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Report;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\AssignmentCsvImportService;

function tenantRecords(): array
{
    $ownMainContractor = MainContractor::factory()->create();
    $otherMainContractor = MainContractor::factory()->create();

    $admin = User::factory()->create([
        'role' => Role::Admin,
        'main_contractor_id' => $ownMainContractor->id,
    ]);

    $ownProject = Project::factory()->create(['main_contractor_id' => $ownMainContractor->id]);
    $otherProject = Project::factory()->create(['main_contractor_id' => $otherMainContractor->id]);

    $otherSite = Site::factory()->create(['project_id' => $otherProject->id]);
    $otherSubcontractor = Subcontractor::factory()->create(['main_contractor_id' => $otherMainContractor->id]);
    $otherAssignment = Assignment::factory()->create([
        'site_id' => $otherSite->id,
        'subcontractor_id' => $otherSubcontractor->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Verified,
    ]);

    return [$admin, $ownProject, $otherProject, $otherSite, $otherAssignment];
}

test('admin cannot view records from another main contractor by direct url', function () {
    [$admin, , $otherProject, $otherSite, $otherAssignment] = tenantRecords();

    $this->actingAs($admin)
        ->get(route('admin.projects.show', $otherProject))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.sites.edit', $otherSite))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.assignments.site-assignments', $otherSite))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.assignments.show', $otherAssignment))
        ->assertForbidden();
});

test('admin cannot mutate assignment from another main contractor', function () {
    [$admin, , , , $otherAssignment] = tenantRecords();

    $otherAssignment->update(['status' => AssignmentStatus::Document]);

    $this->actingAs($admin)
        ->post(route('admin.assignments.verify', $otherAssignment))
        ->assertForbidden();

    expect($otherAssignment->fresh()->status)->toBe(AssignmentStatus::Document);
});

test('admin cannot generate report from another main contractor assignment', function () {
    [$admin, , , , $otherAssignment] = tenantRecords();

    $this->actingAs($admin)
        ->post(route('admin.reports.store'), [
            'report_type' => 'DAILY',
            'assignment_ids' => [$otherAssignment->id],
        ])
        ->assertForbidden();

    expect(Report::query()->count())->toBe(0)
        ->and($otherAssignment->fresh()->status)->toBe(AssignmentStatus::Verified);
});

test('admin cannot create project under another main contractor', function () {
    [$admin] = tenantRecords();
    $otherMainContractor = MainContractor::factory()->create();
    $otherProject = Project::factory()->create(['main_contractor_id' => $otherMainContractor->id]);

    $this->actingAs($admin)
        ->post(route('admin.projects.store'), [
            'name' => 'Cross Tenant Project',
            'main_contractor_id' => $otherMainContractor->id,
            'client_id' => $otherProject->client_id,
        ])
        ->assertSessionHasErrors('client_id');

    expect(Project::query()->where('name', 'Cross Tenant Project')->exists())->toBeFalse();
});

test('assignment import rejects subcontractor from another main contractor', function () {
    $mainContractor = MainContractor::factory()->create();
    $otherMainContractor = MainContractor::factory()->create();
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);
    $site = Site::factory()->create(['project_id' => $project->id]);
    $otherSubcontractor = Subcontractor::factory()->create(['main_contractor_id' => $otherMainContractor->id]);

    $path = tempnam(sys_get_temp_dir(), 'assignment-import-');
    file_put_contents($path, "site_code,activity_type,subcontractor_code\n{$site->site_code},SURVEY,{$otherSubcontractor->code}\n");

    try {
        $result = app(AssignmentCsvImportService::class)->import($path, $project->id);
    } finally {
        @unlink($path);
    }

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(0)
        ->and($result['errors'])->not->toBeEmpty()
        ->and(Assignment::query()->where('site_id', $site->id)->exists())->toBeFalse();
});

test('admin cannot download or regenerate report from another main contractor', function () {
    [$admin, , , , $otherAssignment] = tenantRecords();
    $report = Report::query()->create([
        'name' => 'Daily Report',
        'report_type' => 'DAILY',
        'exported_by' => $admin->id,
    ]);
    $report->assignments()->attach($otherAssignment);

    $this->actingAs($admin)
        ->get(route('admin.reports.download', $report))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.reports.regenerate', $report))
        ->assertForbidden();
});

test('daily report download works with current spreadsheet version', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);
    $assignment = Assignment::factory()->create(['status' => AssignmentStatus::Verified]);
    $report = Report::query()->create([
        'name' => 'Daily Report',
        'report_type' => 'DAILY',
        'exported_by' => $admin->id,
    ]);
    $report->assignments()->attach($assignment);

    $this->actingAs($admin)
        ->get(route('admin.reports.download', $report))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('bast report download works with current spreadsheet version', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);
    $assignment = Assignment::factory()->bast()->create(['status' => AssignmentStatus::Verified]);
    AssignmentBastData::factory()->create(['assignment_id' => $assignment->id]);
    $report = Report::query()->create([
        'name' => 'BAST Report',
        'report_type' => 'BAST',
        'exported_by' => $admin->id,
    ]);
    $report->assignments()->attach($assignment);

    $this->actingAs($admin)
        ->get(route('admin.reports.download', $report))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
