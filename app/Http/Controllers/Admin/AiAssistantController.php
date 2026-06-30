<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\NexpmAssistantAgent;
use App\Ai\Agents\NexpmFullModeAgent;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiPromptLog;
use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\DbSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolResult;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiAssistantController extends Controller
{
    public function promptExamples(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $projectNames = Project::query()
            ->withCount('sites')
            ->orderByDesc('sites_count')
            ->orderBy('name')
            ->limit(3)
            ->pluck('name');

        $mainContractorNames = MainContractor::query()
            ->withCount('projects')
            ->orderByDesc('projects_count')
            ->orderBy('name')
            ->limit(3)
            ->pluck('name');

        $subcontractorNames = Subcontractor::query()
            ->withCount('assignments')
            ->orderByDesc('assignments_count')
            ->orderBy('name')
            ->limit(3)
            ->pluck('name');

        $subcontractorUserNames = User::query()
            ->whereNotNull('subcontractor_id')
            ->orderBy('name')
            ->limit(3)
            ->pluck('name');

        $machineTypeNames = MachineType::query()
            ->orderBy('name')
            ->limit(4)
            ->pluck('name');

        return response()->json([
            'groups' => [
                'counts' => collect([
                    $projectNames->first() ? 'Project '.$projectNames->first().' ada berapa lokasi?' : null,
                    $mainContractorNames->first() ? 'Berapa assignment PLN untuk main contractor '.$mainContractorNames->first().'?' : null,
                    $subcontractorNames->first() ? 'Berapa assignment survey pending untuk subkon '.$subcontractorNames->first().'?' : null,
                    $subcontractorUserNames->first() ? 'Berapa assignment outstanding untuk subkon user '.$subcontractorUserNames->first().'?' : null,
                    $machineTypeNames->first() ? 'Machine type '.$machineTypeNames->first().' ada berapa lokasi?' : null,
                    $machineTypeNames->count() >= 2 ? $machineTypeNames->take(2)->implode(' dan ').' ada berapa lokasi?' : null,
                ])->filter()->values()->all(),
                'recap' => collect([
                    $mainContractorNames->first() ? 'Rekap assignment survey untuk main contractor '.$mainContractorNames->first() : null,
                    $subcontractorNames->first() ? 'Rekap outstanding untuk subkon '.$subcontractorNames->first() : null,
                    $projectNames->first() ? 'Summary assignment project '.$projectNames->first() : null,
                ])->filter()->values()->all(),
                'reminder' => collect([
                    $subcontractorNames->first() ? 'Buatkan reminder untuk subkon '.$subcontractorNames->first() : null,
                    $subcontractorUserNames->first() ? 'Reminder '.$subcontractorUserNames->first().' ada outstanding apa saja?' : null,
                    $subcontractorNames->skip(1)->first() ? 'Outstanding untuk subkon '.$subcontractorNames->skip(1)->first().'?' : null,
                ])->filter()->values()->all(),
            ],
            'entities' => [
                'projects' => $projectNames->values()->all(),
                'main_contractors' => $mainContractorNames->values()->all(),
                'subcontractors' => $subcontractorNames->values()->all(),
                'subcontractor_users' => $subcontractorUserNames->values()->all(),
                'machine_types' => $machineTypeNames->values()->all(),
            ],
        ]);
    }

    public function store(Request $request, AiAssistantService $service): StreamedResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'mode' => ['nullable', 'string', 'in:standard,full'],
            'context' => ['nullable', 'array'],
            'context.type' => ['nullable', 'string', 'max:50'],
            'context.id' => ['nullable'],
            'context.label' => ['nullable', 'string', 'max:255'],
            'context.url' => ['nullable', 'string', 'max:255'],
            'context.component' => ['nullable', 'string', 'max:255'],
            'context.project_id' => ['nullable', 'integer'],
            'context.site_id' => ['nullable', 'integer'],
            'context.assignment_id' => ['nullable', 'integer'],
            'clarification_context' => ['nullable', 'array'],
            'clarification_context.prompt' => ['nullable', 'string', 'max:2000'],
            'clarification_context.suggestions' => ['nullable', 'array'],
            'clarification_context.suggestions.*' => ['string', 'max:255'],
        ]);

        $context = $validated['context'] ?? [];
        $mode = $validated['mode'] ?? $request->user()->getAiPreferences()['mode'];
        $originalMessage = $validated['message'];
        $message = $this->effectiveMessageFromClarification($originalMessage, $validated['clarification_context'] ?? []);

        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->find($validated['conversation_id'] ?? null);

        if ($conversation === null) {
            $conversation = AiConversation::query()->create([
                'user_id' => $request->user()->id,
                'title' => Str::limit($originalMessage, 80),
                'context' => $context,
            ]);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $originalMessage,
        ]);

        return response()->stream(
            function () use ($service, $message, $originalMessage, $context, $conversation, $mode, $request): void {
                $this->streamResponse($service, $message, $originalMessage, $context, $conversation, $request->user()->id, $mode, $request->user()->main_contractor_id, $request->user()->getAiPreferences());
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /** @param array{mode: string, max_rows: int} $preferences */
    private function streamResponse(AiAssistantService $service, string $message, string $originalMessage, array $context, AiConversation $conversation, int $userId, string $mode = 'standard', ?int $mainContractorId = null, array $preferences = []): void
    {
        $contextWithQuery = array_merge($context, ['query' => $message]);
        $deterministicToolName = $service->selectTool($message, $contextWithQuery);

        if ($this->shouldBypassModel($deterministicToolName)) {
            $this->streamToolResponse($service, $deterministicToolName, $message, $originalMessage, $contextWithQuery, $conversation, $userId);

            return;
        }

        $apiKey = config('ai.providers.deepseek.key');

        if (empty($apiKey)) {
            $this->streamFallback($service, $message, $originalMessage, $context, $conversation, $userId, new \RuntimeException('DeepSeek API key is not configured.'));

            return;
        }

        if ($mode === 'full' && $mainContractorId !== null) {
            $agent = new NexpmFullModeAgent(app(DbSchemaService::class), $service, $context, $preferences, $mainContractorId, $conversation->id);
        } else {
            $agent = new NexpmAssistantAgent($service, $context, $conversation->id);
        }

        try {
            $stream = $agent->stream($message);
            $content = '';
            $toolName = null;
            $toolPayload = [];

            foreach ($stream as $event) {
                if ($event instanceof ToolResult) {
                    $bag = $agent->getResultBag();
                    $toolName = $bag->toolName;
                    $toolPayload = $service->decorateToolPayload($toolName ?? 'general_help', $bag->toolPayload, $context);
                    $this->emit('tool_data', [
                        'tool_name' => $toolName ?? 'general_help',
                        'tool_payload' => $toolPayload,
                    ]);
                } elseif ($event instanceof TextDelta) {
                    $content .= $event->delta;
                    $this->emit('text', ['delta' => $event->delta]);
                }
            }

            if ($toolName === null) {
                $bag = $agent->getResultBag();
                $toolName = $bag->toolName ?? 'general_help';
                $toolPayload = $bag->toolPayload !== [] ? $bag->toolPayload : $service->runTool($toolName, $context);
                $toolPayload = $service->decorateToolPayload($toolName, $toolPayload, $context);
            }

            $aiMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $content,
                'tool_name' => $toolName,
                'tool_payload' => $toolPayload,
                'usage' => [],
            ]);

            $conversation->touch();
            $this->logPromptOutcome($userId, $conversation, $aiMessage->id, $originalMessage, $message, $context, $toolName, $toolPayload);

            $this->emit('done', [
                'conversation_id' => $conversation->id,
                'message_id' => $aiMessage->id,
            ]);
        } catch (Throwable $exception) {
            // If partial text was already streamed, appending fallback would corrupt the message.
            // Emit an error so the frontend can display it cleanly instead.
            if ($content !== '') {
                $this->emit('error', ['message' => 'AI assistant encountered an error. Please try again.']);

                return;
            }

            $this->streamFallback($service, $message, $originalMessage, $context, $conversation, $userId, $exception);
        }
    }

    private function streamToolResponse(AiAssistantService $service, string $toolName, string $message, string $originalMessage, array $context, AiConversation $conversation, int $userId): void
    {
        $toolPayload = $service->runTool($toolName, $context);
        $toolPayload = $service->decorateToolPayload($toolName, $toolPayload, $context);
        $language = $this->detectLanguage($message);
        $answer = $service->fallbackAnswer($toolName, $toolPayload, $language, includeProviderPrefix: false);

        $this->emit('tool_data', ['tool_name' => $toolName, 'tool_payload' => $toolPayload]);
        $this->emit('text', ['delta' => $answer]);

        $aiMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'tool_name' => $toolName,
            'tool_payload' => $toolPayload,
            'usage' => [],
        ]);

        $conversation->touch();
        $this->logPromptOutcome($userId, $conversation, $aiMessage->id, $originalMessage, $message, $context, $toolName, $toolPayload);

        $this->emit('done', [
            'conversation_id' => $conversation->id,
            'message_id' => $aiMessage->id,
        ]);
    }

    private function shouldBypassModel(string $toolName): bool
    {
        return in_array($toolName, [
            'query_entity_stats',
            'summarize_assignment_operations',
            'generate_subcontractor_reminder',
        ], true);
    }

    private function streamFallback(AiAssistantService $service, string $message, string $originalMessage, array $context, AiConversation $conversation, int $userId, Throwable $exception): void
    {
        $context = array_merge($context, ['query' => $message]);
        $toolName = $service->selectTool($message, $context);
        $toolPayload = $service->runTool($toolName, $context);
        $toolPayload = $service->decorateToolPayload($toolName, $toolPayload, $context);
        $language = $this->detectLanguage($message);
        $answer = $service->fallbackAnswer($toolName, $toolPayload, $language);

        $toolPayload['ai_provider_error'] = $exception->getMessage();

        $this->emit('tool_data', ['tool_name' => $toolName, 'tool_payload' => $toolPayload]);
        $this->emit('text', ['delta' => $answer]);

        $aiMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'tool_name' => $toolName,
            'tool_payload' => $toolPayload,
            'usage' => [],
        ]);

        $conversation->touch();
        $this->logPromptOutcome($userId, $conversation, $aiMessage->id, $originalMessage, $message, $context, $toolName, $toolPayload);

        $this->emit('done', [
            'conversation_id' => $conversation->id,
            'message_id' => $aiMessage->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $clarificationContext
     */
    private function effectiveMessageFromClarification(string $message, array $clarificationContext): string
    {
        $suggestions = collect($clarificationContext['suggestions'] ?? [])
            ->filter(fn (mixed $suggestion): bool => is_string($suggestion) && str_contains($suggestion, ':'))
            ->values();

        if ($suggestions->isEmpty() || ! $this->isLikelyClarificationReply($message)) {
            return $message;
        }

        $normalizedMessage = $this->normalizeClarificationText($message);
        $matchedSuggestion = $suggestions->first(function (string $suggestion) use ($normalizedMessage): bool {
            [$type, $name] = array_pad(explode(':', $suggestion, 2), 2, '');

            return $type !== '' && Str::contains($this->normalizeClarificationText($name), $normalizedMessage);
        });

        if (! is_string($matchedSuggestion)) {
            return $message;
        }

        [$type, $name] = array_map('trim', array_pad(explode(':', $matchedSuggestion, 2), 2, ''));
        $previousPrompt = (string) ($clarificationContext['prompt'] ?? '');

        return match ($type) {
            'project' => Str::contains(Str::lower($previousPrompt), ['berapa', 'jumlah', 'how many'])
                ? "Project {$name} ada berapa lokasi?"
                : "Summary assignment project {$name}",
            'main_contractor' => $this->countPromptForClarifiedEntity($previousPrompt, "main contractor {$name}"),
            'subcontractor' => $this->countPromptForClarifiedEntity($previousPrompt, "subkon {$name}"),
            'subcontractor_user' => $this->countPromptForClarifiedEntity($previousPrompt, "subkon user {$name}"),
            'machine_type' => "Machine type {$name} ada berapa lokasi?",
            default => $message,
        };
    }

    private function isLikelyClarificationReply(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        return str_word_count($normalized) <= 5
            || Str::startsWith($normalized, ['yang ', 'pilih ', 'pakai ', 'gunakan ', 'itu ']);
    }

    private function normalizeClarificationText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/\b(yang|pilih|pakai|gunakan|itu|the)\b/u', '')
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString();
    }

    private function countPromptForClarifiedEntity(string $previousPrompt, string $entityPhrase): string
    {
        $normalized = Str::lower($previousPrompt);
        $activity = match (true) {
            Str::contains($normalized, ['pln']) => 'assignment PLN',
            Str::contains($normalized, ['survey', 'survei']) => 'assignment survey',
            Str::contains($normalized, ['construction', 'konstruksi']) => 'assignment construction',
            Str::contains($normalized, ['bast']) => 'assignment BAST',
            default => 'assignment',
        };
        $status = Str::contains($normalized, ['pending']) ? ' pending' : '';

        if (Str::contains($normalized, ['outstanding', 'tunggakan', 'belum selesai'])) {
            return "Outstanding untuk {$entityPhrase}?";
        }

        return "Berapa {$activity}{$status} untuk {$entityPhrase}?";
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $toolPayload
     */
    private function logPromptOutcome(int $userId, AiConversation $conversation, int $messageId, string $originalPrompt, string $effectivePrompt, array $context, ?string $toolName, array $toolPayload): void
    {
        $outcome = $this->promptOutcome($toolPayload);

        if ($outcome === null) {
            return;
        }

        AiPromptLog::query()->create([
            'user_id' => $userId,
            'ai_conversation_id' => $conversation->id,
            'ai_message_id' => $messageId,
            'outcome' => $outcome,
            'tool_name' => $toolName,
            'prompt' => $originalPrompt,
            'context' => $context,
            'filters' => $toolPayload['filters'] ?? null,
            'matched_entities' => $toolPayload['matched_entities'] ?? null,
            'metadata' => [
                'effective_prompt' => $effectivePrompt,
                'total_count' => $toolPayload['total_count'] ?? null,
                'count_target' => $toolPayload['count_target'] ?? null,
                'intent' => $toolPayload['intent'] ?? null,
                'clarification_suggestions' => $toolPayload['clarification_suggestions'] ?? [],
                'ai_provider_error' => $toolPayload['ai_provider_error'] ?? null,
            ],
        ]);
    }

    /** @param  array<string, mixed>  $toolPayload */
    private function promptOutcome(array $toolPayload): ?string
    {
        if (isset($toolPayload['ai_provider_error'])) {
            return 'provider_error';
        }

        if (($toolPayload['needs_clarification'] ?? false) === true) {
            return 'needs_clarification';
        }

        if (isset($toolPayload['error'])) {
            return 'tool_error';
        }

        if (($toolPayload['total_count'] ?? null) === 0 && filled($toolPayload['filters'] ?? [])) {
            return 'zero_results';
        }

        return null;
    }

    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
        ob_flush();
        flush();
    }

    private function detectLanguage(string $message): string
    {
        $normalized = Str::lower($message);

        if (Str::contains($normalized, [
            'apa', 'siapa', 'saja', 'yang', 'temukan', 'daftar', 'pengguna',
            'telat', 'terlambat', 'ringkas', 'rangkum', 'progres', 'laporan',
            'siap', 'bisa', 'kamu', 'bantu', 'sudah', 'belum',
        ])) {
            return 'id';
        }

        return 'en';
    }
}
