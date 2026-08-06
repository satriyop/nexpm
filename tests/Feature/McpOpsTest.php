<?php

use App\Models\McpAuditLog;
use App\Models\User;
use App\Services\Ai\AiAssistantService;

beforeEach(function (): void {
    $this->artisan('migrate', ['--force' => true]);

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

it('accepts an authorized admin and records the tool call', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    config([
        'ai.mcp.token' => 'admin-token',
        'ai.mcp.acting_as_user_id' => $admin->id,
    ]);

    $this->mock(AiAssistantService::class, function ($mock): void {
        $mock->shouldReceive('runTool')
            ->once()
            ->with('list_users', [])
            ->andReturn(['total_users_returned' => 0]);
    });

    $response = test()->postJson(
        '/mcp/nexpm-ops',
        [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'list_users', 'arguments' => []],
        ],
        ['Authorization' => 'Bearer admin-token']
    );

    $response->assertStatus(200);
    expect(McpAuditLog::query()->where('tool_name', 'list_users')->count())->toBe(1);
});

it('allows global admin roles', function (string $role): void {
    $admin = User::factory()->create(['role' => $role]);
    config([
        'ai.mcp.token' => 'global-admin-token',
        'ai.mcp.acting_as_user_id' => $admin->id,
    ]);

    $this->mock(AiAssistantService::class, function ($mock): void {
        $mock->shouldReceive('runTool')->once()->andReturn(['ok' => true]);
    });

    test()->postJson(
        '/mcp/nexpm-ops',
        [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'list_users', 'arguments' => []],
        ],
        ['Authorization' => 'Bearer global-admin-token']
    )->assertStatus(200);
})->with(['super_admin', 'project_manager']);

it('denies non-admin roles', function (): void {
    $user = User::factory()->create(['role' => 'drafter']);
    config(['ai.mcp.token' => 'drafter-token', 'ai.mcp.acting_as_user_id' => $user->id]);

    test()->postJson(
        '/mcp/nexpm-ops',
        [],
        ['Authorization' => 'Bearer drafter-token']
    )->assertStatus(403);
});

it('returns a generic error when a tool fails', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    config(['ai.mcp.token' => 'error-token', 'ai.mcp.acting_as_user_id' => $admin->id]);

    $this->mock(AiAssistantService::class, function ($mock): void {
        $mock->shouldReceive('runTool')->once()->andThrow(new RuntimeException('secret SQL/path detail'));
    });

    $response = test()->postJson(
        '/mcp/nexpm-ops',
        [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'list_users', 'arguments' => []],
        ],
        ['Authorization' => 'Bearer error-token']
    );

    $response->assertStatus(200);
    expect((string) $response->json('error.message'))->not->toContain('secret SQL/path detail');
});

it('returns 404 when mcp is disabled', function (): void {
    config(['ai.mcp.enabled' => false]);

    $response = test()->postJson('/mcp/nexpm-ops', []);

    $response->assertStatus(404);
});
