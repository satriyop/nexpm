<?php

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentConstructionData;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\Dashboard\ProjectControlTowerService;
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
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('controlTower.metrics')
                ->has('controlTower.priority_queue')
            )
        );
});
