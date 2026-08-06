<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Ai\AiAssistantService;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Throwable;

trait ExecutesNexpmAiTool
{
    abstract protected function domainToolName(): string;

    /**
     * Build the context array passed to AiAssistantService::runTool().
     *
     * @return array<string, mixed>
     */
    protected function buildContext(Request $request): array
    {
        $context = $request->all();
        unset($context['_meta']);

        return $context;
    }

    protected function executeDomainTool(Request $request): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user() ?? Auth::user();

        if (! $user) {
            return Response::error('Unauthorized. Please authenticate.');
        }

        try {
            $context = $this->buildContext($request);

            /** @var array<string, mixed> $result */
            $result = app(AiAssistantService::class)->runTool(
                $this->domainToolName(),
                $context,
            );

            return Response::text(
                (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            return Response::error('Tool execution failed: '.$e->getMessage());
        }
    }
}
