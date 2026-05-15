<?php

namespace App\Ai\Agents;

use App\Ai\Tools\QueryDatabaseTool;
use App\Ai\Tools\ToolResultBag;
use App\Models\AiMessage;
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
2. Always scope queries to main_contractor_id = {$this->mainContractorId} (JOIN through projects table when querying sites/assignments).
3. Always add LIMIT {$maxRows} or less — never return unbounded results.
4. Answer in the same language as the user's question (Indonesian or English).
5. After running a query, explain the results in natural language. Show key numbers prominently.
6. If a query returns no results, say so clearly and suggest why.

DATABASE SCHEMA:
{$schema}
PROMPT;
    }

    public function tools(): iterable
    {
        return [
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
