<?php

use App\Enums\Role;
use App\Models\Site;
use App\Models\User;

test('site financial date fields can be saved', function () {
    $admin = User::factory()->create(['role' => Role::SuperAdmin]);
    $site = Site::factory()->create([
        'invoice_submission_date' => null,
        'dp_35_date' => null,
        'invoice_60_submission_date' => null,
        'payment_60_date' => null,
        'invoice_5_submission_date' => null,
        'payment_5_date' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.sites.update', $site), [
            'location_name' => $site->location_name,
            'address' => $site->address,
            'province' => $site->province,
            'city' => $site->city,
            'power_kva' => $site->power_kva,
            'invoice_submission_date' => '2026-06-05',
            'dp_35_date' => '2026-06-05',
            'invoice_60_submission_date' => '2026-07-05',
            'payment_60_date' => '2026-07-12',
            'invoice_5_submission_date' => '2026-08-05',
            'payment_5_date' => '2026-08-12',
        ])
        ->assertRedirect(route('admin.sites.edit', $site));

    $site->refresh();

    expect($site->invoice_submission_date?->toDateString())->toBe('2026-06-05')
        ->and($site->dp_35_date?->toDateString())->toBe('2026-06-05')
        ->and($site->invoice_60_submission_date?->toDateString())->toBe('2026-07-05')
        ->and($site->payment_60_date?->toDateString())->toBe('2026-07-12')
        ->and($site->invoice_5_submission_date?->toDateString())->toBe('2026-08-05')
        ->and($site->payment_5_date?->toDateString())->toBe('2026-08-12');
});
