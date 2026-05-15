<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WorkflowKnowledgeTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'workflow_knowledge';
    }

    public function description(): string
    {
        return 'Explain NexPM workflow rules, status meanings, required fields, report readiness, and who should act. Use this before saying you do not have data for workflow/process questions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $payload = $this->service->workflowKnowledge();
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $this->service->decorateToolPayload($this->name(), $payload, $this->context);

        return (string) json_encode($this->bag->toolPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
