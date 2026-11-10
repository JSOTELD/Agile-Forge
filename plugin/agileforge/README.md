# AgileForge Control · Claude Code plugin

[Español](README.es.md)

Run the AgileForge board from Claude Code: a **Director** that decides and delegates, seven specialists with their own model and effort, locked stories, QA reviews, meetings and decisions (ADR). Claude Code is the brain: **no API keys or local AI needed**.

Requirements: Claude Code, Node.js 18+ on `PATH`, and the AgileForge web app running (PHP 8.2, e.g. XAMPP).

## Install

From GitHub:

```text
/plugin marketplace add JSOTELD/agile-forge
/plugin install agileforge@agileforge
```

From a local clone (the folder that holds `.claude-plugin/marketplace.json`):

```text
/plugin marketplace add /path/to/agile-forge
/plugin install agileforge@agileforge
```

Then run `/reload-plugins` or start a new session. `claude plugin marketplace add …` and `claude plugin install …` work the same from a shell.

**Control and View are mutually exclusive.** Install only one. If you have View: `/plugin uninstall agileforge-vista@agileforge`.

## Configuration (URL and token)

- **Default URL:** `http://localhost/Open-to-code/api`. If your app lives at another path or port, set `AGILEFORGE_API_URL`.
- **Token:** in the `.env` at the root of the AgileForge app (e.g. `D:\xampp\htdocs\Open-to-code\.env`, key `AGILEFORGE_API_TOKEN`). Created by `php bin/migrate.php` or on first run. Never commit it.

The MCP server looks for its configuration in this order (an empty value counts as missing):

1. `AGILEFORGE_API_URL` and `AGILEFORGE_API_TOKEN` in the environment where you start `claude`.
2. `$AGILEFORGE_HOME/.env`: point `AGILEFORGE_HOME` at the app folder. Easiest when you work in other projects (`setx AGILEFORGE_HOME "D:\xampp\htdocs\Open-to-code"` on Windows; reopen the terminal).
3. A `.env` containing `AGILEFORGE_API_TOKEN`, searched upwards from the open project (up to 6 levels). Opening Claude Code inside the app folder is enough.
4. `~/.agileforge/config.json`: `{"api_url": "…", "api_token": "…"}`.

To check, ask "run connection_info": it shows the URL, the mode and where the configuration came from (never the token). At session start a hook prints a sprint summary of up to 6 lines, or the URL that failed.

The Control bundle exposes 21 tools (10 read + 11 write, including `start_sprint`); 23 with the engine tools below.

Optional: `AGILEFORGE_ENGINE_TOOLS=on` also registers `director_instruction` and `ask_agent`, which use the web app's AI engine (they need a key or local AI configured in the app).

## Commands

| command | what it does |
|---|---|
| `/agileforge:sprint [project]` | Sprint status: goal, progress, columns, who works on what |
| `/agileforge:daily [project]` | Daily: since yesterday, per role, blockers |
| `/agileforge:tomar <US-id> [agent]` | The specialist claims the story (lock), builds it and submits it for review |
| `/agileforge:dod <US-id>` | QA audits the Definition of Done and gives a verdict |
| `/agileforge:reunion <type> <topic>` | Agent meeting with minutes on the board (no web engine) |
| `/agileforge:director <instruction>` | The main thread acts as Director: decides, records, creates and delegates |
| `/agileforge:equipo [agent]` | Agents with model, effort and specialty |
| `/agileforge:probar [scope]` | Tester (v0, experimental): tries to break the business rules in a disposable environment and logs each confirmed bug (`[BUG]…`) |

For a whole session as Director (opus/high, delegates with the Agent tool): `claude --agent agileforge:director`.

## Agents

| agent | role_key | model | effort |
|---|---|---|---|
| director | — | opus | high |
| chief-architect | lead_architect | opus | high |
| qa-security | qa_security_guard | sonnet | high |
| backend | backend_architect | sonnet | medium |
| frontend | frontend_master | sonnet | medium |
| creative-director | creative_director | sonnet | low |
| legal-compliance | legal_counsel | sonnet | low |
| project-manager | project_manager | haiku | low |
| Tester (v0, experimental) | — | opus | high |

Only backend, frontend and chief-architect edit files. QA runs verification commands but does not edit. The Tester is not a board role: it only writes tests in the folder its recipe allows (`.agileforge/testing.md`), never touches source code or real data, and does not claim, submit or approve stories. **Governance:** `claim_task` before editing; delivery comes with evidence (`submit_for_review`); nobody approves their own work; only QA (`review_task`) or a human in the web app can move a story to `completada`.

**Changing model or effort:**

- For one task: ask for it ("delegate to backend with opus"); Claude passes `model`/`effort` for that invocation.
- For every subagent: `CLAUDE_CODE_SUBAGENT_MODEL` (with `CLAUDE_CODE_SUBAGENT_MODEL_FORCE=1` it overrides the frontmatter) and `CLAUDE_CODE_EFFORT_LEVEL`.
- Permanently: in a local clone, edit `model:` and `effort:` in `plugin/agileforge/agents/<agent>.md`, install from that local marketplace and run `/reload-plugins`. Do not edit the plugin cache: updates overwrite it.

## Notices

- In plugin mode all agent work uses **your Claude Code plan or quota**. Opus/high costs the most; the table above keeps it for deciding and designing.
- AgileForge is an independent project (MIT), **not affiliated with Anthropic**.
- The Legal & Compliance role is for guidance only and is **not professional legal advice**.
