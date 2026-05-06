<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\AssignmentSurveyData;
use App\Models\User;

test('saving complete survey data advances assignment to DOCUMENT', function () {
    $assignment = Assignment::factory()->survey()->create();

    AssignmentSurveyData::factory()
        ->complete()
        ->create(['assignment_id' => $assignment->id]);

    $assignment->refresh();

    expect($assignment->status)->toBe(AssignmentStatus::Document);
});

test('saving survey schedule date (incomplete) advances assignment to SURVEY', function () {
    $assignment = Assignment::factory()->survey()->create();

    AssignmentSurveyData::factory()->create([
        'assignment_id' => $assignment->id,
        'ss_schedule_date' => now()->toDateString(),
    ]);

    $assignment->refresh();

    expect($assignment->status)->toBe(AssignmentStatus::Survey);
});

test('saving incomplete survey data without schedule leaves assignment PENDING', function () {
    $assignment = Assignment::factory()->survey()->create();

    AssignmentSurveyData::factory()->create([
        'assignment_id' => $assignment->id,
        'surveyor_name' => 'Some Surveyor',
    ]);

    $assignment->refresh();

    expect($assignment->status)->toBe(AssignmentStatus::Pending);
});

test('status is forward-only — clearing a required field does not roll back from DOCUMENT', function () {
    $assignment = Assignment::factory()->survey()->create();

    $survey = AssignmentSurveyData::factory()
        ->complete()
        ->create(['assignment_id' => $assignment->id]);

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Document);

    // Clearing a field does not roll back — status only advances forward.
    $survey->surveyor_name = null;
    $survey->save();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Document);
});

test('admin can verify an assignment from a verifiable status', function () {
    $assignment = Assignment::factory()->survey()->create([
        'status' => AssignmentStatus::Document,
    ]);

    $admin = User::factory()->create(['role' => Role::Admin]);

    $assignment->markVerified($admin);

    expect($assignment->status)->toBe(AssignmentStatus::Verified)
        ->and($assignment->verified_by)->toBe($admin->id)
        ->and($assignment->verified_at)->not->toBeNull();
});

test('admin can send BAST assignment to revision with comment', function () {
    $assignment = Assignment::factory()->bast()->create([
        'status' => AssignmentStatus::Completed,
    ]);

    $assignment->sendToRevision('Please re-upload the satellite photo.');

    expect($assignment->status)->toBe(AssignmentStatus::Revision)
        ->and($assignment->revision_comment)->toBe('Please re-upload the satellite photo.');
});

test('completing BAST data after revision auto-flips status back to COMPLETED', function () {
    $assignment = Assignment::factory()->bast()->create([
        'status' => AssignmentStatus::Revision,
    ]);

    // Create BAST data record without the complete state
    $bast = AssignmentBastData::create(['assignment_id' => $assignment->id]);

    // Upload all required photos (mirrors the subcontractor photo upload flow)
    foreach (AssignmentBastData::REQUIRED_CHECKPOINTS as $key) {
        AssignmentBastPhoto::create([
            'assignment_bast_data_id' => $bast->id,
            'section' => 'required',
            'checkpoint_key' => $key,
            'photo_path' => 'bast/test/'.$key.'.jpg',
        ]);
    }

    // Saving the BAST data record triggers the observer, which now sees all photos
    $bast->plant_name = 'Test Plant';
    $bast->installation_date = now()->toDateString();
    $bast->commissioning_date = now()->toDateString();
    $bast->save();

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
        ->and(AssignmentStatus::Document->color())->toBe('indigo')
        ->and(AssignmentStatus::Revision->color())->toBe('amber')
        ->and(AssignmentStatus::Verified->color())->toBe('green')
        ->and(AssignmentStatus::Reported->color())->toBe('purple');
});
