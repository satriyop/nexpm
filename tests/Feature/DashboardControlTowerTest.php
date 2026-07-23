<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentComment;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\Dashboard\ProjectControlTowerService;
use App\Services\Dashboard\SiteOperationsDashboardService;
use Inertia\Testing\AssertableInertia as Assert;

test('project control tower surfaces critical actions and enhanced forecast causes', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create([
        'name' => 'EVCS Jakarta',
        'end_date' => now()->addWeek(),
    ]);
    User::factory()->create([
        'name' => 'Main Con Admin',
        'role' => Role::Admin,
        'main_contractor_id' => $project->main_contractor_id,
    ]);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'JKT-001',
        'power_kva' => null,
    ]);

    $blockedConstruction = Assignment::factory()->construction()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Pending,
        'updated_at' => now()->subDays(15),
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $blockedConstruction->id,
        'cons_wo_number' => null,
    ]);

    Assignment::factory()->survey()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Verified,
        'verified_at' => now()->subWeek(),
    ]);

    $service = app(ProjectControlTowerService::class);
    $controlTower = $service->build($superAdmin);
    $forecast = $service->completionForecast($superAdmin)->firstWhere('id', $project->id);

    expect($controlTower['metrics']['critical_actions'])->toBeGreaterThanOrEqual(1)
        ->and($controlTower['metrics']['stalled'])->toBeGreaterThanOrEqual(1)
        ->and($controlTower['priority_queue'][0]['type'])->toBe('construction_missing_wo')
        ->and($controlTower['priority_queue'][0]['owner'])->toBe('Admin: Main Con Admin')
        ->and(collect($controlTower['priority_queue'])->firstWhere('type', 'verified_not_reported')['owner'])->toBe('Admin: Main Con Admin')
        ->and($forecast['blocker_count'])->toBeGreaterThanOrEqual(1)
        ->and($forecast['main_cause'])->toContain('construction assignments missing WO')
        ->and($forecast['recommended_action'])->toBe('Complete missing WO numbers before construction follow-up.');
});

test('dashboard exposes the control tower as a deferred prop', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('controlTower')
            ->missing('siteOperations')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('controlTower.metrics')
                ->has('controlTower.priority_queue')
                ->has('siteOperations.metrics')
                ->has('siteOperations.problem_breakdown')
                ->has('siteOperations.root_blocker_breakdown')
                ->has('siteOperations.symptom_breakdown')
                ->has('siteOperations.filter_options')
                ->has('siteOperations.active_filters')
                ->has('siteOperations.pagination')
                ->has('siteOperations.site_rows')
            )
        );
});

test('site operations dashboard explains why a location is not done', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Jakarta']);
    User::factory()->create([
        'name' => 'Main Con Admin',
        'role' => Role::Admin,
        'main_contractor_id' => $project->main_contractor_id,
    ]);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'JKT-001',
        'location_name' => 'SPBU Senayan',
        'power_kva' => null,
    ]);

    $blockedConstruction = Assignment::factory()->construction()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Pending,
        'updated_at' => now()->subDays(15),
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $blockedConstruction->id,
        'cons_wo_number' => null,
    ]);
    AssignmentComment::factory()->create([
        'assignment_id' => $blockedConstruction->id,
        'user_id' => $superAdmin->id,
        'body' => 'Access blocked by site security gate.',
        'created_at' => now()->subHour(),
    ]);

    Assignment::factory()->survey()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Verified,
        'verified_at' => now()->subWeek(),
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin);
    $row = $payload['site_rows'][0];
    $noteSearchPayload = app(SiteOperationsDashboardService::class)->build($superAdmin, filters: [
        'search' => 'security gate',
    ]);
    $noteIssuePayload = app(SiteOperationsDashboardService::class)->build($superAdmin, filters: [
        'issue_type' => 'note_blocker_signal',
    ]);

    expect($payload['metrics']['total_sites'])->toBe(1)
        ->and($payload['metrics']['matching_sites'])->toBe(1)
        ->and($payload['metrics']['blocked_sites'])->toBe(1)
        ->and($payload['problem_breakdown'])->toHaveKey('construction_missing_wo')
        ->and($payload['root_blocker_breakdown'])->toHaveKey('construction_missing_wo')
        ->and($payload['symptom_breakdown'])->toHaveKey('stalled_assignment')
        ->and($row['site_code'])->toBe('JKT-001')
        ->and($row['overall_status'])->toBe('blocked')
        ->and($row['current_stage']['key'])->toBe('construction')
        ->and($row['flow_explanation'])->toContain('Construction cannot proceed')
        ->and($row['flow_explanation'])->toContain('BAST')
        ->and($row['issue_type'])->toBe('construction_missing_wo')
        ->and($row['main_issue'])->toContain('WO number is missing')
        ->and($row['issues'])->not->toBeEmpty()
        ->and($row['issues'][0]['type'])->toBe('construction_missing_wo')
        ->and($row['issues'][0]['source'])->toBe('structured_data')
        ->and($row['issues'][0]['category'])->toBe('data_gap')
        ->and($row['issues'][0]['blocks_stage'])->toBe('construction')
        ->and($row['issues'][0]['blocks_downstream'])->toContain(ActivityType::Bast->value)
        ->and($row['root_blocker_type'])->toBe('construction_missing_wo')
        ->and($row['primary_symptom_type'])->toBe('stalled_assignment')
        ->and($row['owner'])->toBe('Admin: Main Con Admin')
        ->and($row['workstreams']['construction']['status'])->toBe(AssignmentStatus::Pending->value)
        ->and($row['workstreams']['construction']['latest_comment']['body'])->toBe('Access blocked by site security gate.')
        ->and($row['latest_note']['body'])->toBe('Access blocked by site security gate.')
        ->and(collect($row['issues'])->pluck('type'))->toContain('note_blocker_signal')
        ->and($row['workstreams']['survey']['status'])->toBe(AssignmentStatus::Verified->value)
        ->and($row['url'])->toBe(route('admin.assignments.site-assignments', $site))
        ->and($payload['filter_options']['statuses'])->toContain('blocked')
        ->and($payload['filter_options']['issue_types'])->toContain('construction_missing_wo', 'note_blocker_signal')
        ->and($payload['filter_options']['owners'])->toContain('Admin: Main Con Admin')
        ->and($noteSearchPayload['site_rows'])->toHaveCount(1)
        ->and($noteSearchPayload['site_rows'][0]['site_code'])->toBe('JKT-001')
        ->and($noteIssuePayload['site_rows'])->toHaveCount(1)
        ->and($noteIssuePayload['site_rows'][0]['site_code'])->toBe('JKT-001');
});

test('site operations root blocker wins over operational symptom', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Jakarta']);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'ROOT-WINS',
        'power_kva' => null,
    ]);
    $survey = Assignment::factory()->survey()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Revision,
    ]);
    AssignmentSurveyData::factory()->create([
        'assignment_id' => $survey->id,
        'ss_schedule_date' => now()->toDateString(),
        'power_kva' => null,
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin);
    $row = $payload['site_rows'][0];

    expect($row['issue_type'])->toBe('site_missing_power')
        ->and($row['root_blocker_type'])->toBe('site_missing_power')
        ->and($row['primary_symptom_type'])->toBe('revision_pending')
        ->and($row['overall_status'])->toBe('blocked')
        ->and($payload['problem_breakdown'])->toHaveKey('site_missing_power')
        ->and($payload['problem_breakdown'])->not->toHaveKey('revision_pending')
        ->and($payload['root_blocker_breakdown'])->toHaveKey('site_missing_power')
        ->and($payload['symptom_breakdown'])->toHaveKey('revision_pending');
});

test('site operations blocked status follows blocker category', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Jakarta']);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'NOTE-BLOCKED',
        'power_kva' => 50,
    ]);
    $survey = Assignment::factory()->survey()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Pending,
    ]);
    AssignmentSurveyData::factory()->complete()->create([
        'assignment_id' => $survey->id,
    ]);
    AssignmentComment::factory()->create([
        'assignment_id' => $survey->id,
        'user_id' => $superAdmin->id,
        'body' => 'Access blocked by location owner.',
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin);
    $row = $payload['site_rows'][0];

    expect($row['issue_type'])->toBe('note_blocker_signal')
        ->and($row['root_blocker_type'])->toBe('note_blocker_signal')
        ->and($row['overall_status'])->toBe('blocked')
        ->and($payload['root_blocker_breakdown'])->toHaveKey('note_blocker_signal')
        ->and($payload['metrics']['blocked_sites'])->toBe(1);
});

test('site operations dashboard exposes construction WO for filtering', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Bandung']);
    $site = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'BDG-001',
    ]);

    $construction = Assignment::factory()->construction()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Construction,
        'updated_at' => now()->subDays(2),
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $construction->id,
        'cons_wo_number' => 'WO-7788',
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin);
    $row = $payload['site_rows'][0];

    expect($row['construction_wo_number'])->toBe('WO-7788')
        ->and($row['workstreams']['construction']['wo_number'])->toBe('WO-7788')
        ->and($payload['filter_options']['wo_numbers'])->toContain('WO-7788');
});

test('site operations filters by WO across the full scoped location set', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Bandung']);

    for ($i = 1; $i <= 55; $i++) {
        $site = Site::factory()->create([
            'project_id' => $project->id,
            'site_code' => sprintf('BLOCKED-%03d', $i),
        ]);
        $construction = Assignment::factory()->construction()->create([
            'site_id' => $site->id,
            'status' => AssignmentStatus::Pending,
            'updated_at' => now()->subDays(20),
        ]);
        AssignmentConstructionData::factory()->create([
            'assignment_id' => $construction->id,
            'cons_wo_number' => null,
        ]);
    }

    $targetSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'TARGET-WO',
    ]);
    $targetConstruction = Assignment::factory()->construction()->create([
        'site_id' => $targetSite->id,
        'status' => AssignmentStatus::Construction,
        'updated_at' => now()->subDays(2),
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $targetConstruction->id,
        'cons_wo_number' => 'WO-TARGET-7788',
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin, filters: [
        'wo_number' => 'WO-TARGET-7788',
        'per_page' => 25,
    ]);

    expect($payload['metrics']['total_sites'])->toBe(56)
        ->and($payload['metrics']['matching_sites'])->toBe(1)
        ->and($payload['pagination']['total'])->toBe(1)
        ->and($payload['site_rows'])->toHaveCount(1)
        ->and($payload['site_rows'][0]['site_code'])->toBe('TARGET-WO')
        ->and($payload['active_filters']['wo_number'])->toBe('WO-TARGET-7788');
});

test('site operations export uses the same WO filter', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Bandung']);

    $otherSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'OTHER-WO',
    ]);
    $otherConstruction = Assignment::factory()->construction()->create([
        'site_id' => $otherSite->id,
        'status' => AssignmentStatus::Construction,
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $otherConstruction->id,
        'cons_wo_number' => 'WO-OTHER',
    ]);

    $targetSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'TARGET-WO',
    ]);
    $targetConstruction = Assignment::factory()->construction()->create([
        'site_id' => $targetSite->id,
        'status' => AssignmentStatus::Construction,
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $targetConstruction->id,
        'cons_wo_number' => 'WO-TARGET-7788',
    ]);

    $response = $this->actingAs($superAdmin)->get(route('dashboard.site-operations.export', [
        'site_wo_number' => 'WO-TARGET-7788',
    ]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('TARGET-WO')
        ->toContain('WO-TARGET-7788')
        ->not->toContain('OTHER-WO');
});

test('dashboard deferred site operations use URL filters', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Bandung']);

    $otherSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'OTHER-WO',
    ]);
    $otherConstruction = Assignment::factory()->construction()->create([
        'site_id' => $otherSite->id,
        'status' => AssignmentStatus::Construction,
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $otherConstruction->id,
        'cons_wo_number' => 'WO-OTHER',
    ]);

    $targetSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'TARGET-WO',
    ]);
    $targetConstruction = Assignment::factory()->construction()->create([
        'site_id' => $targetSite->id,
        'status' => AssignmentStatus::Construction,
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $targetConstruction->id,
        'cons_wo_number' => 'WO-TARGET-7788',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('dashboard', ['site_wo_number' => 'WO-TARGET-7788']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('siteOperations')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('siteOperations.metrics.matching_sites', 1)
                ->where('siteOperations.pagination.total', 1)
                ->where('siteOperations.site_rows.0.site_code', 'TARGET-WO')
                ->where('siteOperations.active_filters.wo_number', 'WO-TARGET-7788')
            )
        );
});

test('site operations surfaces workstream-specific blocker rules', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'EVCS Detail Rules']);

    $surveySite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'SURVEY-GAP',
        'power_kva' => null,
    ]);
    $survey = Assignment::factory()->survey()->create([
        'site_id' => $surveySite->id,
        'status' => AssignmentStatus::Survey,
    ]);
    AssignmentSurveyData::factory()->create([
        'assignment_id' => $survey->id,
        'ss_schedule_date' => null,
        'power_kva' => null,
    ]);

    $plnSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'PLN-GAP',
    ]);
    $pln = Assignment::factory()->plnConnection()->create([
        'site_id' => $plnSite->id,
        'status' => AssignmentStatus::Connection,
    ]);
    AssignmentPlnData::factory()->create([
        'assignment_id' => $pln->id,
        'kwh_meter_installation_date' => null,
        'id_pelanggan' => null,
        'foto_kwh' => null,
    ]);

    $constructionSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'CONS-GAP',
    ]);
    $construction = Assignment::factory()->construction()->create([
        'site_id' => $constructionSite->id,
        'status' => AssignmentStatus::Live,
    ]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $construction->id,
        'cons_wo_number' => 'WO-READY',
        'cons_actual_start_date' => null,
        'cons_actual_done_date' => null,
        'machine_serial_number' => null,
        'foto_machine_sn' => null,
    ]);

    $bastSite = Site::factory()->create([
        'project_id' => $project->id,
        'site_code' => 'BAST-GAP',
    ]);
    $bast = Assignment::factory()->bast()->create([
        'site_id' => $bastSite->id,
        'status' => AssignmentStatus::Pending,
    ]);
    AssignmentBastData::factory()->create([
        'assignment_id' => $bast->id,
        'plant_name' => null,
        'installation_date' => null,
        'commissioning_date' => null,
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin, filters: ['per_page' => 100]);
    $rows = collect($payload['site_rows'])
        ->keyBy('site_code');
    $surveyEvidencePayload = app(SiteOperationsDashboardService::class)->build($superAdmin, filters: [
        'issue_type' => 'survey_evidence_missing',
        'per_page' => 100,
    ]);

    expect($rows->get('SURVEY-GAP')['issue_type'])->toBe('survey_schedule_missing')
        ->and(collect($rows->get('SURVEY-GAP')['issues'])->pluck('type'))->toContain('site_missing_power', 'survey_evidence_missing')
        ->and($rows->get('PLN-GAP')['issue_type'])->toBe('pln_kwh_incomplete')
        ->and($rows->get('CONS-GAP')['issue_type'])->toBe('construction_data_incomplete')
        ->and(collect($rows->get('CONS-GAP')['issues'])->pluck('type'))->toContain('construction_photos_missing')
        ->and($rows->get('BAST-GAP')['issue_type'])->toBe('bast_sim_missing')
        ->and(collect($rows->get('BAST-GAP')['issues'])->pluck('type'))->toContain('bast_evidence_missing')
        ->and($payload['filter_options']['issue_types'])->toContain('survey_evidence_missing', 'construction_photos_missing', 'bast_evidence_missing')
        ->and($surveyEvidencePayload['site_rows'])->toHaveCount(1)
        ->and($surveyEvidencePayload['site_rows'][0]['site_code'])->toBe('SURVEY-GAP');
});
