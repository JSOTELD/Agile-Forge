# AgileForge View (Vista) · Claude Code plugin

[Español](README.es.md)

Shows how work is going in AgileForge without being able to change anything: sprint, columns, who does what, meetings, decisions and activity. **Read-only**: no write tools, no AI engines, no API keys.

Requirements: Claude Code, Node.js 18+ on `PATH`, and the AgileForge web app running.

## Install

```text
/plugin marketplace add JSOTELD/agile-forge
/plugin install agileforge-vista@agileforge
```

From a local clone: `/plugin marketplace add /path/to/agile-forge` and the same `install`. Then `/reload-plugins`.

**View and Control are mutually exclusive.** Install only one. If you have Control: `/plugin uninstall agileforge@agileforge`.

## Configuration (URL and view token)

- **Default URL:** `http://localhost/Open-to-code/api`; change it with `AGILEFORGE_API_URL`.
- **Token:** use the read-only `AGILEFORGE_VIEW_TOKEN` from the `.env` at the app root (e.g. `D:\xampp\htdocs\Open-to-code\.env`). It is generated automatically when empty. It only allows GET on `api/index.php`, so even if leaked it cannot change the board.

Lookup order: `AGILEFORGE_VIEW_TOKEN` (or, if missing, `AGILEFORGE_API_TOKEN`) and `AGILEFORGE_API_URL` variables → `$AGILEFORGE_HOME/.env` → a `.env` searched upwards from the project (6 levels) → `~/.agileforge/config.json` (`{"api_url": "…", "view_token": "…"}`). For people who should only watch, share only the view token.

Check with "run connection_info". At session start a hook prints a summary of up to 6 lines.

## Commands

| command | shows |
|---|---|
| `/agileforge-vista:estado` | 5-line summary: progress and since yesterday, who does what, review, meetings, decisions |
| `/agileforge-vista:tablero [status or role]` | Stories per column with owner and points |
| `/agileforge-vista:reuniones [MEET-id]` | Latest meetings or one meeting in detail |
| `/agileforge-vista:decisiones [n]` | Latest decisions (ADR) |
| `/agileforge-vista:actividad [n]` | Recent events grouped by day |

Agent: `observador` (haiku, low effort), with read tools only. To change its model: `CLAUDE_CODE_SUBAGENT_MODEL` or, in a local clone, `model:`/`effort:` in `agents/observador.md`.

## Notices

- The plugin uses **your Claude Code plan or quota**.
- AgileForge is an independent project (MIT), **not affiliated with Anthropic**.
- Legal-role decisions shown here are for guidance only and are **not professional legal advice**.
