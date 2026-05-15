<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CheckReportReadinessTool;
use App\Ai\Tools\ContextualPageSummaryTool;
use App\Ai\Tools\DetectWorkflowGapsTool;
use App\Ai\Tools\FindBlockedAssignmentsTool;
use App\Ai\Tools\GeneralHelpTool;
use App\Ai\Tools\ListUsersTool;
use App\Ai\Tools\ProjectHealthBriefingTool;
use App\Ai\Tools\ResolveEntityContextTool;
use App\Ai\Tools\SummarizeDashboardTool;
use App\Ai\Tools\SummarizePriorityActionsTool;
use App\Ai\Tools\SummarizeProjectRisksTool;
use App\Ai\Tools\SummarizeSubcontractorBlockersTool;
use App\Ai\Tools\ToolResultBag;
use App\Ai\Tools\WorkflowKnowledgeTool;
use App\Models\AiMessage;
use App\Services\Ai\AiAssistantService;
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
#[Temperature(0.2)]
#[MaxTokens(1024)]
#[MaxSteps(3)]
class NexpmAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    private readonly ToolResultBag $bag;

    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context = [],
        private readonly ?int $conversationId = null,
    ) {
        $this->bag = new ToolResultBag;
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
You are NexPM's read-only project management assistant for super admins.
Use only the supplied application data from tools. Do not claim that you changed records, sent messages, generated reports, or updated workflow state.
Answer concisely with concrete project risks, blockers, counts, and recommended next actions. If the data is insufficient, say what is missing.
For workflow/status/process questions, use the workflow knowledge tool before saying data is unavailable.
For project/site/subcontractor names or codes, use entity lookup before saying data is unavailable.
Reply in the same language as the user's question.
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new FindBlockedAssignmentsTool($this->service, $this->context, $this->bag),
            new SummarizeDashboardTool($this->service, $this->context, $this->bag),
            new CheckReportReadinessTool($this->service, $this->context, $this->bag),
            new SummarizeProjectRisksTool($this->service, $this->context, $this->bag),
            new SummarizeSubcontractorBlockersTool($this->service, $this->context, $this->bag),
            new SummarizePriorityActionsTool($this->service, $this->context, $this->bag),
            new ProjectHealthBriefingTool($this->service, $this->context, $this->bag),
            new WorkflowKnowledgeTool($this->service, $this->context, $this->bag),
            new ResolveEntityContextTool($this->service, $this->context, $this->bag),
            new ContextualPageSummaryTool($this->service, $this->context, $this->bag),
            new DetectWorkflowGapsTool($this->service, $this->context, $this->bag),
            new ListUsersTool($this->service, $this->context, $this->bag),
            new GeneralHelpTool($this->service, $this->context, $this->bag),
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
