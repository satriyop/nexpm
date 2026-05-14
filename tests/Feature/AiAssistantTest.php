<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentSurveyData;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('only super admins can ask the assistant', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Find blocked assignments'])
        ->assertForbidden();
});

test('super admins can ask the assistant and messages are stored', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    Http::fake([
        'api.deepseek.com/*' => Http::sequence()
            ->push([
                'model' => 'deepseek-chat',
                'choices' => [[
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_123',
                            'function' => [
                                'name' => 'find_blocked_assignments',
                                'arguments' => '{}',
                            ],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 0],
            ])
            ->push([
                'model' => 'deepseek-chat',
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => 'There is 1 blocked assignment that needs attention.'],
                ]],
                'usage' => ['prompt_tokens' => 150, 'completion_tokens' => 42],
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

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.deepseek.com')
        && $request['model'] === 'deepseek-chat');
});

test('assistant falls back to local summaries when deepseek is not configured', function () {
    config(['ai.providers.deepseek.key' => null]);
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
    config(['ai.providers.deepseek.key' => null]);
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
    config(['ai.providers.deepseek.key' => null]);
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
    config(['ai.providers.deepseek.key' => null]);
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
    config(['ai.providers.deepseek.key' => null]);
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
    config(['ai.providers.deepseek.key' => null]);
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

test('assistant returns project health briefing with risks reports and workflow gaps', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Construction,
        'status' => AssignmentStatus::Pending,
        'updated_at' => now()->subDays(8),
    ]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Document,
    ]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Briefing proyek hari ini'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'project_health_briefing')
        ->assertJsonPath('message.tool_payload.project_risks.total_risky_assignments', 1)
        ->assertJsonPath('message.tool_payload.report_readiness.ready_assignment_count', 1);

    expect($response->json('message.tool_payload.workflow_gaps.total_gaps'))->toBeGreaterThanOrEqual(1);

    Http::assertNothingSent();
});

test('assistant scopes project context summaries to the selected project', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $site = Site::factory()->create(['project_id' => $project->id]);
    $otherSite = Site::factory()->create(['project_id' => $otherProject->id]);

    Assignment::factory()->create([
        'site_id' => $site->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Revision,
        'updated_at' => now()->subDays(8),
    ]);
    Assignment::factory()->create([
        'site_id' => $otherSite->id,
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Revision,
        'updated_at' => now()->subDays(8),
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), [
            'message' => 'Project ini apa masalahnya?',
            'context' => ['type' => 'project', 'id' => $project->id, 'project_id' => $project->id],
        ])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'contextual_page_summary')
        ->assertJsonPath('message.tool_payload.project_risks.total_risky_assignments', 1);

    Http::assertNothingSent();
});

test('assistant summarizes assignment context with workflow gaps and next action', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $assignment = Assignment::factory()->construction()->create(['status' => AssignmentStatus::Pending]);
    AssignmentConstructionData::factory()->create([
        'assignment_id' => $assignment->id,
        'cons_wo_number' => null,
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), [
            'message' => 'Assignment ini apa masalahnya?',
            'context' => ['type' => 'assignment', 'id' => $assignment->id, 'assignment_id' => $assignment->id],
        ])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'contextual_page_summary')
        ->assertJsonPath('message.tool_payload.assignment.id', $assignment->id)
        ->assertJsonPath('message.tool_payload.gaps.0.type', 'construction_missing_wo');

    Http::assertNothingSent();
});

test('assistant detects workflow gaps across core workflows', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $survey = Assignment::factory()->survey()->create(['status' => AssignmentStatus::Pending]);
    $surveyData = AssignmentSurveyData::factory()->complete()->make(['assignment_id' => $survey->id]);
    AssignmentSurveyData::withoutEvents(fn () => $surveyData->save());
    $survey->status = AssignmentStatus::Pending;
    $survey->saveQuietly();

    $construction = Assignment::factory()->construction()->create(['status' => AssignmentStatus::Pending]);
    AssignmentConstructionData::factory()->create(['assignment_id' => $construction->id, 'cons_wo_number' => null]);

    $bast = Assignment::factory()->bast()->create(['status' => AssignmentStatus::Pending]);
    AssignmentBastData::factory()->create(['assignment_id' => $bast->id]);

    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Verified,
    ]);

    $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Cek gap workflow'])
        ->assertOk()
        ->assertJsonPath('message.tool_name', 'detect_workflow_gaps')
        ->assertJsonPath('message.tool_payload.gap_type_counts.survey_complete_status_mismatch', 1)
        ->assertJsonPath('message.tool_payload.gap_type_counts.construction_missing_wo', 1)
        ->assertJsonPath('message.tool_payload.gap_type_counts.bast_missing_data', 1)
        ->assertJsonPath('message.tool_payload.gap_type_counts.verified_not_reported', 1);

    Http::assertNothingSent();
});
