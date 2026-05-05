<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentSurveyData;
use App\Models\User;

test('saving complete survey data marks assignment as completed', function () {
    $assignment = Assignment::factory()->survey()->create();

    AssignmentSurveyData::factory()
        ->complete()
        ->create(['assignment_id' => $assignment->id]);

    $assignment->refresh();

    expect($assignment->status)->toBe(AssignmentStatus::Completed);
});

test('saving incomplete survey data leaves assignment pending', function () {
    $assignment = Assignment::factory()->survey()->create();

    AssignmentSurveyData::factory()->create([
        'assignment_id' => $assignment->id,
        'surveyor_name' => 'Some Surveyor',
    ]);

    $assignment->refresh();

    expect($assignment->status)->toBe(AssignmentStatus::Pending);
});

test('clearing required field rolls completed assignment back to pending', function () {
    $assignment = Assignment::factory()->survey()->create();

    $survey = AssignmentSurveyData::factory()
        ->complete()
        ->create(['assignment_id' => $assignment->id]);

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Completed);

    $survey->surveyor_name = null;
    $survey->save();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Pending);
});

test('admin can verify a completed assignment', function () {
    $assignment = Assignment::factory()->survey()->create([
        'status' => AssignmentStatus::Completed,
    ]);

    $admin = User::factory()->create(['role' => Role::Admin]);

    $assignment->markVerified($admin);

    expect($assignment->status)->toBe(AssignmentStatus::Verified)
        ->and($assignment->verified_by)->toBe($admin->id)
        ->and($assignment->verified_at)->not->toBeNull();
});

test('admin can send assignment to revision with comment', function () {
    $assignment = Assignment::factory()->survey()->create([
        'status' => AssignmentStatus::Completed,
    ]);

    $assignment->sendToRevision('Please re-upload the satellite photo.');

    expect($assignment->status)->toBe(AssignmentStatus::Revision)
        ->and($assignment->revision_comment)->toBe('Please re-upload the satellite photo.');
});

test('completing data after revision auto-flips status back to completed', function () {
    $assignment = Assignment::factory()->survey()->create([
        'status' => AssignmentStatus::Revision,
    ]);

    AssignmentSurveyData::factory()
        ->complete()
        ->create(['assignment_id' => $assignment->id]);

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Completed);
});

test('construction assignment is locked when wo number missing', function () {
    $assignment = Assignment::factory()->construction()->create();

    expect($assignment->isLocked())->toBeTrue();
});

test('construction assignment unlocks once wo number set', function () {
    $assignment = Assignment::factory()->construction()->create();

    $assignment->constructionData()->create([
        'cons_wo_number' => 'WO-123',
    ]);

    $assignment->refresh()->load('constructionData');

    expect($assignment->isLocked())->toBeFalse();
});

test('activity type and status enum labels and colors are wired', function () {
    expect(ActivityType::Survey->label())->toBe('Survey')
        ->and(ActivityType::PlnConnection->label())->toBe('PLN Connection')
        ->and(ActivityType::Construction->label())->toBe('Construction')
        ->and(ActivityType::Bast->label())->toBe('BAST')
        ->and(AssignmentStatus::Pending->color())->toBe('gray')
        ->and(AssignmentStatus::Completed->color())->toBe('blue')
        ->and(AssignmentStatus::Revision->color())->toBe('amber')
        ->and(AssignmentStatus::Verified->color())->toBe('green')
        ->and(AssignmentStatus::Reported->color())->toBe('purple');
});
