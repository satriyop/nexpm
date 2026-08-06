<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\McpAuditLog;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /**
     * @return array<string, Type>
     */
    protected function contextSchema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional natural-language filter or entity name.')->nullable(),
            'type' => $schema->string()->description('Optional context type: page, project, site, or assignment.')->nullable(),
            'id' => $schema->integer()->description('Optional context entity ID.')->nullable(),
            'project_id' => $schema->integer()->description('Optional project ID filter.')->nullable(),
            'site_id' => $schema->integer()->description('Optional site ID filter.')->nullable(),
            'assignment_id' => $schema->integer()->description('Optional assignment ID filter.')->nullable(),
            'label' => $schema->string()->description('Optional display label for contextual summaries.')->nullable(),
            'url' => $schema->string()->description('Optional internal page URL for contextual summaries.')->nullable(),
            'site_operations_context' => $schema->object()->description('Optional site operations context for contextual summaries.')->nullable(),
        ];
    }

    protected function executeDomainTool(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user() ?? Auth::user();

        if (! $user && filter_var(config('ai.mcp.local_acting_as', false), FILTER_VALIDATE_BOOLEAN)) {
            $actingUserId = config('ai.mcp.acting_as_user_id');
            $user = $actingUserId ? User::query()->find($actingUserId) : null;
        }

        if (! $user || (! $user->isAdmin() && ! $user->isGlobalAdmin())) {
            return Response::error('Unauthorized. Please authenticate.');
        }

        $requestId = (string) Str::uuid();
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
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                )
            );
        } catch (Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
            Log::error('NexPM MCP tool failed.', [
                'request_id' => $requestId,
                'tool' => $this->domainToolName(),
                'exception' => $e,
            ]);

            return Response::error('Tool execution failed. Request ID: '.$requestId);
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
            } catch (Throwable $auditException) {
                Log::error('NexPM MCP audit logging failed.', [
                    'tool' => $this->domainToolName(),
                    'exception' => $auditException,
                ]);
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
        $allowed = ['query', 'type', 'id', 'project_id', 'site_id', 'assignment_id'];
        $summary = array_intersect_key($request->all(), array_flip($allowed));

        return array_map(
            fn ($value): int|string|null => is_string($value) && mb_strlen($value) > 100
                ? mb_substr($value, 0, 97).'...'
                : (is_scalar($value) || $value === null ? $value : null),
            $summary
        );
    }
}
