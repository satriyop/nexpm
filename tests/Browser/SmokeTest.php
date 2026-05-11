<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentSurveyData;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;

test('welcome page has no browser smoke errors', function () {
    visit('/')->assertNoSmoke();
});

test('subcontractor can select survey upload files in the browser', function () {
    [$assignment, $subcontractorUser] = browserSurveyAssignment();
    [$imagePath, $pdfPath] = browserSurveyFiles();

    visit('/login')
        ->type('email', $subcontractorUser->email)
        ->type('password', 'password')
        ->click('@login-button')
        ->navigate(route('subcontractor.assignments.show', $assignment, false))
        ->assertSee($assignment->site->location_name)
        ->assertSee('50')
        ->type('input[type="date"]', '2026-05-12')
        ->attach('@survey-photo_overall_site', $imagePath)
        ->attach('@survey-photo_parking_evcs', $imagePath)
        ->attach('@survey-photo_access_route', $imagePath)
        ->attach('@survey-photo_pln_network', $imagePath)
        ->attach('@survey-photo_satellite_gmaps', $imagePath)
        ->attach('@survey-file_mockup_3d', $pdfPath)
        ->attach('@survey-file_site_plan', $pdfPath)
        ->attach('@survey-file_ba_survey', $pdfPath)
        ->wait(3)
        ->assertSee('Selected: Format Site Survey Report.pdf')
        ->assertNoJavaScriptErrors();
});

function browserSurveyAssignment(): array
{
    $mainContractor = MainContractor::factory()->create();
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'location_name' => 'Browser Smoke Survey Site',
        'power_kva' => '50',
    ]);
    $subcontractor = Subcontractor::factory()->forMainContractor($mainContractor)->create();
    $assignment = Assignment::factory()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $subcontractor->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Pending,
    ]);

    AssignmentSurveyData::factory()->create([
        'assignment_id' => $assignment->id,
        'surveyor_name' => 'Browser Surveyor',
        'pic_location_name' => 'Browser PIC',
        'pic_location_phone' => '081234567890',
        'charger_type' => 'BSS',
        'ss_schedule_date' => '2026-05-12',
        'cable_pulling_type' => 'New Power',
        'power_kva' => '50',
        'pln_network_type' => '3 Phase',
        'parking_slot' => 'A1',
        'additional_info' => 'Browser smoke data',
    ]);

    $subcontractorUser = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $subcontractor->id,
    ]);

    return [$assignment, $subcontractorUser];
}

function browserSurveyFiles(): array
{
    $imagePath = sys_get_temp_dir().'/nex-browser-survey.png';
    $pdfPath = base_path('docs/Format Site Survey Report.pdf');

    $image = imagecreatetruecolor(40, 40);
    imagefilledrectangle($image, 0, 0, 39, 39, imagecolorallocate($image, 30, 120, 220));
    imagepng($image, $imagePath);
    imagedestroy($image);

    return [$imagePath, $pdfPath];
}
