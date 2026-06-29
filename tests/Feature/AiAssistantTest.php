<?php

use App\Ai\Agents\NexpmAssistantAgent;
use App\Ai\Agents\NexpmFullModeAgent;
use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentSurveyData;
use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\DbSchemaService;
use Illuminate\Support\Facades\Http;

/**
 * Parse SSE event stream into a keyed array of event data.
 *
 * @return array<string, array<string, mixed>>
 */
function parseSseEvents(string $content): array
{
    $events = [];

    foreach (explode("\n\n", trim($content)) as $block) {
        $block = trim($block);

        if ($block === '') {
            continue;
        }

        $eventName = '';
        $eventData = '';

        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, 'event: ')) {
                $eventName = substr($line, 7);
            } elseif (str_starts_with($line, 'data: ')) {
                $eventData = substr($line, 6);
            }
        }

        if ($eventName !== '' && $eventData !== '') {
            $events[$eventName] = json_decode($eventData, true);
        }
    }

    return $events;
}

test('only super admins can ask the assistant', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Find blocked assignments'])
        ->assertForbidden();
});

test('super admins can ask the assistant and messages are stored', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);

    NexpmAssistantAgent::fake(['There is 1 blocked assignment that needs attention.']);

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Pending,
        'updated_at' => now()->subDays(8),
    ]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Find blocked assignments'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events)->toHaveKey('done')
        ->and($events['done']['conversation_id'])->toBeInt()
        ->and($events['done']['message_id'])->toBeInt();

    expect(AiConversation::query()->count())->toBe(1)
        ->and(AiMessage::query()->count())->toBe(2);

    NexpmAssistantAgent::assertPrompted('Find blocked assignments');
});

test('assistant falls back to local summaries when deepseek is not configured', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    Assignment::factory()->create([
        'activity_type' => ActivityType::Survey,
        'status' => AssignmentStatus::Document,
    ]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Check report readiness'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('check_report_readiness')
        ->and($events['text']['delta'])->toBe('AI provider is not configured or reachable, so this is a local NexPM summary. Report readiness: 1 assignments are currently in report-ready statuses. Top activity split: SURVEY: 1.')
        ->and($events['done'])->toHaveKey('conversation_id');

    Http::assertNothingSent();
});

test('assistant can route user questions to user summaries', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    User::factory()->create(['role' => Role::Admin]);
    User::factory()->create(['role' => Role::Subcontractor]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'siapa saja user'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('list_users')
        ->and($events['text']['delta'])->toBe('AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. Ditemukan 3 user. Pembagian role: admin: 1, subcontractor: 1, super_admin: 1.')
        ->and($events['tool_data']['tool_payload']['total_users_returned'])->toBe(3)
        ->and($events['tool_data']['tool_payload']['role_counts']['admin'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['role_counts']['subcontractor'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['role_counts']['super_admin'])->toBe(1);

    Http::assertNothingSent();
});

test('assistant does not default unrelated questions to blocked assignments', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'apa yang bisa kamu bantu'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('general_help')
        ->and($events['text']['delta'])->toBe('AI provider belum dikonfigurasi atau tidak dapat dihubungi, jadi ini ringkasan lokal NexPM. Saya bisa membantu melihat risiko proyek, assignment telat, blocker subcon, kesiapan laporan, dan prioritas tindakan PM hari ini.');

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

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Apa risiko proyek hari ini?'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('summarize_project_risks')
        ->and($events['tool_data']['tool_payload']['total_projects_with_risk'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['total_risky_assignments'])->toBe(1);

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

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Subcon mana yang paling banyak blocker?'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('summarize_subcontractor_blockers')
        ->and($events['tool_data']['tool_payload']['total_subcontractors_with_blockers'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['total_blocked_assignments'])->toBe(1);

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

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Apa prioritas tindakan saya hari ini?'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('summarize_priority_actions')
        ->and($events['tool_data']['tool_payload']['risk_action_count'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['report_ready_count'])->toBe(1);

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
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('project_health_briefing')
        ->and($events['tool_data']['tool_payload']['project_risks']['total_risky_assignments'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['report_readiness']['ready_assignment_count'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['workflow_gaps']['total_gaps'])->toBeGreaterThanOrEqual(1);

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

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), [
            'message' => 'Project ini apa masalahnya?',
            'context' => ['type' => 'project', 'id' => $project->id, 'project_id' => $project->id],
        ])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('contextual_page_summary')
        ->and($events['tool_data']['tool_payload']['project_risks']['total_risky_assignments'])->toBe(1);

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

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), [
            'message' => 'Assignment ini apa masalahnya?',
            'context' => ['type' => 'assignment', 'id' => $assignment->id, 'assignment_id' => $assignment->id],
        ])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('contextual_page_summary')
        ->and($events['tool_data']['tool_payload']['assignment']['id'])->toBe($assignment->id)
        ->and($events['tool_data']['tool_payload']['gaps'][0]['type'])->toBe('construction_missing_wo');

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

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Cek gap workflow'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('detect_workflow_gaps')
        ->and($events['tool_data']['tool_payload']['gap_type_counts']['survey_complete_status_mismatch'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['gap_type_counts']['construction_missing_wo'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['gap_type_counts']['bast_missing_data'])->toBe(1)
        ->and($events['tool_data']['tool_payload']['gap_type_counts']['verified_not_reported'])->toBe(1);

    Http::assertNothingSent();
});

test('assistant answers workflow knowledge questions without database rows', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Apa arti status DOCUMENT?'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('workflow_knowledge')
        ->and($events['text']['delta'])->toContain('DOCUMENT')
        ->and($events['tool_data']['tool_payload']['sources'])->toContain('NexPM workflow rules')
        ->and($events['tool_data']['tool_payload']['follow_up_suggestions'])->toContain('Cek gap workflow');

    Http::assertNothingSent();
});

test('full mode agent includes curated pm tools before raw database querying', function () {
    $agent = new NexpmFullModeAgent(
        app(DbSchemaService::class),
        app(AiAssistantService::class),
        [],
        ['mode' => 'full', 'max_rows' => 100],
        1,
    );

    $toolNames = collect($agent->tools())->map(fn ($tool): string => $tool->name())->all();

    expect($toolNames)->toContain('project_health_briefing')
        ->and($toolNames)->toContain('workflow_knowledge')
        ->and($toolNames)->toContain('resolve_entity_context')
        ->and($toolNames)->toContain('query_database');
});

test('assistant can resolve ambiguous natural language project and subcon references', function () {
    $service = app(AiAssistantService::class);

    Project::factory()->create(['name' => 'Alpha Rollout Barat']);
    Project::factory()->create(['name' => 'Alpha Rollout Timur']);
    Subcontractor::factory()->create(['name' => 'Subcon Alpha']);

    $payload = $service->resolveEntityContext('kenapa project Alpha lambat?');

    expect($payload['needs_clarification'])->toBeTrue()
        ->and($payload['match_count'])->toBeGreaterThanOrEqual(2)
        ->and($payload['suggestions'])->not->toBeEmpty();
});

test('assistant counts locations for a named project through local fallback', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $project = Project::factory()->create(['name' => 'Planet Ban Rollout']);
    $otherProject = Project::factory()->create(['name' => 'Other Rollout']);

    Site::factory()->count(2)->create(['project_id' => $project->id]);
    Site::factory()->create(['project_id' => $otherProject->id]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Project Planet Ban, how many locations?'])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('query_entity_stats')
        ->and($events['tool_data']['tool_payload']['count_target'])->toBe('sites')
        ->and($events['tool_data']['tool_payload']['total_count'])->toBe(2);

    Http::assertNothingSent();
});

test('assistant counts pending survey assignments by subcontractor company and user', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $subcontractor = Subcontractor::factory()->create(['name' => 'Acme Fiber']);
    $otherSubcontractor = Subcontractor::factory()->create(['name' => 'Other Fiber']);
    User::factory()->create(['name' => 'Budi Surveyor', 'role' => Role::Subcontractor, 'subcontractor_id' => $subcontractor->id]);

    Assignment::factory()->count(2)->survey()->create([
        'subcontractor_id' => $subcontractor->id,
        'status' => AssignmentStatus::Pending,
    ]);
    Assignment::factory()->survey()->create([
        'subcontractor_id' => $subcontractor->id,
        'status' => AssignmentStatus::Document,
    ]);
    Assignment::factory()->survey()->create([
        'subcontractor_id' => $otherSubcontractor->id,
        'status' => AssignmentStatus::Pending,
    ]);

    $companyResponse = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'How many survey assignment pending by subcon Acme Fiber?'])
        ->assertOk();
    $companyEvents = parseSseEvents($companyResponse->streamedContent());

    expect($companyEvents['tool_data']['tool_name'])->toBe('query_entity_stats')
        ->and($companyEvents['tool_data']['tool_payload']['total_count'])->toBe(2);

    $userResponse = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'How many survey assignment pending by subcon user Budi Surveyor?'])
        ->assertOk();
    $userEvents = parseSseEvents($userResponse->streamedContent());

    expect($userEvents['tool_data']['tool_name'])->toBe('query_entity_stats')
        ->and($userEvents['tool_data']['tool_payload']['total_count'])->toBe(2)
        ->and($userEvents['tool_data']['tool_payload']['matched_entities']['subcontractor_user'][0]['subcontractor_name'])->toBe('Acme Fiber');

    Http::assertNothingSent();
});

test('assistant counts pln assignments by main contractor', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $sigmatec = MainContractor::factory()->create(['name' => 'Sigmatec']);
    $otherMainContractor = MainContractor::factory()->create(['name' => 'Other Maincon']);
    $project = Project::factory()->create(['main_contractor_id' => $sigmatec->id]);
    $otherProject = Project::factory()->create(['main_contractor_id' => $otherMainContractor->id]);
    $sites = Site::factory()->count(2)->create(['project_id' => $project->id]);
    $otherSite = Site::factory()->create(['project_id' => $otherProject->id]);

    Assignment::factory()->plnConnection()->create(['site_id' => $sites[0]->id]);
    Assignment::factory()->plnConnection()->create(['site_id' => $sites[1]->id]);
    Assignment::factory()->plnConnection()->create(['site_id' => $otherSite->id]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'How many PLN assignment by main contractor Sigmatec?'])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('query_entity_stats')
        ->and($events['tool_data']['tool_payload']['total_count'])->toBe(2)
        ->and($events['tool_data']['tool_payload']['filters']['activity_type'])->toBe(ActivityType::PlnConnection->value)
        ->and($events['tool_data']['tool_payload']['filters']['main_contractor_name'])->toBe('Sigmatec');

    Http::assertNothingSent();
});

test('assistant deterministically routes operational counts even in full mode', function () {
    config(['ai.providers.deepseek.key' => 'test-key']);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mainContractor = MainContractor::factory()->create(['name' => 'Sigma Tec']);
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);
    $site = Site::factory()->create(['project_id' => $project->id]);

    Assignment::factory()->plnConnection()->create(['site_id' => $site->id]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), [
            'message' => 'ada berapa assignment PLN pada main contractor sigmatec?',
            'mode' => 'full',
        ])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());

    expect($events['tool_data']['tool_name'])->toBe('query_entity_stats')
        ->and($events['tool_data']['tool_payload']['total_count'])->toBe(1)
        ->and($events['text']['delta'])->toStartWith('Statistik entitas:')
        ->and($events['text']['delta'])->not->toContain('AI provider');

    Http::assertNothingSent();
});

test('assistant returns survey recap for main contractor and outstanding by subcontractor company or user', function () {
    config(['ai.providers.deepseek.key' => null]);
    Http::fake();

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $vahana = MainContractor::factory()->create(['name' => 'Vahana']);
    $project = Project::factory()->create(['name' => 'Vahana Survey Rollout', 'main_contractor_id' => $vahana->id]);
    $sites = Site::factory()->count(3)->create(['project_id' => $project->id]);
    $subcontractor = Subcontractor::factory()->create(['name' => 'Nusa Subcon']);
    User::factory()->create(['name' => 'Citra Subcon', 'role' => Role::Subcontractor, 'subcontractor_id' => $subcontractor->id]);

    Assignment::factory()->survey()->create(['site_id' => $sites[0]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Pending]);
    Assignment::factory()->survey()->create(['site_id' => $sites[1]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Verified]);
    Assignment::factory()->plnConnection()->create(['site_id' => $sites[2]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Pending]);

    $recapResponse = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Make a summary recap for assignment survey for main con Vahana.'])
        ->assertOk();
    $recapEvents = parseSseEvents($recapResponse->streamedContent());

    expect($recapEvents['tool_data']['tool_name'])->toBe('summarize_assignment_operations')
        ->and($recapEvents['tool_data']['tool_payload']['intent'])->toBe('survey_recap')
        ->and($recapEvents['tool_data']['tool_payload']['total_count'])->toBe(2)
        ->and($recapEvents['tool_data']['tool_payload']['activity_breakdown'])->toHaveKey(ActivityType::Survey->value);

    $companyResponse = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Outstanding for subcon Nusa Subcon?'])
        ->assertOk();
    $companyEvents = parseSseEvents($companyResponse->streamedContent());

    expect($companyEvents['tool_data']['tool_name'])->toBe('summarize_assignment_operations')
        ->and($companyEvents['tool_data']['tool_payload']['intent'])->toBe('outstanding')
        ->and($companyEvents['tool_data']['tool_payload']['total_count'])->toBe(2);

    $userResponse = $this->actingAs($superAdmin)
        ->postJson(route('admin.ai.messages.store'), ['message' => 'Outstanding for subcon user Citra Subcon?'])
        ->assertOk();
    $userEvents = parseSseEvents($userResponse->streamedContent());

    expect($userEvents['tool_data']['tool_name'])->toBe('summarize_assignment_operations')
        ->and($userEvents['tool_data']['tool_payload']['intent'])->toBe('outstanding')
        ->and($userEvents['tool_data']['tool_payload']['total_count'])->toBe(2)
        ->and($userEvents['tool_data']['tool_payload']['status_breakdown'])->not->toHaveKeys([
            AssignmentStatus::Verified->value,
            AssignmentStatus::Reported->value,
            AssignmentStatus::Drop->value,
        ]);

    Http::assertNothingSent();
});

test('assignment operation service supports combined filters and clarification', function () {
    $service = app(AiAssistantService::class);
    $mainContractor = MainContractor::factory()->create(['name' => 'Sigmatec']);
    $machineType = MachineType::factory()->create(['name' => 'Delta 50kW']);
    $project = Project::factory()->create(['name' => 'Planet Ban', 'main_contractor_id' => $mainContractor->id]);
    $sites = Site::factory()->count(4)->create(['project_id' => $project->id, 'machine_type_id' => $machineType->id]);
    $subcontractor = Subcontractor::factory()->create(['name' => 'Prima Subcon']);
    User::factory()->create(['name' => 'Dewi Operator', 'role' => Role::Subcontractor, 'subcontractor_id' => $subcontractor->id]);

    Assignment::factory()->plnConnection()->create(['site_id' => $sites[0]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Pending]);
    Assignment::factory()->survey()->create(['site_id' => $sites[1]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Verified]);
    Assignment::factory()->survey()->create(['site_id' => $sites[2]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Reported]);
    Assignment::factory()->survey()->create(['site_id' => $sites[3]->id, 'subcontractor_id' => $subcontractor->id, 'status' => AssignmentStatus::Drop]);

    $mainContractorPayload = $service->queryEntityStats([
        'count_target' => 'assignments',
        'main_contractor_name' => 'Sigmatec',
        'activity_type' => 'PLN',
    ], []);

    expect($mainContractorPayload['total_count'])->toBe(1);

    $projectMachinePayload = $service->queryEntityStats([
        'count_target' => 'assignments',
        'project_name' => 'Planet Ban',
        'machine_type_name' => 'Delta',
    ], []);

    expect($projectMachinePayload['total_count'])->toBe(4);

    $userPayload = $service->summarizeAssignmentOperations([
        'intent' => 'outstanding',
        'subcontractor_user_name' => 'Dewi Operator',
    ], []);

    expect($userPayload['total_count'])->toBe(1)
        ->and($userPayload['matched_entities']['subcontractor_user'][0]['subcontractor_name'])->toBe('Prima Subcon');

    Project::factory()->create(['name' => 'Alpha Rollout Barat']);
    Project::factory()->create(['name' => 'Alpha Rollout Timur']);

    $ambiguousPayload = $service->queryEntityStats([
        'count_target' => 'assignments',
        'project_name' => 'Alpha',
    ], []);

    expect($ambiguousPayload['needs_clarification'])->toBeTrue()
        ->and($ambiguousPayload['clarification_suggestions'])->not->toBeEmpty();
});

test('assignment operation filters tolerate spacing and punctuation differences in names', function () {
    $service = app(AiAssistantService::class);
    $mainContractor = MainContractor::factory()->create(['name' => 'Sigma Tec']);
    $subcontractor = Subcontractor::factory()->create(['name' => 'PT Ade-Ahyadi']);
    $project = Project::factory()->create(['main_contractor_id' => $mainContractor->id]);
    $site = Site::factory()->create(['project_id' => $project->id]);

    Assignment::factory()->survey()->create([
        'site_id' => $site->id,
        'subcontractor_id' => $subcontractor->id,
        'status' => AssignmentStatus::Pending,
    ]);

    $mainContractorPayload = $service->queryEntityStats([
        'count_target' => 'assignments',
        'main_contractor_name' => 'sigmatec',
        'activity_type' => 'SURVEY',
    ], []);

    expect($mainContractorPayload['total_count'])->toBe(1)
        ->and($mainContractorPayload['filters']['main_contractor_name'])->toBe('Sigma Tec');

    $subcontractorPayload = $service->queryEntityStats([
        'count_target' => 'assignments',
        'subcontractor_name' => 'ade ahyadi',
        'activity_type' => 'SURVEY',
        'status' => 'PENDING',
    ], []);

    expect($subcontractorPayload['total_count'])->toBe(1)
        ->and($subcontractorPayload['filters']['subcontractor_name'])->toBe('PT Ade-Ahyadi');
});
