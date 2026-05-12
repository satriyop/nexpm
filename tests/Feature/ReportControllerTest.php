<?php

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Report;
use App\Models\User;

test('admin can view reports index', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    Assignment::factory()->count(2)->create(['status' => AssignmentStatus::Verified]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/Index')
            ->has('ssrAssignments')
            ->has('bastAssignments')
            ->has('recentReports')
        );
});

test('admin can generate a report from verified assignments', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $assignments = Assignment::factory()->count(3)->create(['status' => AssignmentStatus::Verified]);
    $ids = $assignments->pluck('id')->toArray();

    $this->actingAs($admin)
        ->post(route('admin.reports.store'), [
            'report_type' => 'SSR',
            'assignment_ids' => $ids,
        ])
        ->assertRedirect(route('admin.reports.index'));

    expect(Report::count())->toBe(1);

    $report = Report::first();
    expect($report->assignments()->count())->toBe(3);

    foreach ($assignments as $assignment) {
        expect($assignment->fresh()->status)->toBe(AssignmentStatus::Reported);
    }
});

test('store rejects non-verified assignments', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $pending = Assignment::factory()->create(['status' => AssignmentStatus::Pending]);

    $this->actingAs($admin)
        ->post(route('admin.reports.store'), [
            'report_type' => 'SSR',
            'assignment_ids' => [$pending->id],
        ])
        ->assertStatus(422);
});

test('admin can generate daily report without selecting assignments', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    Assignment::factory()->count(3)->create(['status' => AssignmentStatus::Verified]);
    Assignment::factory()->create(['status' => AssignmentStatus::Pending]);
    Assignment::factory()->create(['status' => AssignmentStatus::Drop]);

    $this->actingAs($admin)
        ->post(route('admin.reports.store'), ['report_type' => 'DAILY'])
        ->assertRedirect(route('admin.reports.index'));

    expect(Report::count())->toBe(1);
    // 4 non-DROPPED assignments should be attached
    expect(Report::first()->assignments()->count())->toBe(4);
});

test('store requires at least one assignment id', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($admin)
        ->post(route('admin.reports.store'), ['assignment_ids' => []])
        ->assertSessionHasErrors('assignment_ids');
});

test('guests cannot access reports routes', function () {
    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    $this->post(route('admin.reports.store'))->assertRedirect(route('login'));
});
