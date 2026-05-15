<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $userId = $request->user()->id;

        $queryAuditLog = AiMessage::query()
            ->where('tool_name', 'query_database')
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $userId))
            ->with('conversation:id,title')
            ->latest()
            ->limit(50)
            ->get(['id', 'ai_conversation_id', 'tool_payload', 'created_at']);

        $conversations = AiConversation::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(30)
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return Inertia::render('settings/AiAssistant', [
            'preferences' => $request->user()->getAiPreferences(),
            'queryAuditLog' => $queryAuditLog,
            'conversations' => $conversations,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:standard,full'],
            'max_rows' => ['required', 'integer', 'in:100,500,1000,5000'],
        ]);

        $request->user()->update(['ai_preferences' => $validated]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI assistant settings saved.']);

        return to_route('ai-settings.edit');
    }

    public function destroyConversation(Request $request, AiConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $conversation->delete();

        return back();
    }
}
