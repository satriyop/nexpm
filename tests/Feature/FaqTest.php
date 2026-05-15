<?php

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to login', function () {
    get('/faq')->assertRedirect('/login');
});

it('is accessible to subcontractor', function () {
    $user = User::factory()->create(['role' => Role::Subcontractor]);

    actingAs($user)->get('/faq')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faq/Index')
            ->where('userRole', 'subcontractor')
        );
});

it('is accessible to admin', function () {
    $user = User::factory()->create(['role' => Role::Admin]);

    actingAs($user)->get('/faq')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faq/Index')
            ->where('userRole', 'admin')
        );
});

it('is accessible to super admin', function () {
    $user = User::factory()->create(['role' => Role::SuperAdmin]);

    actingAs($user)->get('/faq')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('faq/Index')
            ->where('userRole', 'super_admin')
        );
});
