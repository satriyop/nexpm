<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\NexpmAssistantAgent;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantController extends Controller
{
    public function store(Request $request, AiAssistantService $service): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'context' => ['nullable', 'array'],
            'context.type' => ['nullable', 'string', 'max:50'],
            'context.id' => ['nullable'],
            'context.label' => ['nullable', 'string', 'max:255'],
            'context.url' => ['nullable', 'string', 'max:255'],
            'context.component' => ['nullable', 'string', 'max:255'],
            'context.project_id' => ['nullable', 'integer'],
            'context.site_id' => ['nullable', 'integer'],
            'context.assignment_id' => ['nullable', 'integer'],
        ]);

        $context = $validated['context'] ?? [];

        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->find($validated['conversation_id'] ?? null);

        if ($conversation === null) {
            $conversation = AiConversation::query()->create([
                'user_id' => $request->user()->id,
                'title' => Str::limit($validated['message'], 80),
                'context' => $context,
            ]);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $result = $this->runAgent($service, $validated['message'], $context, $conversation->id);

        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['answer'],
            'tool_name' => $result['tool_name'],
            'tool_payload' => $result['tool_payload'],
            'usage' => $result['usage'],
        ]);

        $conversation->touch();

        return response()->json([
            'conversation_id' => $conversation->id,
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'tool_name' => $message->tool_name,
                'tool_payload' => $message->tool_payload,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Run the agent, falling back to local keyword routing when the AI provider is unavailable.
     *
     * @param  array<string, mixed>  $context
     * @return array{answer: string, tool_name: string, tool_payload: array<string, mixed>, usage: array<string, mixed>}
     */
    private function runAgent(AiAssistantService $service, string $message, array $context, int $conversationId): array
    {
        $apiKey = config('ai.providers.deepseek.key');

        if (empty($apiKey)) {
            return $this->fallback($service, $message, $context, new \RuntimeException('DeepSeek API key is not configured.'));
        }

        $agent = new NexpmAssistantAgent($service, $context, $conversationId);

        try {
            $response = $agent->prompt($message);
            $bag = $agent->getResultBag();

            $toolName = $bag->toolName ?? 'general_help';
            $toolPayload = $bag->toolPayload;

            if ($toolPayload === []) {
                $toolPayload = $service->runTool($toolName, $context);
            }

            return [
                'answer' => $response->text,
                'tool_name' => $toolName,
                'tool_payload' => $toolPayload,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                ],
            ];
        } catch (Throwable $exception) {
            return $this->fallback($service, $message, $context, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{answer: string, tool_name: string, tool_payload: array<string, mixed>, usage: array<string, mixed>}
     */
    private function fallback(AiAssistantService $service, string $message, array $context, Throwable $exception): array
    {
        $toolName = $service->selectTool($message, $context);
        $toolPayload = $service->runTool($toolName, $context);
        $language = $this->detectLanguage($message);
        $answer = $service->fallbackAnswer($toolName, $toolPayload, $language);

        return [
            'answer' => $answer,
            'tool_name' => $toolName,
            'tool_payload' => array_merge($toolPayload, [
                'ai_provider_error' => $exception->getMessage(),
            ]),
            'usage' => [],
        ];
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
