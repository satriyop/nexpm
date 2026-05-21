<?php

use App\Enums\Role;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\User;

test('project manager can access dashboard', function () {
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->get(route('dashboard'))
        ->assertOk();
});

test('project manager can access admin assignments index', function () {
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->get(route('admin.assignments.index'))
        ->assertOk();
});

test('project manager can access admin projects index', function () {
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->get(route('admin.projects.index'))
        ->assertOk();
});

test('project manager cannot POST to create a project (read-only)', function () {
    $mc = MainContractor::factory()->create();
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->post(route('admin.projects.store'), [
            'name' => 'Test Project',
            'main_contractor_id' => $mc->id,
        ])
        ->assertForbidden();
});

test('project manager cannot bulk drop assignments (read-only)', function () {
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->post(route('admin.assignments.bulk-drop'), [
            'assignment_ids' => [],
        ])
        ->assertForbidden();
});

test('project manager cannot access drafter routes', function () {
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->get(route('drafter.assignments.index'))
        ->assertForbidden();
});

test('project manager cannot access subcontractor routes', function () {
    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($pm)
        ->get(route('subcontractor.assignments.index'))
        ->assertForbidden();
});

test('admin is scoped to their MC but project manager can see all MCs', function () {
    $mc1 = MainContractor::factory()->create();
    $mc2 = MainContractor::factory()->create();

    $project = Project::factory()->create(['main_contractor_id' => $mc2->id]);

    $admin = User::factory()->create([
        'role' => Role::Admin,
        'main_contractor_id' => $mc1->id,
    ]);

    $pm = User::factory()->create(['role' => Role::ProjectManager]);

    $this->actingAs($admin)
        ->get(route('admin.projects.show', $project))
        ->assertForbidden();

    $this->actingAs($pm)
        ->get(route('admin.projects.show', $project))
        ->assertOk();
});

test('unauthenticated user cannot access admin routes', function () {
    $this->get(route('admin.assignments.index'))
        ->assertRedirect(route('login'));
});
