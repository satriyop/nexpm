<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryEntityStatsTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'query_entity_stats';
    }

    public function description(): string
    {
        return 'Count sites (lokasi) or assignments for a named project, subcontractor, or filtered by activity type and status. Use when the user asks "berapa lokasi project X", "berapa assignment survey pending untuk subkon Y", or "ada berapa assignment PLN".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'count_target' => $schema->string()
                ->description('What to count: "sites" to count locations/sites, "assignments" to count assignments. Default: "assignments".')
                ->nullable(),
            'project_name' => $schema->string()
                ->description('Optional: fuzzy project name to filter results, e.g. "planet ban".')
                ->nullable(),
            'subcontractor_name' => $schema->string()
                ->description('Optional: fuzzy subcontractor name to filter assignments, e.g. "ade ahyadi".')
                ->nullable(),
            'activity_type' => $schema->string()
                ->description('Optional: filter by activity type — SURVEY, PLN_CONNECTION, CONSTRUCTION, or BAST.')
                ->nullable(),
            'status' => $schema->string()
                ->description('Optional: filter by assignment status — PENDING, REVISION, DOCUMENT, VERIFIED, REPORTED, DROP, etc.')
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $filters = [
            'count_target' => $request['count_target'] ?? 'assignments',
            'project_name' => $request['project_name'] ?? null,
            'subcontractor_name' => $request['subcontractor_name'] ?? null,
            'activity_type' => $request['activity_type'] ?? null,
            'status' => $request['status'] ?? null,
        ];

        $payload = $this->service->queryEntityStats($filters, $this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $this->service->decorateToolPayload($this->name(), $payload, $this->context);

        return (string) json_encode($this->bag->toolPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
