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
        ->assertJsonFragment(['content' => 'AI provider is not configured or reachable, so this is a local NexPM summary. Report readiness: 1 assignments are currently in report-ready statuses. Top activity split: SURVEY: 1.']);

    Http::assertNothingSent();
});

test('assistant can route user questions to user summaries', function () {
    config(['services.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    User::factory()->create(['role' => Role::Admin]);
    User::factory()->create(['role' => Role::Subcontractor]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'siapa saja user'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'list_users')
        ->assertJsonPath('message.content', 'AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. Ditemukan 3 user. Pembagian role: admin: 1, subcontractor: 1, super_admin: 1.')
        ->assertJsonPath('message.tool_payload.total_users_returned', 3)
        ->assertJsonPath('message.tool_payload.role_counts.admin', 1)
        ->assertJsonPath('message.tool_payload.role_counts.subcontractor', 1)
        ->assertJsonPath('message.tool_payload.role_counts.super_admin', 1);

    Http::assertNothingSent();
});

test('assistant does not default unrelated questions to blocked assignments', function () {
    config(['services.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'apa yang bisa kamu bantu'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'general_help')
        ->assertJsonPath('message.content', 'AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. Saya bisa membantu melihat risiko proyek, assignment telat, blocker subcon, kesiapan laporan, dan prioritas tindakan PM hari ini.');

    Http::assertNothingSent();
});

test('assistant routes project manager risk questions to project risk analytics', function () {
    config(['services.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Revision,
        'updated_at' => now()->subDays(10),
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Apa risiko proyek hari ini?'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'summarize_project_risks')
        ->assertJsonPath('message.tool_payload.total_projects_with_risk', 1)
        ->assertJsonPath('message.tool_payload.total_risky_assignments', 1);

    Http::assertNothingSent();
});

test('assistant routes subcontractor blocker questions to subcon blocker analytics', function () {
    config(['services.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Pending,
        'updated_at' => now()->subDays(8),
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Subcon mana yang paling banyak blocker?'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'summarize_subcontractor_blockers')
        ->assertJsonPath('message.tool_payload.total_subcontractors_with_blockers', 1)
        ->assertJsonPath('message.tool_payload.total_blocked_assignments', 1);

    Http::assertNothingSent();
});

test('assistant routes pm priority questions to priority actions', function () {
    config(['services.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Revision,
        'updated_at' => now()->subDays(9),
    ]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Document,
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Apa prioritas tindakan saya hari ini?'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'summarize_priority_actions')
        ->assertJsonPath('message.tool_payload.risk_action_count', 1)
        ->assertJsonPath('message.tool_payload.report_ready_count', 1);

    Http::assertNothingSent();
});
