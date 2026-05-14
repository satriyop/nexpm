<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SummarizePriorityActionsTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'summarize_priority_actions';
    }

    public function description(): string
    {
        return 'Get a prioritized action list for the project manager today — combining highest-risk blocker assignments and report-ready items. Use when the user asks what to focus on, priority actions, what to do today, or PM task priorities.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $payload = $this->service->summarizePriorityActions($this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $payload;

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
