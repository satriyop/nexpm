<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;

#[Description('Summarize NexPM dashboard: assignment status counts, top projects, and recent activity.')]
class DashboardPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        return Response::text('Use the summarize_dashboard tool, then explain status counts, activity matrix, and top projects.');
    }
}
