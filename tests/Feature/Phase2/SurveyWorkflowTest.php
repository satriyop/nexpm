<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Report;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function completeSurveyUploadPayload(): array
{
    return [
        '_method' => 'patch',
        'surveyor_name' => 'Surveyor A',
        'pic_location_name' => 'PIC Site',
        'pic_location_phone' => '08123456789',
        'charger_type' => 'EVCS',
        'ss_schedule_date' => '2026-05-12',
        'cable_pulling_type' => 'New Power',
        'pln_network_type' => '3 Phase',
        'parking_slot' => 'A1',
        'additional_info' => 'Ready for document review.',
        'photo_overall_site' => UploadedFile::fake()->image('overall.jpg'),
        'photo_parking_evcs' => UploadedFile::fake()->image('parking.jpg'),
        'photo_access_route' => UploadedFile::fake()->image('access.jpg'),
        'photo_pln_network' => UploadedFile::fake()->image('pln.jpg'),
        'photo_satellite_gmaps' => UploadedFile::fake()->image('satellite.jpg'),
        'file_mockup_3d' => UploadedFile::fake()->create('mockup.pdf', 64, 'application/pdf'),
        'file_site_plan' => UploadedFile::fake()->create('site-plan.pdf', 64, 'application/pdf'),
        'file_ba_survey' => UploadedFile::fake()->create('ba-survey.pdf', 64, 'application/pdf'),
    ];
}

function surveyWorkflowAssignment(): Assignment
{
    $mainContractor = MainContractor::factory()->create();
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'power_kva' => '50',
    ]);
    $subcontractor = Subcontractor::factory()->forMainContractor($mainContractor)->create();

    return Assignment::factory()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $subcontractor->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Pending,
    ]);
}

test('subcontractor can submit complete survey uploads and assignment advances to document', function () {
    Storage::fake('public');

    $assignment = surveyWorkflowAssignment();
    $subcontractorUser = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $assignment->subcontractor_id,
    ]);

    $this->actingAs($subcontractorUser)
        ->post(route('subcontractor.assignments.survey', $assignment), [
            ...completeSurveyUploadPayload(),
            'power_kva' => '999',
        ])
        ->assertRedirect();

    $assignment->refresh();
    $survey = $assignment->surveyData()->firstOrFail();

    expect($assignment->status)->toBe(AssignmentStatus::Document)
        ->and($survey->power_kva)->toBe('50')
        ->and($survey->isComplete())->toBeTrue();

    Storage::disk('public')->assertExists($survey->photo_overall_site);
    Storage::disk('public')->assertExists($survey->photo_satellite_gmaps);
    Storage::disk('public')->assertExists($survey->file_ba_survey);
});

test('admin can submit complete survey uploads and assignment advances to document', function () {
    Storage::fake('public');

    $assignment = surveyWorkflowAssignment();
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($admin)
        ->post(route('admin.assignments.admin-survey', $assignment), completeSurveyUploadPayload())
        ->assertRedirect();

    $assignment->refresh();
    $survey = $assignment->surveyData()->firstOrFail();

    expect($assignment->status)->toBe(AssignmentStatus::Document)
        ->and($survey->power_kva)->toBe('50')
        ->and($survey->isComplete())->toBeTrue();

    Storage::disk('public')->assertExists($survey->photo_overall_site);
    Storage::disk('public')->assertExists($survey->photo_satellite_gmaps);
    Storage::disk('public')->assertExists($survey->file_ba_survey);
});

test('verified completed survey can generate and download ssr report', function () {
    $assignment = surveyWorkflowAssignment();
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($admin)
        ->post(route('admin.assignments.admin-survey', $assignment), completeSurveyUploadPayload())
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Document);

    $this->actingAs($admin)
        ->post(route('admin.assignments.verify', $assignment))
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Verified);

    $this->actingAs($admin)
        ->post(route('admin.reports.store'), [
            'report_type' => 'SSR',
            'assignment_ids' => [$assignment->id],
        ])
        ->assertRedirect(route('admin.reports.index'))
        ->assertSessionHas('download_url');

    $report = Report::query()->where('report_type', 'SSR')->firstOrFail();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Reported);

    $this->actingAs($admin)
        ->get(route('admin.reports.download', $report))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('setting power_kva on site after survey submission advances status to document', function () {
    Storage::fake('public');

    $assignment = surveyWorkflowAssignment();
    $assignment->site->update(['power_kva' => null]);

    $subcontractorUser = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $assignment->subcontractor_id,
    ]);

    $this->actingAs($subcontractorUser)
        ->post(route('subcontractor.assignments.survey', $assignment), completeSurveyUploadPayload())
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Survey);

    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $site = $assignment->site;
    $this->actingAs($admin)
        ->patch(route('admin.sites.update', $site), [
            'location_name' => $site->location_name,
            'address' => $site->address ?? 'Test Address',
            'province' => $site->province ?? 'Test Province',
            'city' => $site->city ?? 'Test City',
            'power_kva' => '50kVA',
        ])
        ->assertRedirect();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Document);
    expect($assignment->surveyData()->value('power_kva'))->toBe('50kVA');
});
