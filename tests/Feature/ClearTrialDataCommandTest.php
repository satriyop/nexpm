<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentConstructionPhoto;
use App\Models\AssignmentSurveyData;
use App\Models\Project;
use App\Models\Report;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Seed a realistic slice of trial data and return the counts for assertions.
 */
function seedTrialData(): void
{
    // Config/master data — must be preserved
    $project = Project::factory()->create();
    $subcontractor = Subcontractor::factory()->create();

    // Sites + assignments
    $site = Site::factory()->create(['project_id' => $project->id]);
    $survey = Assignment::factory()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $subcontractor->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Document,
    ]);
    $bast = Assignment::factory()->bast()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $subcontractor->id,
        'status' => AssignmentStatus::Submitted,
    ]);

    // Activity data with fake storage paths
    AssignmentSurveyData::factory()->create([
        'assignment_id' => $survey->id,
        'photo_overall_site' => 'survey/photo1.jpg',
    ]);

    $bastData = AssignmentBastData::factory()->create(['assignment_id' => $bast->id]);
    AssignmentBastPhoto::create([
        'assignment_bast_data_id' => $bastData->id,
        'section' => 'device',
        'checkpoint_key' => 'device_front_view_open',
        'photo_path' => 'bast/photo1.jpg',
    ]);

    $construction = Assignment::factory()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $subcontractor->id,
        'activity_type' => ActivityType::Construction,
    ]);
    $constructionData = AssignmentConstructionData::factory()->create(['assignment_id' => $construction->id]);
    AssignmentConstructionPhoto::factory()->create([
        'assignment_construction_data_id' => $constructionData->id,
        'path' => 'construction/photo1.jpg',
    ]);

    // Report linked to the bast assignment
    $report = Report::create([
        'name' => 'Test Report',
        'report_type' => 'BAST',
        'exported_by' => User::factory()->create(['role' => Role::SuperAdmin])->id,
    ]);
    $report->assignments()->attach($bast->id);
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test('dry-run shows counts without deleting anything', function () {
    seedTrialData();

    $sitesBefore = Site::count();
    $assignmentsBefore = Assignment::count();

    $this->artisan('app:clear-trial-data --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('Dry run complete. No changes made.');

    expect(Site::count())->toBe($sitesBefore)
        ->and(Assignment::count())->toBe($assignmentsBefore);
});

test('abort when confirmation answer is not yes', function () {
    seedTrialData();

    $sitesBefore = Site::count();

    $this->artisan('app:clear-trial-data')
        ->expectsQuestion('Type "yes" to proceed', 'no')
        ->assertFailed();

    expect(Site::count())->toBe($sitesBefore);
});

test('clears all site and assignment data with --force', function () {
    Storage::fake('public');
    Storage::disk('public')->put('survey/photo1.jpg', 'fake');
    Storage::disk('public')->put('bast/photo1.jpg', 'fake');
    Storage::disk('public')->put('construction/photo1.jpg', 'fake');
    Storage::disk('public')->put('logos/contractor.png', 'fake'); // must be preserved

    seedTrialData();

    $this->artisan('app:clear-trial-data --force')
        ->assertSuccessful()
        ->expectsOutputToContain('Trial data cleared');

    // All trial tables empty
    expect(Site::count())->toBe(0)
        ->and(Assignment::count())->toBe(0)
        ->and(AssignmentSurveyData::count())->toBe(0)
        ->and(AssignmentBastData::count())->toBe(0)
        ->and(AssignmentBastPhoto::count())->toBe(0)
        ->and(AssignmentConstructionData::count())->toBe(0)
        ->and(AssignmentConstructionPhoto::count())->toBe(0)
        ->and(Report::count())->toBe(0);

    // Configuration tables untouched
    expect(Project::count())->toBeGreaterThan(0)
        ->and(Subcontractor::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(0);

    // Upload dirs cleaned, logos preserved
    Storage::disk('public')->assertMissing('survey/photo1.jpg');
    Storage::disk('public')->assertMissing('bast/photo1.jpg');
    Storage::disk('public')->assertMissing('construction/photo1.jpg');
    Storage::disk('public')->assertExists('logos/contractor.png');
});

test('delete order respects foreign key constraints', function () {
    // This test would throw a DB constraint exception if the order is wrong.
    seedTrialData();

    $this->artisan('app:clear-trial-data --force')
        ->assertSuccessful();
});
