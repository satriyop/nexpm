<?php

use App\Models\User;
use App\Models\Assignment;
use App\Enums\Role;

test('super admin can access admin assignment list', function () {
    $user = User::factory()->create([
        'role' => Role::SuperAdmin,
    ]);

    Assignment::factory()->count(3)->create();

    $response = $this->actingAs($user)
        ->get(route('admin.assignments.index'));

    $response->assertSuccessful();
});
