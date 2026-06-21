<?php

namespace App\Ai\Tools;

use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GenerateSubcontractorReminderTool implements Tool
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly AiAssistantService $service,
        private readonly array $context,
        private readonly ToolResultBag $bag,
    ) {}

    public function name(): string
    {
        return 'generate_subcontractor_reminder';
    }

    public function description(): string
    {
        return 'Fetch all outstanding (non-completed) assignments for a specific subcontractor so you can compose a WhatsApp-style reminder message. Use when the user asks "buatkan reminder untuk subkon X", "apa outstanding subkon Y", or "tunggakan subkon Z".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subcontractor_name' => $schema->string()
                ->description('Fuzzy subcontractor name to look up, e.g. "asep" or "ade ahyadi".'),
        ];
    }

    public function handle(Request $request): string
    {
        $name = (string) ($request['subcontractor_name'] ?? '');
        $payload = $this->service->generateSubcontractorReminder($name, $this->context);
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $this->service->decorateToolPayload($this->name(), $payload, $this->context);

        return (string) json_encode($this->bag->toolPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
