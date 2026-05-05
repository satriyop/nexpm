<?php

use App\Enums\Role;
use App\Models\Client;
use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\SitePhoto;
use App\Models\SiteType;
use App\Models\Subcontractor;
use App\Models\SubcontractorType;
use App\Models\User;
use Database\Seeders\ConfigTypesSeeder;
use Database\Seeders\SuperAdminSeeder;

test('config types seeder seeds defaults', function () {
    $this->seed(ConfigTypesSeeder::class);

    expect(SubcontractorType::pluck('name')->sort()->values()->all())
        ->toBe(['Construction', 'PLN'])
        ->and(SiteType::pluck('name')->sort()->values()->all())
        ->toBe(['BSS', 'EVCS'])
        ->and(MachineType::pluck('name')->sort()->values()->all())
        ->toBe(['22kVA', '50kVA', '7.7kVA']);
});

test('super admin seeder creates the super admin user', function () {
    $this->seed(SuperAdminSeeder::class);

    $admin = User::where('email', 'superadmin@nexpm.com')->firstOrFail();

    expect($admin->role)->toBe(Role::SuperAdmin)
        ->and($admin->isSuperAdmin())->toBeTrue()
        ->and($admin->isAdmin())->toBeFalse()
        ->and($admin->isSubcontractor())->toBeFalse()
        ->and($admin->main_contractor_id)->toBeNull()
        ->and($admin->subcontractor_id)->toBeNull();
});

test('main contractor has clients projects subcontractors and users', function () {
    $contractor = MainContractor::factory()->create();
    $client = Client::factory()->for($contractor, 'mainContractor')->create();
    $project = Project::factory()
        ->for($contractor, 'mainContractor')
        ->for($client)
        ->create();

    expect($contractor->clients)->toHaveCount(1)
        ->and($contractor->projects)->toHaveCount(1)
        ->and($project->client->is($client))->toBeTrue()
        ->and($project->mainContractor->is($contractor))->toBeTrue();
});

test('site belongs to project site type and machine type and has photos', function () {
    $site = Site::factory()->create();
    SitePhoto::create(['site_id' => $site->id, 'path' => 'photos/example.jpg']);

    $site->refresh()->load(['project', 'siteType', 'machineType', 'photos']);

    expect($site->project)->not->toBeNull()
        ->and($site->siteType)->not->toBeNull()
        ->and($site->machineType)->not->toBeNull()
        ->and($site->photos)->toHaveCount(1)
        ->and($site->photos->first()->path)->toBe('photos/example.jpg');
});

test('subcontractor user can be linked to subcontractor and main contractor', function () {
    $contractor = MainContractor::factory()->create();
    $subcontractor = Subcontractor::factory()
        ->for($contractor, 'mainContractor')
        ->create();

    $user = User::factory()->create([
        'role' => Role::Subcontractor,
        'main_contractor_id' => $contractor->id,
        'subcontractor_id' => $subcontractor->id,
    ]);

    expect($user->isSubcontractor())->toBeTrue()
        ->and($user->mainContractor->is($contractor))->toBeTrue()
        ->and($user->subcontractor->is($subcontractor))->toBeTrue()
        ->and($subcontractor->user->is($user))->toBeTrue();
});

test('role enum has expected cases', function () {
    expect(Role::SuperAdmin->value)->toBe('super_admin')
        ->and(Role::Admin->value)->toBe('admin')
        ->and(Role::Subcontractor->value)->toBe('subcontractor');
});
