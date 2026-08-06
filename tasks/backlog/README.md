# Backlog

Agreed work tracked as `tasks/backlog/{task-name}/`.

Done work lives in `tasks/done/`. Parked work lives in `tasks/parked/`.

## Active backlog

| Folder | Status | Phase | Summary |
|--------|--------|-------|---------|
| [mcp1-mcp-server](./mcp1-mcp-server/) | pending | 1 | MCP server for nex-pm — like lsptdi's, more complete |

**Recently done:**

- _(belum ada)_

## Convention

0. Task should have a sequence number if related (implies intended implementation order).
1. Each folder has `TASK.md` (created date, goal, decisions, acceptance).
2. When done **and committed**, set status DONE and with the commit hash then move the folder to `tasks/done/`.
3. Clean up regularly; if idle more than one week from created date, move to `tasks/parked/`.
4. Extra docs/scripts for a task stay in that folder, named contextually.
5. Keep this folder list up to date.
