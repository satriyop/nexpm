<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
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
        'status' => AssignmentStatus::Submitted,
    ]);

    $assignment->sendToRevision('Please re-upload the satellite photo.');

    expect($assignment->status)->toBe(AssignmentStatus::Revision)
        ->and($assignment->revision_comment)->toBe('Please re-upload the satellite photo.');
});

test('subcontractor can submit BAST assignment for review from PENDING or REVISION', function () {
    $assignment = Assignment::factory()->bast()->create(['status' => AssignmentStatus::Pending]);
    $user = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $assignment->subcontractor_id,
    ]);

    $this->actingAs($user)->post(route('subcontractor.assignments.submit', $assignment));

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Submitted);

    // After revision, subcontractor can re-submit
    $assignment->update(['status' => AssignmentStatus::Revision]);
    $this->actingAs($user)->post(route('subcontractor.assignments.submit', $assignment));

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Submitted);
});

test('admin can update full BAST commissioning data', function () {
    $assignment = Assignment::factory()->bast()->create();
    AssignmentBastData::factory()->create([
        'assignment_id' => $assignment->id,
        'sim_provider' => 'Indosat',
    ]);
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);

    $response = $this->actingAs($admin)->patch(route('admin.assignments.admin-bast', $assignment), [
        'plant_name' => 'Planet Ban KEBON AGUNG TRIHANGGO',
        'plant_address' => 'Jl. Kebon Agung',
        'plant_coordinate' => '-7.7300, 110.3400',
        'gmaps_link' => 'https://maps.app.goo.gl/example',
        'charger_type' => 'BSS 65 IP',
        'sn_unit' => 'BSS-255141000550',
        'id_pln' => 'ID-PLN-001',
        'sim_provider' => 'Indosat',
        'installation_vendor' => 'CV SIGMATEC',
        'pic_vendor_contact' => 'Topan Gilas',
        'installation_date' => '2026-04-12',
        'nomor_simcard' => '081431142676',
        'commissioning_date' => '2026-05-29',
        'customer' => 'PT VGreen Global Charging Station Investment Indonesia',
        'go_live_date_pln_pass' => '2026-04-13',
        'go_live_date_pln' => '2026-04-14',
    ]);

    $response->assertRedirect();

    expect($assignment->bastData()->first())
        ->plant_name->toBe('Planet Ban KEBON AGUNG TRIHANGGO')
        ->customer->toBe('PT VGreen Global Charging Station Investment Indonesia')
        ->charger_type->toBe('BSS 65 IP')
        ->sn_unit->toBe('BSS-255141000550')
        ->id_pln->toBe('ID-PLN-001')
        ->installation_vendor->toBe('CV SIGMATEC')
        ->nomor_simcard->toBe('081431142676')
        ->commissioning_date->toDateString()->toBe('2026-05-29');
});

test('survey revision is not a valid admin revision action', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);
    $assignment = Assignment::factory()->survey()->create([
        'status' => AssignmentStatus::Document,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.assignments.revise', $assignment), [
        'revision_comment' => 'Please revise the survey photos.',
    ]);

    $response->assertUnprocessable();

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Document);
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
        ->and(AssignmentStatus::Submitted->color())->toBe('blue')
        ->and(AssignmentStatus::Document->color())->toBe('indigo')
        ->and(AssignmentStatus::Revision->color())->toBe('amber')
        ->and(AssignmentStatus::Verified->color())->toBe('green')
        ->and(AssignmentStatus::Reported->color())->toBe('purple');
});
