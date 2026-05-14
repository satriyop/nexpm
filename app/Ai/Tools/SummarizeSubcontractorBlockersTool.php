<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SummarizeSubcontractorBlockersTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'summarize_subcontractor_blockers';
    }

    public function description(): string
    {
        return 'Analyze which subcontractors have the most blocked assignments. Use when the user asks about subcontractor performance, which subcon has the most blockers, or subcontractor-level issues.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $payload = $this->service->summarizeSubcontractorBlockers($this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $payload;

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
