<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentConstructionPhoto;
use App\Models\AssignmentLegacyReport;
use App\Models\AssignmentSurveyData;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('non-superadmin cannot delete a project', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.projects.destroy', $project))
        ->assertForbidden();

    expect(Project::find($project->id))->not->toBeNull();
});

test('superadmin can delete a project and is redirected to index', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create();

    $this->actingAs($superAdmin)
        ->delete(route('admin.projects.destroy', $project))
        ->assertRedirect(route('admin.projects.index'));

    expect(Project::find($project->id))->toBeNull();
});

test('deleting a project cascades to sites and assignments', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create();
    $site = Site::factory()->create(['project_id' => $project->id]);
    $assignment = Assignment::factory()->create([
        'site_id' => $site->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Reported,
    ]);
    AssignmentSurveyData::factory()->create(['assignment_id' => $assignment->id]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.projects.destroy', $project));

    expect(Project::find($project->id))->toBeNull()
        ->and(Site::find($site->id))->toBeNull()
        ->and(Assignment::find($assignment->id))->toBeNull()
        ->and(AssignmentSurveyData::where('assignment_id', $assignment->id)->exists())->toBeFalse();
});

test('deleting a project removes uploaded storage files', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create();
    $site = Site::factory()->create(['project_id' => $project->id]);
    $assignment = Assignment::factory()->create([
        'site_id' => $site->id,
        'activity_type' => ActivityType::Construction,
        'status' => AssignmentStatus::Reported,
    ]);

    $consData = AssignmentConstructionData::factory()->create(['assignment_id' => $assignment->id]);
    $photoPath = 'construction/test-photo.jpg';
    Storage::disk('public')->put($photoPath, 'fake');
    AssignmentConstructionPhoto::factory()->create([
        'assignment_construction_data_id' => $consData->id,
        'path' => $photoPath,
    ]);

    $reportPath = 'legacy-reports/test.pdf';
    Storage::disk('public')->put($reportPath, 'fake');
    AssignmentLegacyReport::create([
        'assignment_id' => $assignment->id,
        'report_type' => 'SSR',
        'file_path' => $reportPath,
        'original_filename' => 'test.pdf',
        'uploaded_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.projects.destroy', $project));

    Storage::disk('public')->assertMissing($photoPath);
    Storage::disk('public')->assertMissing($reportPath);
});

test('deleting a project removes orphaned reports from the reports table', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create();
    $site = Site::factory()->create(['project_id' => $project->id]);
    $assignment = Assignment::factory()->create(['site_id' => $site->id]);

    $reportId = DB::table('reports')->insertGetId([
        'name' => 'Test Report',
        'report_type' => 'BAST',
        'exported_by' => $superAdmin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('report_assignments')->insert([
        'report_id' => $reportId,
        'assignment_id' => $assignment->id,
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.projects.destroy', $project));

    expect(DB::table('reports')->where('id', $reportId)->exists())->toBeFalse();
});

test('deleting a project with bast photos removes them from storage', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create();
    $site = Site::factory()->create(['project_id' => $project->id]);
    $assignment = Assignment::factory()->create([
        'site_id' => $site->id,
        'activity_type' => ActivityType::Bast,
        'status' => AssignmentStatus::Reported,
    ]);

    $bastData = AssignmentBastData::factory()->create(['assignment_id' => $assignment->id]);
    $bastPhotoPath = 'bast/test-bast.jpg';
    Storage::disk('public')->put($bastPhotoPath, 'fake');
    DB::table('assignment_bast_photos')->insert([
        'assignment_bast_data_id' => $bastData->id,
        'section' => 'test',
        'checkpoint_key' => 'key_1',
        'photo_path' => $bastPhotoPath,
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.projects.destroy', $project));

    Storage::disk('public')->assertMissing($bastPhotoPath);
    expect(Project::find($project->id))->toBeNull();
});
