<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ResolveEntityContextTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'resolve_entity_context';
    }

    public function description(): string
    {
        return 'Find matching projects, sites, and subcontractors from a natural-language user query. Use when the user mentions a project/site/subcon by name or code, and before answering that no data exists.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The original user query or the entity phrase to resolve, for example "project Alpha lambat" or "site JKT-001".'),
        ];
    }

    public function handle(Request $request): string
    {
        $payload = $this->service->resolveEntityContext((string) ($request['query'] ?? ''), $this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $this->service->decorateToolPayload($this->name(), $payload, $this->context);

        return (string) json_encode($this->bag->toolPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
