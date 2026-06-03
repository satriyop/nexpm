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

test('normalizes whitespace artifacts in google maps urls before resolving redirects', function () {
    Http::fake([
        'https://maps.app.goo.gl/Y7kswcMzEgw3BkV37?g_st=aw' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Test/@-6.445507,107.0437936,17z',
        ]),
    ]);

    $site = Site::factory()->create([
        'google_map_url' => "https://maps.app.goo.gl/Y7kswcMzEgw3BkV37?g_ st\n=aw",
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
        ->latitude->toBe('-6.4455070')
        ->longitude->toBe('107.0437936');

    Http::assertSent(fn ($request) => $request->url() === 'https://maps.app.goo.gl/Y7kswcMzEgw3BkV37?g_st=aw');
});

test('backfills coordinates from resolved google maps response body', function () {
    $placeUrl = 'https://www.google.com/maps/place/Masjid+Arief+Rahman/data=!4m2!3m1!1s0x2e69f7d9db7725ef:0x3ab3670bb98555de';

    Http::fake([
        'https://maps.app.goo.gl/body123' => Http::response('', 302, [
            'Location' => $placeUrl,
        ]),
        $placeUrl => Http::response('<script>APP_INITIALIZATION_STATE=[null,null,[-6.445507,107.0437936]];</script>', 200),
    ]);

    $site = Site::factory()->create([
        'google_map_url' => 'https://maps.app.goo.gl/body123',
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
        ->latitude->toBe('-6.4455070')
        ->longitude->toBe('107.0437936');

    Http::assertSentCount(2);
});

test('backfills coordinates from google maps place cid lookup when redirect url has no coordinates', function () {
    $placeUrl = 'https://www.google.com/maps/place/Masjid+Arief+Rahman/data=!4m2!3m1!1s0x2e69f7d9db7725ef:0x3ab3670bb98555de';

    Http::fake([
        'https://maps.app.goo.gl/Y7kswcMzEgw3BkV37?g_st=aw' => Http::response('', 302, [
            'Location' => $placeUrl,
        ]),
        $placeUrl => Http::response('<html>No coordinates in this Google place payload.</html>', 200),
        'https://www.google.com/maps?cid=4229837775085852126' => Http::response('<meta content="https://maps.google.com/maps/api/staticmap?center=-6.164492%2C106.786891&amp;zoom=14" itemprop="image">', 200),
    ]);

    $site = Site::factory()->create([
        'google_map_url' => 'https://maps.app.goo.gl/Y7kswcMzEgw3BkV37?g_ st=aw',
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
        ->latitude->toBe('-6.1644920')
        ->longitude->toBe('106.7868910');

    Http::assertSent(fn ($request) => $request->url() === 'https://www.google.com/maps?cid=4229837775085852126');
});

test('backfills coordinates from google maps static map center metadata', function () {
    Http::fake([
        'https://maps.app.goo.gl/staticmap123' => Http::response('<meta content="https://maps.google.com/maps/api/staticmap?center=-7.7517602%2C110.686184&amp;zoom=14" itemprop="image">', 200),
    ]);

    $site = Site::factory()->create([
        'google_map_url' => 'https://maps.app.goo.gl/staticmap123',
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
        ->latitude->toBe('-7.7517602')
        ->longitude->toBe('110.6861840');
});

test('normalizes malformed short google maps urls before resolving redirects', function () {
    Http::fake([
        'https://maps.app.goo.gl/5oyWXMkPFEr42yE7' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Test/@-7.725123,110.345678,17z',
        ]),
        'https://maps.app.goo.gl/JeufXzTBc3gKuH66' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Test/@-7.725124,110.345679,17z',
        ]),
    ]);

    $firstSite = Site::factory()->create([
        'google_map_url' => 'https://maps.app.goo.gl/?5oyWXMkPFEr42yE7',
        'latitude' => null,
        'longitude' => null,
    ]);

    $secondSite = Site::factory()->create([
        'google_map_url' => 'https://maps.app.goo.gl/JeufXz?TBc3gKuH66',
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->artisan('sites:backfill-coordinates-from-google-maps', [
        '--limit' => 10,
        '--sleep' => 0,
        '--force' => true,
    ])
        ->assertSuccessful();

    expect($firstSite->refresh())
        ->latitude->toBe('-7.7251230')
        ->longitude->toBe('110.3456780')
        ->and($secondSite->refresh())
        ->latitude->toBe('-7.7251240')
        ->longitude->toBe('110.3456790');
});

test('allows google share urls when resolving redirects', function () {
    Http::fake([
        'https://share.google/GbIywcGaM18FbfooE' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Test/@-6.917464,107.619123,17z',
        ]),
    ]);

    $site = Site::factory()->create([
        'google_map_url' => 'https://share.google/GbIywcGaM18FbfooE',
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
