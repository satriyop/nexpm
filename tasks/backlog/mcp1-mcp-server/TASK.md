# MCP1 — MCP server for nex-pm

**Status:** pending
**Phase:** 1 — Design
**Created:** 2026-08-06
**Depends on:** —

## Goal

Build an MCP server that external agents (Hermes, opencode, dll.) can call — same pattern as lsptdi's MCP ops server (`https://lsptdi.com/mcp/lsptdi-ops`) but **more complete and better**.

## Scope

1. **Domain** (OPEN — confirm with Satriyo): nex-pm ops data? generic Laravel template? other?
2. **Stack** (OPEN): Python `fastmcp` / TypeScript SDK / PHP Laravel (`laravel/mcp`)?
3. **Transport & hosting** (OPEN): streamable HTTP + Bearer token (like lsptdi) vs stdio local
4. **"More complete" features** (OPEN): auth + API key mgmt, audit log, metrik/observability, multi-project, auto-docs, unit tests
5. Design: tools/resources/prompts list + auth model
6. Implement + test (Pest/phpunit or pytest)
7. Deploy / document usage for agents

## Out of scope

- _(to be filled during design)_

## Acceptance

- [ ] MCP server callable by external agent (Hermes MCP client verified)
- [ ] Auth (Bearer) works
- [ ] At least N read-only tools + resources operational
- [ ] Tests pass
- [ ] Docs: setup + example agent usage
