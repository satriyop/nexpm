<?php

use App\Enums\Role;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
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

test('map returns sites that have coordinates for markers', function () {
    $mainContractor = MainContractor::factory()->create();
    $user = User::factory()->create([
        'role' => Role::Admin,
        'main_contractor_id' => $mainContractor->id,
    ]);
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);

    Site::factory()->for($project)->create([
        'site_code' => 'SITE-MARKER',
        'latitude' => '-6.200000',
        'longitude' => '106.816666',
    ]);
    Site::factory()->for($project)->create([
        'site_code' => 'SITE-NO-COORDS',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->actingAs($user)
        ->get(route('admin.map.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Map')
            ->has('sites', 1)
            ->where('sites.0.site_code', 'SITE-MARKER')
            ->where('sites.0.latitude', -6.2)
            ->where('sites.0.longitude', 106.816666)
        );
});

test('map derives marker coordinates from parseable google map urls', function () {
    $mainContractor = MainContractor::factory()->create();
    $user = User::factory()->create([
        'role' => Role::Admin,
        'main_contractor_id' => $mainContractor->id,
    ]);
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);

    Site::factory()->for($project)->create([
        'site_code' => 'SITE-GMAP',
        'google_map_url' => 'https://www.google.com/maps/place/Test/data=!3d-6.917464!4d107.619123',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->actingAs($user)
        ->get(route('admin.map.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Map')
            ->has('sites', 1)
            ->where('sites.0.site_code', 'SITE-GMAP')
            ->where('sites.0.latitude', -6.917464)
            ->where('sites.0.longitude', 107.619123)
        );
});
