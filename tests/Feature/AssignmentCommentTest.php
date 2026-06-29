<?php

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentComment;
use App\Models\User;

test('admin can add a note to an accessible assignment', function () {
    $assignment = Assignment::factory()->create();
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $response = $this->actingAs($admin)->post(route('admin.assignments.comments.store', $assignment), [
        'body' => 'Technician reported waiting for site access.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('assignment_comments', [
        'assignment_id' => $assignment->id,
        'user_id' => $admin->id,
        'body' => 'Technician reported waiting for site access.',
    ]);
});

test('project manager can add a note to an assignment', function () {
    $assignment = Assignment::factory()->create();
    $projectManager = User::factory()->create(['role' => Role::ProjectManager]);

    $response = $this->actingAs($projectManager)->post(route('admin.assignments.comments.store', $assignment), [
        'body' => 'Project manager confirmed the field constraint with client.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('assignment_comments', [
        'assignment_id' => $assignment->id,
        'user_id' => $projectManager->id,
        'body' => 'Project manager confirmed the field constraint with client.',
    ]);
});

test('subcontractor can add a note to its own assignment', function () {
    $assignment = Assignment::factory()->create();
    $user = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $assignment->subcontractor_id,
    ]);

    $response = $this->actingAs($user)->post(route('subcontractor.assignments.comments.store', $assignment), [
        'body' => 'PLN visit rescheduled to tomorrow morning.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('assignment_comments', [
        'assignment_id' => $assignment->id,
        'user_id' => $user->id,
        'body' => 'PLN visit rescheduled to tomorrow morning.',
    ]);
});

test('subcontractor cannot add a note to another subcontractor assignment', function () {
    $assignment = Assignment::factory()->create();
    $user = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('subcontractor.assignments.comments.store', $assignment), [
        'body' => 'This should not be accepted.',
    ]);

    $response->assertForbidden();

    expect(AssignmentComment::query()->count())->toBe(0);
});

test('note body is required', function () {
    $assignment = Assignment::factory()->create();
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $response = $this->actingAs($admin)
        ->from(route('admin.assignments.show', $assignment))
        ->post(route('admin.assignments.comments.store', $assignment), [
            'body' => '',
        ]);

    $response->assertRedirect(route('admin.assignments.show', $assignment));
    $response->assertSessionHasErrors('body');

    expect(AssignmentComment::query()->count())->toBe(0);
});

test('assignment detail includes notes newest first', function () {
    $assignment = Assignment::factory()->create();
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    AssignmentComment::factory()->create([
        'assignment_id' => $assignment->id,
        'user_id' => $admin->id,
        'body' => 'Older note',
        'created_at' => now()->subDay(),
    ]);

    AssignmentComment::factory()->create([
        'assignment_id' => $assignment->id,
        'user_id' => $admin->id,
        'body' => 'Latest note',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.assignments.show', $assignment))
        ->assertInertia(fn ($page) => $page
            ->component('admin/assignments/Show')
            ->where('assignment.comments.0.body', 'Latest note')
            ->where('assignment.comments.1.body', 'Older note')
            ->where('assignment.comments.0.user.name', $admin->name)
        );
});

test('admin assignment list includes the latest note preview for each assignment', function () {
    $assignment = Assignment::factory()->create();
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    AssignmentComment::factory()->create([
        'assignment_id' => $assignment->id,
        'user_id' => $admin->id,
        'body' => 'Earlier site access note.',
        'created_at' => now()->subDay(),
    ]);

    AssignmentComment::factory()->create([
        'assignment_id' => $assignment->id,
        'user_id' => $admin->id,
        'body' => 'Latest note shown in the assignment list.',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.assignments.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/assignments/Index')
            ->where('sites.data.0.assignments.0.latest_comment.body', 'Latest note shown in the assignment list.')
            ->where('sites.data.0.assignments.0.latest_comment.user.name', $admin->name)
        );
});
