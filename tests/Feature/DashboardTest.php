<?php

use App\Enums\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard renders the Dashboard component with correct props', function () {
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard')
            ->has('filters')
    );
});

test('super admin can visit the dashboard', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});
