# 🤝 Contributing to AgileForge v2

🌐 **English** · [Español](CONTRIBUTING.es.md)

Thanks for your interest in contributing to **AgileForge v2**! The project combines human software development with AI agents working together under Scrum and the PMBOK 7th Edition.

---

## 🏛️ Architecture principles

1. **Centralized SQLite persistence**
   - Every state change (stories, meetings, decisions, comments) goes through the services in `api/services/` (`BoardService`, `DirectorService`, `EngineService`, `LlmService`) or `api/Repository.php`, using `Database::transaction()`.
   - The database runs in `WAL` mode with `busy_timeout = 15000` ms and write transactions start with `BEGIN IMMEDIATE`.
   - **Close every cursor** (`$stmt->closeCursor()`) after a single-row `fetch()`, and never keep a transaction open while waiting for an AI model: a long call with an open read cursor locks the database for everybody else ("database is locked").

2. **The MCP server is the standard bridge**
   - Every new capability offered to agents must be a typed tool in `mcp-server/src/` (registered from `index.ts`), with input validated by `zod`.
   - Tool names are in English (snake_case); descriptions must be clear and short. Read-only tools carry `readOnlyHint: true`; write tools must never be reachable from the viewer entry point (`src/viewer.ts`), whose client only does `GET`.
   - There is one codebase and two plugin bundles: **Control** (21 tools) and **View** (10 read-only tools). Update the tool counts in the docs if you add or remove tools.

3. **Human governance is non-negotiable**
   - Agents may move stories to `en_progreso` (with an atomic lock) and to `revision` (with diffs and test evidence).
   - Moving a story to `completada` (done) is reserved for the human Product Owner (web app) or an explicit QA verdict (`review_task` with `decision: approve`) given by the `qa_security_guard` role, on a story in `revision` with evidence, and **never** by the agent that claimed it. The Managing Director cannot do it either. Do not weaken these checks; rejections must return a clear 403/409.
   - The lock is released when a story becomes `completada` or returns to `backlog`; `request_changes` sends it back to `en_progreso` and keeps the developer's lock.
   - Never read approval flags from `$_REQUEST` or the URL.

4. **Two tokens, two scopes**
   - `AGILEFORGE_API_TOKEN` is full access. `AGILEFORGE_VIEW_TOKEN` is read-only: it is valid only for `GET` on `api/index.php`, and `api/tasks.php` must answer `403` to it.
   - `vista.php`, the View plugin and the viewer mode of the MCP server may only use the read-only token. Any new endpoint that writes must reject it. Never embed the main token in a page or a plugin.

5. **API keys never leave the server**
   - Keys live only in `.env` (written with `Bootstrap::setEnvValue()`), never in the database, the logs, the browser or Git. The UI only ever receives a hint such as `sk-ant-…a1b2`.
   - The server only calls fixed provider URLs; never a URL sent by the client.

6. **Technical honesty**
   - No simulated certifications or static scores in the UI. If a value comes from a heuristic, the DOM or an external API, the UI must say so. If no AI engine is available, say it clearly instead of inventing an answer.

7. **Bilingual by default (English + Spanish)**
   - New UI strings go in `assets/js/i18n.js` with both `es` and `en` keys.
   - Documentation is kept in both languages: `README.md` ⇄ `README.es.md`, `BEGINNERS_GUIDE.md` ⇄ `MANUAL_NOVATOS.md`, `docs/API_KEYS.md` ⇄ `docs/API_KEYS.es.md`, `CONTRIBUTING.md` ⇄ `CONTRIBUTING.es.md`, `adapters/*.md` ⇄ `adapters/*.es.md`. If you change one, update the other in the same PR (or say in the PR that a translation is pending).

---

## 🛠️ Local development

```bash
# 1. Clone the repository
git clone https://github.com/JSOTELD/agile-forge.git
cd agile-forge

# 2. Create the database and .env (safe to run again: it never overwrites data)
php bin/migrate.php

# 3. Install and build the MCP server
cd mcp-server
npm install
npm run build     # tsc -> dist/ (used by Cursor, Codex, Gemini)
npm run bundle    # esbuild -> the two plugin bundles (see below)
cd ..

# 4. Start a development server (port 8000 is Colibri's default, so use 8080).
#    Always pass router.php: the built-in server ignores .htaccess and would serve /.env and /data/*.sqlite
php -S localhost:8080 router.php
```

To test AI features you can add your own keys in **⚙️ AI Engine → 🔑 API keys** (see [docs/API_KEYS.md](docs/API_KEYS.md)) or use a local model with Ollama. **Never commit `.env`, `data/*.sqlite` or `data/logs/`** (they are already in `.gitignore`).

---

## 🧪 Checks before opening a Pull Request

These are the same checks CI runs (`.github/workflows/ci.yml`):

```bash
# 1. PHP syntax
find . -name "*.php" ! -path "./mcp-server/*" ! -path "./vendor/*" -exec php -l {} +

# 2. Migration (also try it on a fresh copy: AGILEFORGE_DB_PATH=/tmp/test.sqlite)
php bin/migrate.php

# 3. ES module syntax
for f in assets/js/*.js; do node --check "$f" || exit 1; done

# 4. Type-check and build the MCP server
cd mcp-server && npm ci && npm run check && npm run build && cd ..

# 5. Regenerate the plugin bundles and make sure nothing changes
cd mcp-server && npm run bundle && cd ..
git status --short plugin/    # must show no unexpected changes after committing the bundles

# 6. Validate the marketplace and both plugins (Claude Code CLI)
claude plugin validate .
claude plugin validate plugin/agileforge
claude plugin validate plugin/agileforge-vista
```

### Plugin bundles

The plugins are self-contained, so each one ships its own MCP server as a single file generated from `mcp-server/src/`:

| Bundle | Built from | Plugin |
|---|---|---|
| `plugin/agileforge/server/agileforge-mcp.mjs` | `src/index.ts` (21 tools) | AgileForge Control |
| `plugin/agileforge-vista/server/agileforge-vista-mcp.mjs` | `src/viewer.ts` (10 read-only tools) | AgileForge View |

- **After any change in `mcp-server/src/`, regenerate them:** `cd mcp-server && npm run bundle`.
- **Commit the bundles** together with the source change. They are versioned on purpose: users install the plugin straight from Git, with no build step.
- CI checks that the committed bundles are up to date (it rebuilds them and fails if they differ), so a stale bundle blocks the PR.
- Never edit a bundle by hand, and keep the plugin version in `.claude-plugin/marketplace.json` and in each plugin's manifest in sync.
- `mcp-server/dist/` (from `npm run build`, `tsc`) is separate: it is what the Cursor, Codex and Gemini adapters run. Rebuild it too when you change `src/`.

If you add a new JavaScript module, also add it to the `Validate ES Modules Syntax` step in `ci.yml` and to the import map in `index.php`.

### Pull Request checklist
- [ ] The checks above pass (including `npm run check`, `npm run bundle` with the bundles committed, and `claude plugin validate`).
- [ ] Governance and token rules are intact: only a human or a different QA agent completes a story; the read-only token can never write.
- [ ] No keys, tokens, `.env` or database files in the diff.
- [ ] New UI text has `es` and `en` translations.
- [ ] Docs updated in both languages (or translation flagged as pending).

Welcome to the team!
