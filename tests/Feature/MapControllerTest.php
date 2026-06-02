<?php

use App\Enums\Role;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('admin.map.index'))->assertRedirect(route('login'));
});

test('subcontractors cannot access the map', function () {
    $user = User::factory()->create(['role' => Role::Subcontractor]);
    $this->actingAs($user)->get(route('admin.map.index'))->assertForbidden();
});

test('admins can access the map', function () {
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user)->get(route('admin.map.index'))->assertOk();
});

test('super admins can access the map', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);
    $this->actingAs($user)->get(route('admin.map.index'))->assertOk();
});

test('project managers can access the map', function () {
    $user = User::factory()->create(['role' => Role::ProjectManager]);
    $this->actingAs($user)->get(route('admin.map.index'))->assertOk();
});

test('map renders with expected props', function () {
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $this->get(route('admin.map.index'))->assertInertia(
        fn ($page) => $page
            ->component('admin/Map')
            ->has('sites')
            ->has('projects')
            ->has('subcontractors')
            ->has('machineTypes')
            ->has('provinces')
            ->has('stats')
            ->has('filters')
            ->has('stats.total_sites')
            ->has('stats.completed_sites')
            ->has('stats.in_progress_sites')
            ->has('stats.total_assignments'),
    );
});
