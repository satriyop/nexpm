<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiAssistantController extends Controller
{
    public function store(Request $request, AiAssistantService $assistant): JsonResponse
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

        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->find($validated['conversation_id'] ?? null);

        if ($conversation === null) {
            $conversation = AiConversation::query()->create([
                'user_id' => $request->user()->id,
                'title' => Str::limit($validated['message'], 80),
                'context' => $validated['context'] ?? [],
            ]);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $result = $assistant->answer($request->user(), $validated['message'], $validated['context'] ?? []);

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
}
