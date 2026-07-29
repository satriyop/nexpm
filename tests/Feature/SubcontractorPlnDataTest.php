<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentPlnData;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function plnAssignmentForSubcontractor(?Subcontractor $subcontractor = null): Assignment
{
    $subcontractor ??= Subcontractor::factory()->create();

    return Assignment::factory()->plnConnection()->create([
        'subcontractor_id' => $subcontractor->id,
        'status' => AssignmentStatus::Pending,
    ]);
}

function subcontractorUserFor(Assignment $assignment): User
{
    return User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $assignment->subcontractor_id,
    ]);
}

test('subcontractor can upload kwh meter photo on own pln assignment', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $user = subcontractorUserFor($assignment);

    $this->actingAs($user)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'type_rate' => 'B-3',
            'foto_kwh' => UploadedFile::fake()->image('kwh-meter.jpg'),
        ])
        ->assertRedirect();

    $pln = $assignment->plnData()->firstOrFail();

    expect($pln->foto_kwh)->not->toBeNull()
        ->and($pln->type_rate)->toBe('B-3');

    Storage::disk('public')->assertExists($pln->foto_kwh);
});

test('subcontractor show page includes existing foto_kwh path', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $path = UploadedFile::fake()->image('existing-kwh.jpg')->store('pln', 'public');

    AssignmentPlnData::factory()->create([
        'assignment_id' => $assignment->id,
        'foto_kwh' => $path,
    ]);

    $user = subcontractorUserFor($assignment);

    $this->actingAs($user)
        ->get(route('subcontractor.assignments.show', $assignment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subcontractor/assignments/Show')
            ->where('assignment.pln_data.foto_kwh', $path)
        );
});

test('replacing kwh photo stores new file and deletes previous', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $oldPath = UploadedFile::fake()->image('old-kwh.jpg')->store('pln', 'public');

    AssignmentPlnData::factory()->create([
        'assignment_id' => $assignment->id,
        'foto_kwh' => $oldPath,
    ]);

    Storage::disk('public')->assertExists($oldPath);

    $user = subcontractorUserFor($assignment);

    $this->actingAs($user)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'foto_kwh' => UploadedFile::fake()->image('new-kwh.jpg'),
        ])
        ->assertRedirect();

    $pln = $assignment->fresh()->plnData()->firstOrFail();

    expect($pln->foto_kwh)->not->toBe($oldPath);

    Storage::disk('public')->assertExists($pln->foto_kwh);
    Storage::disk('public')->assertMissing($oldPath);
});

test('saving pln data without new photo preserves existing foto_kwh', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $path = UploadedFile::fake()->image('keep-kwh.jpg')->store('pln', 'public');

    AssignmentPlnData::factory()->create([
        'assignment_id' => $assignment->id,
        'foto_kwh' => $path,
        'type_rate' => 'A-1',
    ]);

    $user = subcontractorUserFor($assignment);

    $this->actingAs($user)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'type_rate' => 'B-2',
        ])
        ->assertRedirect();

    $pln = $assignment->fresh()->plnData()->firstOrFail();

    expect($pln->foto_kwh)->toBe($path)
        ->and($pln->type_rate)->toBe('B-2');

    Storage::disk('public')->assertExists($path);
});

test('non image foto_kwh is rejected', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $user = subcontractorUserFor($assignment);

    $this->actingAs($user)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'foto_kwh' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('foto_kwh');

    expect($assignment->plnData()->exists())->toBeFalse();
});

test('subcontractor cannot update another subcontractors pln data', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $otherUser = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => Subcontractor::factory()->create()->id,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'foto_kwh' => UploadedFile::fake()->image('kwh.jpg'),
        ])
        ->assertForbidden();
});

test('verified pln assignment rejects photo upload', function () {
    Storage::fake('public');

    $assignment = plnAssignmentForSubcontractor();
    $assignment->update(['status' => AssignmentStatus::Verified]);

    $user = subcontractorUserFor($assignment);

    $this->actingAs($user)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'foto_kwh' => UploadedFile::fake()->image('kwh.jpg'),
        ])
        ->assertStatus(422);
});

test('non pln activity type rejects pln update', function () {
    Storage::fake('public');

    $subcontractor = Subcontractor::factory()->create();
    $assignment = Assignment::factory()->survey()->create([
        'subcontractor_id' => $subcontractor->id,
        'status' => AssignmentStatus::Pending,
        'activity_type' => ActivityType::Survey,
    ]);

    $user = User::factory()->create([
        'role' => Role::Subcontractor,
        'subcontractor_id' => $subcontractor->id,
    ]);

    $this->actingAs($user)
        ->patch(route('subcontractor.assignments.pln', $assignment), [
            'foto_kwh' => UploadedFile::fake()->image('kwh.jpg'),
        ])
        ->assertStatus(422);
});
