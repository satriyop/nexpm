<?php

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentConstructionData;
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
                ->has('siteOperations.filter_options')
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

    Assignment::factory()->survey()->create([
        'site_id' => $site->id,
        'status' => AssignmentStatus::Verified,
        'verified_at' => now()->subWeek(),
    ]);

    $payload = app(SiteOperationsDashboardService::class)->build($superAdmin);
    $row = $payload['site_rows'][0];

    expect($payload['metrics']['total_sites'])->toBe(1)
        ->and($payload['metrics']['blocked_sites'])->toBe(1)
        ->and($payload['problem_breakdown'])->toHaveKey('construction_missing_wo')
        ->and($row['site_code'])->toBe('JKT-001')
        ->and($row['overall_status'])->toBe('blocked')
        ->and($row['issue_type'])->toBe('construction_missing_wo')
        ->and($row['main_issue'])->toContain('WO number is missing')
        ->and($row['issues'])->not->toBeEmpty()
        ->and($row['issues'][0]['type'])->toBe('construction_missing_wo')
        ->and($row['owner'])->toBe('Admin: Main Con Admin')
        ->and($row['workstreams']['construction']['status'])->toBe(AssignmentStatus::Pending->value)
        ->and($row['workstreams']['survey']['status'])->toBe(AssignmentStatus::Verified->value)
        ->and($row['url'])->toBe(route('admin.assignments.site-assignments', $site))
        ->and($payload['filter_options']['statuses'])->toContain('blocked')
        ->and($payload['filter_options']['issue_types'])->toContain('construction_missing_wo')
        ->and($payload['filter_options']['owners'])->toContain('Admin: Main Con Admin');
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
