<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SummarizeAssignmentOperationsTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'summarize_assignment_operations';
    }

    public function description(): string
    {
        return 'Summarize assignment operations with curated read-only analytics. Use for survey recaps by main contractor/project/subcon/machine type, outstanding assignments by subcontractor company or subcontractor user, and assignment breakdowns by project, activity, status, machine type, or subcon.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()
                ->description('survey_recap, outstanding, or assignment_recap.')
                ->nullable(),
            'main_contractor_name' => $schema->string()
                ->description('Optional fuzzy main contractor name, e.g. "sigmatec" or "vahana".')
                ->nullable(),
            'project_name' => $schema->string()
                ->description('Optional fuzzy project name.')
                ->nullable(),
            'subcontractor_name' => $schema->string()
                ->description('Optional fuzzy subcontractor company name.')
                ->nullable(),
            'subcontractor_user_name' => $schema->string()
                ->description('Optional fuzzy subcontractor user/person name; resolves through users.subcontractor_id.')
                ->nullable(),
            'machine_type_name' => $schema->string()
                ->description('Optional fuzzy machine type name.')
                ->nullable(),
            'activity_type' => $schema->string()
                ->description('Optional activity type: SURVEY, PLN_CONNECTION, CONSTRUCTION, or BAST.')
                ->nullable(),
            'status' => $schema->string()
                ->description('Optional assignment status, e.g. PENDING, DOCUMENT, VERIFIED, REPORTED, DROP.')
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $filters = [
            'intent' => $request['intent'] ?? 'assignment_recap',
            'main_contractor_name' => $request['main_contractor_name'] ?? null,
            'project_name' => $request['project_name'] ?? null,
            'subcontractor_name' => $request['subcontractor_name'] ?? null,
            'subcontractor_user_name' => $request['subcontractor_user_name'] ?? null,
            'machine_type_name' => $request['machine_type_name'] ?? null,
            'activity_type' => $request['activity_type'] ?? null,
            'status' => $request['status'] ?? null,
        ];

        $payload = $this->service->summarizeAssignmentOperations($filters, $this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $this->service->decorateToolPayload($this->name(), $payload, $this->context);

        return (string) json_encode($this->bag->toolPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
