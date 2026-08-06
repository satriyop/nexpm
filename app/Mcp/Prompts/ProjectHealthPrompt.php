<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;

#[Description('Check project health: risks, workflow gaps, and report-ready assignments.')]
class ProjectHealthPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        return Response::text('Use the project_health_briefing tool, then explain risks, workflow gaps, and report-ready assignments.');
    }
}

