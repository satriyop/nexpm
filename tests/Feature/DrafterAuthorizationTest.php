<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;

test('drafter is redirected to drafter assignments on login', function () {
    $drafter = User::factory()->create(['role' => Role::Drafter]);

    $this->actingAs($drafter)
        ->get(route('dashboard'))
        ->assertRedirect(route('drafter.assignments.index'));
});

test('drafter can access drafter assignments index', function () {
    $drafter = User::factory()->create(['role' => Role::Drafter]);

    $this->actingAs($drafter)
        ->get(route('drafter.assignments.index'))
        ->assertOk();
});

test('drafter can view a survey assignment from any main contractor', function () {
    $mc1 = MainContractor::factory()->create();
    $mc2 = MainContractor::factory()->create();

    $project1 = Project::factory()->create(['main_contractor_id' => $mc1->id]);
    $project2 = Project::factory()->create(['main_contractor_id' => $mc2->id]);

    $subcontractor = Subcontractor::factory()->forMainContractor($mc1)->create();

    $site1 = Site::factory()->create(['project_id' => $project1->id]);
    $site2 = Site::factory()->create(['project_id' => $project2->id]);

    $assignment1 = Assignment::factory()->create([
        'site_id' => $site1->id,
        'subcontractor_id' => $subcontractor->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Survey,
    ]);

    $assignment2 = Assignment::factory()->create([
        'site_id' => $site2->id,
        'subcontractor_id' => $subcontractor->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Survey,
    ]);

    $drafter = User::factory()->create(['role' => Role::Drafter]);

    $this->actingAs($drafter)
        ->get(route('drafter.assignments.show', $assignment1))
        ->assertOk();

    $this->actingAs($drafter)
        ->get(route('drafter.assignments.show', $assignment2))
        ->assertOk();
});

test('drafter cannot access admin routes', function () {
    $drafter = User::factory()->create(['role' => Role::Drafter]);

    $this->actingAs($drafter)
        ->get(route('admin.assignments.index'))
        ->assertForbidden();
});

test('drafter cannot access subcontractor routes', function () {
    $drafter = User::factory()->create(['role' => Role::Drafter]);

    $this->actingAs($drafter)
        ->get(route('subcontractor.assignments.index'))
        ->assertForbidden();
});

test('admin cannot access drafter routes', function () {
    $mc = MainContractor::factory()->create();
    $admin = User::factory()->create([
        'role' => Role::Admin,
        'main_contractor_id' => $mc->id,
    ]);

    $this->actingAs($admin)
        ->get(route('drafter.assignments.index'))
        ->assertForbidden();
});

test('subcontractor cannot access drafter routes', function () {
    $subcontractor = Subcontractor::factory()->create();
    $user = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $subcontractor->id,
    ]);

    $this->actingAs($user)
        ->get(route('drafter.assignments.index'))
        ->assertForbidden();
});

test('unauthenticated user cannot access drafter routes', function () {
    $this->get(route('drafter.assignments.index'))
        ->assertRedirect(route('login'));
});
