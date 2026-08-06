<?php

use App\Http\Middleware\AuthenticateAiMcp;
use App\Models\User;

beforeEach(function (): void {
    config([
        'ai.mcp.enabled' => true,
        'ai.mcp.token' => 'test-token',
        'ai.mcp.acting_as_user_id' => null,
        'ai.mcp.local_acting_as' => false,
    ]);
});

it('blocks unauthenticated requests', function (): void {
    $response = test()->postJson('/mcp/nexpm-ops', []);

    $response->assertStatus(401);
    expect($response->json('error.message'))->toContain('bearer');
});

it('blocks invalid token', function (): void {
    $response = test()->postJson(
        '/mcp/nexpm-ops',
        [],
        ['Authorization' => 'Bearer wrong-token']
    );

    $response->assertStatus(401);
});

it('blocks when acting user is missing', function (): void {
    config(['ai.mcp.token' => 'valid-token']);

    $response = test()->postJson(
        '/mcp/nexpm-ops',
        [],
        ['Authorization' => 'Bearer valid-token']
    );

    $response->assertStatus(403);
});

it('accepts valid admin user with correct token', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    config([
        'ai.mcp.token' => 'admin-token',
        'ai.mcp.acting_as_user_id' => $admin->id,
    ]);

    $response = test()->postJson(
        '/mcp/nexpm-ops',
        ['method' => 'list_users'],
        ['Authorization' => 'Bearer admin-token']
    );

    $response->assertStatus(200);
});

it('returns 404 when mcp is disabled', function (): void {
    config(['ai.mcp.enabled' => false]);

    $response = test()->postJson('/mcp/nexpm-ops', []);

    $response->assertStatus(404);
});
