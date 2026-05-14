<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DetectWorkflowGapsTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'detect_workflow_gaps';
    }

    public function description(): string
    {
        return 'Detect workflow inconsistencies and data gaps across assignments — such as survey complete but status not updated, construction locked due to missing WO, BAST with incomplete data, or verified assignments not yet reported. Use when the user asks about gaps, inconsistencies, workflow issues, or missing data.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $payload = $this->service->detectWorkflowGaps($this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $payload;

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
