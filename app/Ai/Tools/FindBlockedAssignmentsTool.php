<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class FindBlockedAssignmentsTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'find_blocked_assignments';
    }

    public function description(): string
    {
        return 'Find assignments that are blocked, late, stuck, or at risk — including revision-pending, long-pending, construction assignments missing WO numbers, and surveys missing power data. Use when the user asks about stuck, slow, blocked, or problematic assignments.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $payload = $this->service->findBlockedAssignments($this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $payload;

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
