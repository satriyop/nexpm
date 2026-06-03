<?php

use App\Models\Site;
use Illuminate\Support\Facades\Http;

test('backfills coordinates from direct google maps urls', function () {
    Http::fake();

    $site = Site::factory()->create([
        'google_map_url' => 'https://maps.google.com/?q=-6.917464,107.619123',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->artisan('sites:backfill-coordinates-from-google-maps', [
        '--limit' => 10,
        '--sleep' => 0,
        '--force' => true,
    ])
        ->assertSuccessful();

    expect($site->refresh())
        ->latitude->toBe('-6.9174640')
        ->longitude->toBe('107.6191230');

    Http::assertNothingSent();
});

test('backfills coordinates from short google maps redirect urls', function () {
    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Test/@-7.725123,110.345678,17z',
        ]),
    ]);

    $site = Site::factory()->create([
        'google_map_url' => 'https://maps.app.goo.gl/abc123',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->artisan('sites:backfill-coordinates-from-google-maps', [
        '--limit' => 10,
        '--sleep' => 0,
        '--force' => true,
    ])
        ->assertSuccessful();

    expect($site->refresh())
        ->latitude->toBe('-7.7251230')
        ->longitude->toBe('110.3456780');

    Http::assertSentCount(1);
});

test('dry run resolves coordinates without saving them', function () {
    Http::fake();

    $site = Site::factory()->create([
        'google_map_url' => 'https://maps.google.com/?q=-6.917464,107.619123',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->artisan('sites:backfill-coordinates-from-google-maps', [
        '--limit' => 10,
        '--sleep' => 0,
        '--dry-run' => true,
    ])
        ->assertSuccessful();

    expect($site->refresh())
        ->latitude->toBeNull()
        ->longitude->toBeNull();
});

test('does not request non-google imported urls', function () {
    Http::fake();

    $site = Site::factory()->create([
        'google_map_url' => 'https://example.com/?q=-6.917464,107.619123',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->artisan('sites:backfill-coordinates-from-google-maps', [
        '--limit' => 10,
        '--sleep' => 0,
        '--force' => true,
    ])
        ->assertSuccessful();

    expect($site->refresh())
        ->latitude->toBeNull()
        ->longitude->toBeNull();

    Http::assertNothingSent();
});
