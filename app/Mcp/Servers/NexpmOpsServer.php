<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\NexpmStatusResource;
use App\Mcp\Tools\CheckReportReadinessMcpTool;
use App\Mcp\Tools\ContextualPageSummaryMcpTool;
use App\Mcp\Tools\DetectWorkflowGapsMcpTool;
use App\Mcp\Tools\GeneralHelpMcpTool;
use App\Mcp\Tools\GenerateSubcontractorReminderMcpTool;
use App\Mcp\Tools\ListUsersMcpTool;
use App\Mcp\Tools\ProjectHealthBriefingMcpTool;
use App\Mcp\Tools\QueryEntityStatsMcpTool;
use App\Mcp\Tools\ResolveEntityContextMcpTool;
use App\Mcp\Tools\SummarizeAssignmentOperationsMcpTool;
use App\Mcp\Tools\SummarizeDashboardMcpTool;
use App\Mcp\Tools\SummarizePriorityActionsMcpTool;
use App\Mcp\Tools\SummarizeProjectRisksMcpTool;
use App\Mcp\Tools\SummarizeSubcontractorBlockersMcpTool;
use App\Mcp\Tools\WorkflowKnowledgeMcpTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('NexPM Ops')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Read-only operational tools for NexPM administrators and external agents (Hermes/OpenCode, etc.).

Available capabilities:
- project_health_briefing: combined health snapshot (risks, gaps, report readiness)
- summarize_dashboard: assignment status counts + activity matrix + top projects
- summarize_project_risks: risky assignments grouped by project
- summarize_subcontractor_blockers: subcontractor blockers + profiles
- summarize_priority_actions: top actionable items
- summarize_assignment_operations: assignment operations recap
- detect_workflow_gaps: stale/bottlenecked assignments
- check_report_readiness: assignment readiness for reports
- query_entity_stats: entity statistics (machine type counts, filtering)
- generate_subcontractor_reminder: subcontractor reminders
- resolve_entity_context: project/site/subcontractor entity lookup
- contextual_page_summary: context-aware dashboard summary for a specific page
- list_users: user listing with role breakdown
- workflow_knowledge: workflow knowledge reference
- general_help: tool discovery and query guidance

Authenticate web access with Authorization: Bearer <token>. Do not use these tools to mutate domain data.
TXT)]
class NexpmOpsServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        CheckReportReadinessMcpTool::class,
        ContextualPageSummaryMcpTool::class,
        DetectWorkflowGapsMcpTool::class,
        GeneralHelpMcpTool::class,
        GenerateSubcontractorReminderMcpTool::class,
        ListUsersMcpTool::class,
        ProjectHealthBriefingMcpTool::class,
        QueryEntityStatsMcpTool::class,
        ResolveEntityContextMcpTool::class,
        SummarizeAssignmentOperationsMcpTool::class,
        SummarizeDashboardMcpTool::class,
        SummarizePriorityActionsMcpTool::class,
        SummarizeProjectRisksMcpTool::class,
        SummarizeSubcontractorBlockersMcpTool::class,
        WorkflowKnowledgeMcpTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        NexpmStatusResource::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        'dashboard' => 'Summarize NexPM dashboard: assignment status counts, top projects, and recent activity.',
        'project_health' => 'Check project health: risks, workflow gaps, and report-ready assignments.',
    ];
}
