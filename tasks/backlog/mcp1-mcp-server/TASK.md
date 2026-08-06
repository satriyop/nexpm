# MCP1 — MCP server for nex-pm

**Status:** pending
**Phase:** 1 — Design
**Created:** 2026-08-06
**Depends on:** —

## Goal

Build an MCP server for **nex-pm data** that external agents (Hermes, opencode, dll.) can call — same pattern as lsptdi's MCP ops server (`App\Mcp\Servers\LsptdiOpsServer`, `laravel/mcp ^0.9`) but **more complete and better**.

## Decisions (confirmed 2026-08-06)

- **Domain:** nex-pm data (read-only ops tools, same pattern as lsptdi)
- **Stack:** Laravel + `laravel/mcp` (^0.9, align with lsptdi) — PHP 8.4, Inertia app
- **Hosting:** bareng dengan app (`routes` + middleware, deployed with nex-pm), endpoint `https://{app}/mcp/nexpm-ops`
- **Auth:** bearer token (Sanctum-style) → acting user, via middleware (pola `AuthenticateAiMcp` lsptdi) — diperkuat: multi-token per agent + revocation
- **Referensi:** `~/dev/laravel-project/lsptdi/tasks/done/ai6-mcp-tool-plane/` (TASK.md + `app/Mcp/`, `app/Http/Middleware/AuthenticateAiMcp.php`, `docs/mcp-ops.md`)

## Exploration findings (2026-08-06, zero code)

**Domain:** app manajemen proyek infrastruktur SPKLU/kelistrikan (charging station) PLN.
Entities: Project → Site (SPKLU) → Assignment (survey → construction → PLN → BAST), Client, MainContractor, Subcontractor, Report, SiteType, MachineType.

**Status pipeline (16):** PENDING, DROP, VERIFIED, REPORTED (shared); SURVEY, DOCUMENT; CONSTRUCTION, MACHINE_ONSITE, DONE, LIVE; REGISTRATION, BILLING, CONNECTION, KWH_DONE; SUBMITTED, REVISION — enum `App\Enums\AssignmentStatus` (label(), color(), verifiableStatuses(), adminLocked()).

**Roles:** super_admin, admin, subcontractor, drafter, project_manager.

**Domain AI layer siap dibungkus:** `App\Services\Ai\AiAssistantService` (tools: list_users, contextual_page_summary, detect_workflow_gaps, project_health_briefing, workflow_knowledge, resolve_entity_context, query_entity_stats, summarize_assignment_operations, generate_subcontractor_reminder, summarize_priority_actions, summarize_project_risks, summarize_subcontractor_blockers, check_report_readiness, summarize_dashboard, general_help) + `AiQueryPlanner` + `DbSchemaService` (ALLOWED_TABLES 18 tabel + join paths).

**Stack terpasang:** laravel/mcp v0.7.0 (sudah di vendor/lock, belum di require composer.json), laravel/ai ^0.6.8, PHP 8.3+. config/ai.php ada tapi belum ada bagian mcp.

## Scope (proposed "lebih complete" vs lsptdi)

1. **Tools** — wrap domain logic nex-pm (read-only, `#[IsReadOnly]`). Daftar tool: TBD — perlu eksplorasi model bisnis nex-pm
2. **Auth lebih kuat** — multiple tokens per agent (nama agent, scope), revoke/rotate, config `ai.mcp.*` / `AI_MCP_*`
3. **Audit log** — tabel `mcp_audit_logs`: tool, token/agent, status, latency, timestamp
4. **Metrik** — hit count + latency per tool (queryable via resource atau endpoint internal)
5. **Resources + Prompts** — MCP resources (statis) + prompts (query umum) — yang di lsptdi belum ada
6. **Docs** — `docs/mcp-ops.md` + contoh config client (Hermes MCP YAML, opencode)
7. **Tests** — Pest: invoke tool, auth denied, middleware token, audit tercatat

## Out of scope

- Write surface (no write tools — read-only seperti lsptdi)
- Multi-tenant di luar nex-pm

## Acceptance

- [ ] External agent (Hermes) invoke ≥1 tool nex-pm sukses di dev
- [ ] Auth bearer: valid token OK, invalid/revoked denied
- [ ] Audit log tercatat per pemanggilan
- [ ] Metrik per tool tersedia
- [ ] ≥1 resource/prompt jalan
- [ ] Tests hijau
- [ ] Docs + contoh config agent
