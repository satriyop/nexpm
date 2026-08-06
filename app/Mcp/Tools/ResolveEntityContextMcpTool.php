<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ExecutesNexpmAiTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Entity lookup: project, site, subcontractor, main contractor, or machine type by name.')]
#[IsReadOnly]
class ResolveEntityContextMcpTool extends Tool
{
    use ExecutesNexpmAiTool;

    protected function domainToolName(): string
    {
        return 'resolve_entity_context';
    }

    public function handle(Request $request): Response
    {
        return $this->executeDomainTool($request);
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return $this->contextSchema($schema);
    }
}
