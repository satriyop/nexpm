<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CheckReportReadinessTool;
use App\Ai\Tools\ContextualPageSummaryTool;
use App\Ai\Tools\DetectWorkflowGapsTool;
use App\Ai\Tools\FindBlockedAssignmentsTool;
use App\Ai\Tools\GeneralHelpTool;
use App\Ai\Tools\GenerateSubcontractorReminderTool;
use App\Ai\Tools\ListUsersTool;
use App\Ai\Tools\ProjectHealthBriefingTool;
use App\Ai\Tools\QueryDatabaseTool;
use App\Ai\Tools\QueryEntityStatsTool;
use App\Ai\Tools\ResolveEntityContextTool;
use App\Ai\Tools\SummarizeDashboardTool;
use App\Ai\Tools\SummarizePriorityActionsTool;
use App\Ai\Tools\SummarizeProjectRisksTool;
use App\Ai\Tools\SummarizeSubcontractorBlockersTool;
use App\Ai\Tools\ToolResultBag;
use App\Ai\Tools\WorkflowKnowledgeTool;
use App\Models\AiMessage;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\DbSchemaService;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-chat')]
#[Temperature(0.1)]
#[MaxTokens(2048)]
#[MaxSteps(5)]
class NexpmFullModeAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    private readonly ToolResultBag $bag;

    /** @param array{mode: string, max_rows: int} $preferences */
    public function __construct(
        private readonly DbSchemaService $schemaService,
        private readonly AiAssistantService $assistantService,
        private readonly array $context,
        private readonly array $preferences,
        private readonly int $mainContractorId,
        private readonly ?int $conversationId = null,
    ) {
        $this->bag = new ToolResultBag;
    }

    public function instructions(): string
    {
        $schema = $this->schemaService->buildSchemaDescription($this->mainContractorId);
        $maxRows = (int) ($this->preferences['max_rows'] ?? 500);

        return <<<PROMPT
You are NexPM's full-access database analyst for super admins.
You can query the live database to answer any question about projects, sites, assignments, subcontractors, and financials.

RULES:
1. Only write SELECT queries — never INSERT, UPDATE, DELETE, or DDL.
2. Always scope every query to main_contractor_id = {$this->mainContractorId} using the correct column for each table (see SCOPING RULES in the schema below).
3. Always add LIMIT {$maxRows} or less — never return unbounded results.
4. Answer in the same language as the user's question (Indonesian or English).
5. After running a query, explain the results in natural language. Show key numbers prominently.
6. If a query returns no results, say so clearly and suggest why.
7. When asked about "subkontraktor", always query the `subcontractors` table — NOT the `users` table. The `users` table is for individual people accounts, not companies.
8. Prefer the curated NexPM tools for workflow, project health, blockers, report readiness, priority actions, and entity lookup. Use raw SQL only when the curated tools cannot answer the user's specific question.
9. For workflow/status/process questions, use `workflow_knowledge` before saying you do not have data.
10. If a project/site/subcontractor name is ambiguous, ask a clarifying question and include the likely matches instead of inventing an answer.

DATABASE SCHEMA AND DOMAIN MODEL:
{$schema}
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new QueryEntityStatsTool($this->assistantService, $this->context, $this->bag),
            new GenerateSubcontractorReminderTool($this->assistantService, $this->context, $this->bag),
            new ProjectHealthBriefingTool($this->assistantService, $this->context, $this->bag),
            new WorkflowKnowledgeTool($this->assistantService, $this->context, $this->bag),
            new ResolveEntityContextTool($this->assistantService, $this->context, $this->bag),
            new FindBlockedAssignmentsTool($this->assistantService, $this->context, $this->bag),
            new SummarizeDashboardTool($this->assistantService, $this->context, $this->bag),
            new CheckReportReadinessTool($this->assistantService, $this->context, $this->bag),
            new SummarizeProjectRisksTool($this->assistantService, $this->context, $this->bag),
            new SummarizeSubcontractorBlockersTool($this->assistantService, $this->context, $this->bag),
            new SummarizePriorityActionsTool($this->assistantService, $this->context, $this->bag),
            new ContextualPageSummaryTool($this->assistantService, $this->context, $this->bag),
            new DetectWorkflowGapsTool($this->assistantService, $this->context, $this->bag),
            new ListUsersTool($this->assistantService, $this->context, $this->bag),
            new GeneralHelpTool($this->assistantService, $this->context, $this->bag),
            new QueryDatabaseTool($this->preferences, $this->bag),
        ];
    }

    public function messages(): iterable
    {
        if ($this->conversationId === null) {
            return [];
        }

        return AiMessage::query()
            ->where('ai_conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiMessage $m): Message => new Message($m->role, $m->content))
            ->all();
    }

    public function getResultBag(): ToolResultBag
    {
        return $this->bag;
    }
}
