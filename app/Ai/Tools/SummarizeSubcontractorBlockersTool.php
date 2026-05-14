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
        return 'Analyze subcontractor performance and blockers. Use when the user asks about subcontractor blockers, performance, or when they ask about a specific subcontractor by name — including how many sites they handle, what sites they manage, or their full assignment list.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subcontractor_name' => $schema->string()
                ->description('Optional: filter results to a specific subcontractor by name to see all their sites and full assignment list, not just blocked ones.')
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $subcontractorName = isset($request['subcontractor_name']) ? (string) $request['subcontractor_name'] : null;
        $payload = $this->service->summarizeSubcontractorBlockers($this->context, $subcontractorName);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $payload;

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
