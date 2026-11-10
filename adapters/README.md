# AgileForge · Installation and multi-AI adapters guide

🌐 **English** · [Español](README.es.md)

This guide explains how to connect AgileForge to **Claude Code**, **Cursor**, **Codex CLI** and **Gemini CLI / Google Antigravity**. In every path, `<path-to-agile-forge>` is the folder where you cloned the repository and `<folder>` is the folder name under your web server (for example `htdocs/<folder>`).

---

## 0. Before you start

1. Clone the repo, run `php bin/migrate.php` (creates the database and the `.env`) and serve the app (XAMPP or `php -S localhost:8080 router.php`). See the [README](../README.md#-installation).
2. **API URL.** The MCP server uses `http://localhost/Open-to-code/api` by default (the folder name of the original XAMPP setup). If your app lives elsewhere, set `AGILEFORGE_API_URL`, for example `http://localhost/<folder>/api` or `http://localhost:8080/api`.
3. **Token.** The token is in the `.env` at the root of the app (`<path-to-agile-forge>/.env`), key `AGILEFORGE_API_TOKEN` (full access) or `AGILEFORGE_VIEW_TOKEN` (read-only). The MCP server finds it on its own, in this order:
   1. `AGILEFORGE_API_URL` / `AGILEFORGE_API_TOKEN` in the environment of the client.
   2. `$AGILEFORGE_HOME/.env`: set `AGILEFORGE_HOME=<path-to-agile-forge>`.
   3. A `.env` containing the token, searched upwards from the open project (up to 6 levels).
   4. `~/.agileforge/config.json`: `{"api_url": "…", "api_token": "…", "view_token": "…"}`.
   Never commit or share the token. Ask the agent to run `connection_info` to see the URL and the source of the configuration (it never shows the token).
4. Node.js 18+ must be on your `PATH`.

---

## 1. Claude Code: use the plugins

For Claude Code, do not configure the MCP server by hand: install a plugin from the marketplace in this repo. Both work **without API keys and without a local AI**.

```text
/plugin marketplace add JSOTELD/agile-forge
/plugin install agileforge@agileforge          # AgileForge Control: the full team (21 tools)
/plugin install agileforge-vista@agileforge    # AgileForge View: read-only (10 tools)
```

From a local clone use `/plugin marketplace add <path-to-agile-forge>`. Control and View are **mutually exclusive**: install only one. Details, commands, agents, models and effort:

- [plugin/agileforge/README.md](../plugin/agileforge/README.md) (Control)
- [plugin/agileforge-vista/README.md](../plugin/agileforge-vista/README.md) (View)
- [Overview in the main README](../README.md#-claude-code-plugins-agileforge-control--agileforge-view)

---

## 2. Cursor

Cursor talks to the AgileForge MCP server over stdio.

1. Build the server once: `cd <path-to-agile-forge>/mcp-server && npm install && npm run build`.
2. In Cursor go to **Settings** > **Features** > **MCP Servers** (or edit `.cursor/mcp.json` in your project) and add the configuration from [`cursor.json`](cursor.json):

```json
{
  "mcpServers": {
    "agileforge": {
      "command": "node",
      "args": ["<path-to-agile-forge>/mcp-server/dist/index.js"],
      "env": {
        "AGILEFORGE_API_URL": "http://localhost/<folder>/api",
        "AGILEFORGE_CLIENT": "cursor"
      }
    }
  }
}
```
3. Add `"AGILEFORGE_HOME": "<path-to-agile-forge>"` to `env` so the server can read the token from the app's `.env` (or set `AGILEFORGE_API_TOKEN` yourself).
4. Restart Cursor or reload the MCP servers. The **21 tools** (`get_overview`, `claim_task`, …) will be available in Composer and Chat.

---

## 3. Codex CLI

1. Find your Codex configuration file (`~/.codex/config.json` or equivalent).
2. Add the definition from [`codex.json`](codex.json) (same placeholders as above):

```json
{
  "mcp_servers": {
    "agileforge": {
      "command": "node",
      "args": ["<path-to-agile-forge>/mcp-server/dist/index.js"],
      "env": {
        "AGILEFORGE_API_URL": "http://localhost/<folder>/api",
        "AGILEFORGE_CLIENT": "codex"
      }
    }
  }
}
```

---

## 4. Gemini CLI / Google Antigravity

Configure the MCP server in your extensions configuration file (`settings.json` or the MCP plugin settings), using [`gemini.json`](gemini.json):

```json
{
  "mcpServers": {
    "agileforge": {
      "command": "node",
      "args": ["<path-to-agile-forge>/mcp-server/dist/index.js"],
      "env": {
        "AGILEFORGE_API_URL": "http://localhost/<folder>/api",
        "AGILEFORGE_CLIENT": "gemini"
      }
    }
  }
}
```

---

## 5. Tools, viewer mode and optional tools

- **Full server** (`dist/index.js`): **21 tools** (10 read, 11 write). See the [tool table](../README.md#mcp-tools).
- **Viewer mode** (read-only, **10 tools**, uses the read-only token): point `args` at `<path-to-agile-forge>/mcp-server/dist/viewer.js`, or keep `index.js` and add `"AGILEFORGE_MODE": "vista"` to `env`. Use it for clients or people who should only watch the board.
- **Optional engine tools:** `director_instruction` and `ask_agent` are registered only with `"AGILEFORGE_ENGINE_TOOLS": "on"` (**23 tools** in total). They use the web app's AI engine, so they need a provider key or a local AI configured in the app. See [docs/API_KEYS.md](../docs/API_KEYS.md).
- The agent protocol (claim, evidence, review, governance) is in [AGENTS.md](AGENTS.md).

---

## 6. Check that it works

1. Rebuild after pulling changes: `cd <path-to-agile-forge>/mcp-server && npm run build` (and `npm run check` for a type check).
2. In your client, ask the agent to **run `connection_info`**: it must show the URL you expect and the mode (`control` or `vista`, the read-only one). Then ask for `get_sprint_status` to confirm it can read the sprint.
3. If it cannot connect, check that the web server is running, that the URL ends in `/api`, and that the token is in the `.env` the server can find (section 0).

> The adapters use `dist/`; the Claude Code plugins ship their own bundles. If you contribute, see [CONTRIBUTING.md](../CONTRIBUTING.md#plugin-bundles).
