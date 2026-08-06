# NexPM MCP Operations

Endpoint: `POST https://{app}/mcp/nexpm-ops`
Auth: `Authorization: Bearer <token>`

## Setup

1. Set in `.env`:
```
AI_MCP_ENABLED=true
AI_MCP_TOKEN=<your-secret-token>
AI_MCP_ACTING_AS_USER_ID=<admin-user-id>
AI_MCP_LOCAL_ACTING_AS=false
```

2. Run `php artisan migrate` (creates `mcp_audit_logs` table).

3. Local dev (stdio): `php artisan mcp:start nexpm-ops`

## Available Tools (read-only)

| Tool | Description |
|---|---|
| `project_health_briefing` | Combined health: risks, gaps, report readiness |
| `summarize_dashboard` | Assignment status counts + activity matrix |
| `summarize_project_risks` | Risky assignments grouped by project |
| `summarize_subcontractor_blockers` | Subcontractors ranked by blockers |
| `summarize_priority_actions` | Top actionable items |
| `summarize_assignment_operations` | Operations recap |
| `detect_workflow_gaps` | Stale/bottlenecked assignments |
| `check_report_readiness` | Report-ready assignments |
| `query_entity_stats` | Entity statistics |
| `generate_subcontractor_reminder` | Reminder with outstanding counts |
| `resolve_entity_context` | Entity lookup |
| `contextual_page_summary` | Dashboard summary for a page |
| `list_users` | User list + role breakdown |
| `workflow_knowledge` | Workflow knowledge reference |
| `general_help` | Tool discovery + guidance |

## Resources

| URI | Content |
|---|---|
| `nexpm://status` | System status: assignment counts per status |

## Prompts

| Prompt | Query |
|---|---|
| `dashboard` | Summarize NexPM dashboard |
| `project_health` | Risks, gaps, report readiness |

## Hermes MCP Client Config

```yaml
mcp_servers:
  nexpm-ops:
    url: https://{app}/mcp/nexpm-ops
    headers:
      Authorization: Bearer ${NEXPM_MCP_TOKEN}
    enabled: true
```

## Audit

All tool calls are logged to `mcp_audit_logs` (tool_name, token_prefix, acting_user_id, latency_ms, status).
