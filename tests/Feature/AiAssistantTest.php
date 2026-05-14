<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('only super admins can ask the assistant', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Find blocked assignments'])
        ->assertForbidden();
});

test('super admins can ask the assistant and messages are stored', function () {
    config(['services.deepseek.key' => 'test-key']);

    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'model' => 'deepseek-chat',
            'choices' => [
                ['message' => ['content' => 'There is 1 blocked assignment that needs attention.']],
            ],
            'usage' => ['total_tokens' => 42],
        ]),
    ]);

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Pending,
        'updated_at' => now()->subDays(8),
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Find blocked assignments'])
        ->assertOk()
        ->assertJsonPath('message.role', 'assistant')
        ->assertJsonPath('message.content', 'There is 1 blocked assignment that needs attention.')
        ->assertJsonPath('message.tool_name', 'find_blocked_assignments');

    expect(AiConversation::query()->count())->toBe(1)
        ->and(AiMessage::query()->count())->toBe(2);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.deepseek.com/chat/completions'
        && $request['model'] === 'deepseek-chat');
});

test('assistant falls back to local summaries when deepseek is not configured', function () {
    config(['services.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Document,
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Check report readiness'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'check_report_readiness')
        ->assertJsonFragment(['content' => 'Report readiness: 1 assignments are currently in report-ready statuses. Top activity split: SURVEY: 1.']);

    Http::assertNothingSent();
});
