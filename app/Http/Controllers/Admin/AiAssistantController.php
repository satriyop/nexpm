<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\NexpmAssistantAgent;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolResult;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiAssistantController extends Controller
{
    public function store(Request $request, AiAssistantService $service): StreamedResponse
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

        $message = $validated['message'];

        return response()->stream(
            function () use ($service, $message, $context, $conversation): void {
                $this->streamResponse($service, $message, $context, $conversation);
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    private function streamResponse(AiAssistantService $service, string $message, array $context, AiConversation $conversation): void
    {
        $apiKey = config('ai.providers.deepseek.key');

        if (empty($apiKey)) {
            $this->streamFallback($service, $message, $context, $conversation, new \RuntimeException('DeepSeek API key is not configured.'));

            return;
        }

        $agent = new NexpmAssistantAgent($service, $context, $conversation->id);

        try {
            $stream = $agent->stream($message);
            $content = '';
            $toolName = null;
            $toolPayload = [];

            foreach ($stream as $event) {
                if ($event instanceof ToolResult) {
                    $bag = $agent->getResultBag();
                    $toolName = $bag->toolName;
                    $toolPayload = $bag->toolPayload;
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
            }

            $aiMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $content,
                'tool_name' => $toolName,
                'tool_payload' => $toolPayload,
                'usage' => [],
            ]);

            $conversation->touch();

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

            $this->streamFallback($service, $message, $context, $conversation, $exception);
        }
    }

    private function streamFallback(AiAssistantService $service, string $message, array $context, AiConversation $conversation, Throwable $exception): void
    {
        $toolName = $service->selectTool($message, $context);
        $toolPayload = $service->runTool($toolName, $context);
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

        $this->emit('done', [
            'conversation_id' => $conversation->id,
            'message_id' => $aiMessage->id,
        ]);
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
