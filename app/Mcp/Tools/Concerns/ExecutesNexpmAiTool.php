<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\McpAuditLog;
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

        $startMs = (int) (microtime(true) * 1000);
        $errorMessage = null;
        $status = 'success';

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
            $status = 'error';
            $errorMessage = $e->getMessage();

            return Response::error('Tool execution failed: '.$errorMessage);
        } finally {
            $elapsed = max(0, (int) (microtime(true) * 1000) - $startMs);

            try {
                McpAuditLog::create([
                    'tool_name' => $this->domainToolName(),
                    'token_prefix' => $this->tokenPrefix($request),
                    'acting_user_id' => $user?->id,
                    'status' => $status,
                    'latency_ms' => $elapsed,
                    'request_summary' => $this->summarizeRequest($request),
                    'error_message' => $errorMessage,
                ]);
            } catch (Throwable) {
                // Swallow audit-log failures; never block the tool response.
            }
        }
    }

    protected function tokenPrefix(Request $request): ?string
    {
        $token = $request->bearerToken();

        return $token ? substr(hash('sha256', $token), 0, 12) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function summarizeRequest(Request $request): ?array
    {
        $summary = $request->all();
        unset($summary['_meta']);
        $summary = array_filter($summary, fn ($v) => ! is_resource($v));

        return array_map(
            fn ($v) => is_string($v) && mb_strlen($v) > 100 ? mb_substr($v, 0, 97).'...' : $v,
            $summary
        );
    }
}
