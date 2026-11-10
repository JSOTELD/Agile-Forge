# AgileForge · Guía de instalación y adaptadores multi-IA

🌐 [English](README.md) · **Español**

Esta guía explica cómo conectar AgileForge a **Claude Code**, **Cursor**, **Codex CLI** y **Gemini CLI / Google Antigravity**. En todas las rutas, `<path-to-agile-forge>` es la carpeta donde clonaste el repositorio y `<folder>` es el nombre de la carpeta bajo tu servidor web (por ejemplo `htdocs/<folder>`).

---

## 0. Antes de empezar

1. Clona el repo, ejecuta `php bin/migrate.php` (crea la base de datos y el `.env`) y sirve la app (XAMPP o `php -S localhost:8080 router.php`). Ver el [README](../README.es.md#-instalación).
2. **URL de la API.** El servidor MCP usa por defecto `http://localhost/Open-to-code/api` (el nombre de carpeta de la instalación original con XAMPP). Si tu app está en otro sitio, define `AGILEFORGE_API_URL`, por ejemplo `http://localhost/<folder>/api` o `http://localhost:8080/api`.
3. **Token.** El token está en el `.env` de la raíz de la app (`<path-to-agile-forge>/.env`), clave `AGILEFORGE_API_TOKEN` (acceso total) o `AGILEFORGE_VIEW_TOKEN` (solo lectura). El servidor MCP lo encuentra solo, en este orden:
   1. `AGILEFORGE_API_URL` / `AGILEFORGE_API_TOKEN` en el entorno del cliente.
   2. `$AGILEFORGE_HOME/.env`: define `AGILEFORGE_HOME=<path-to-agile-forge>`.
   3. Un `.env` con el token, buscado hacia arriba desde el proyecto abierto (hasta 6 niveles).
   4. `~/.agileforge/config.json`: `{"api_url": "…", "api_token": "…", "view_token": "…"}`.
   Nunca subas ni compartas el token. Pídele al agente que ejecute `connection_info` para ver la URL y el origen de la configuración (nunca muestra el token).
4. Node.js 18+ debe estar en tu `PATH`.

---

## 1. Claude Code: usa los plugins

Para Claude Code no configures el servidor MCP a mano: instala un plugin del marketplace de este repo. Ambos funcionan **sin claves de API y sin IA local**.

```text
/plugin marketplace add JSOTELD/agile-forge
/plugin install agileforge@agileforge          # AgileForge Control: el equipo completo (21 herramientas)
/plugin install agileforge-vista@agileforge    # AgileForge Vista: solo lectura (10 herramientas)
```

Desde un clon local usa `/plugin marketplace add <path-to-agile-forge>`. Control y Vista son **excluyentes**: instala solo uno. Detalles, comandos, agentes, modelos y esfuerzo:

- [plugin/agileforge/README.es.md](../plugin/agileforge/README.es.md) (Control)
- [plugin/agileforge-vista/README.es.md](../plugin/agileforge-vista/README.es.md) (Vista)
- [Resumen en el README principal](../README.es.md#-plugins-de-claude-code-agileforge-control-y-agileforge-vista)

---

## 2. Cursor

Cursor se comunica con el servidor MCP de AgileForge mediante stdio.

1. Compila el servidor una vez: `cd <path-to-agile-forge>/mcp-server && npm install && npm run build`.
2. En Cursor ve a **Settings** > **Features** > **MCP Servers** (o edita `.cursor/mcp.json` en tu proyecto) y añade la configuración de [`cursor.json`](cursor.json):

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
3. Añade `"AGILEFORGE_HOME": "<path-to-agile-forge>"` a `env` para que el servidor lea el token del `.env` de la app (o define tú mismo `AGILEFORGE_API_TOKEN`).
4. Reinicia Cursor o recarga los servidores MCP. Las **21 herramientas** (`get_overview`, `claim_task`, …) estarán disponibles en Composer y Chat.

---

## 3. Codex CLI

1. Localiza tu archivo de configuración de Codex (`~/.codex/config.json` o equivalente).
2. Agrega la definición de [`codex.json`](codex.json) (mismos marcadores que arriba):

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

Configura el servidor MCP en tu archivo de configuración de extensiones (`settings.json` o los ajustes de plugins MCP), usando [`gemini.json`](gemini.json):

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

## 5. Herramientas, modo lector y herramientas opcionales

- **Servidor completo** (`dist/index.js`): **21 herramientas** (10 de lectura, 11 de escritura). Ver la [tabla de herramientas](../README.es.md#herramientas-mcp).
- **Modo lector** (solo lectura, **10 herramientas**, usa el token de solo lectura): apunta `args` a `<path-to-agile-forge>/mcp-server/dist/viewer.js`, o deja `index.js` y añade `"AGILEFORGE_MODE": "vista"` a `env`. Úsalo para clientes o personas que solo deben mirar el tablero.
- **Herramientas opcionales del motor:** `director_instruction` y `ask_agent` solo se registran con `"AGILEFORGE_ENGINE_TOOLS": "on"` (**23 herramientas** en total). Usan el motor de IA de la web, así que necesitan una clave de proveedor o una IA local configurada en la app. Ver [docs/API_KEYS.es.md](../docs/API_KEYS.es.md).
- El protocolo de los agentes (toma, evidencia, revisión, gobernanza) está en [AGENTS.es.md](AGENTS.es.md).

---

## 6. Comprobar que funciona

1. Recompila tras traer cambios: `cd <path-to-agile-forge>/mcp-server && npm run build` (y `npm run check` para comprobar tipos).
2. En tu cliente, pídele al agente que **ejecute `connection_info`**: debe mostrar la URL que esperas y el modo (`control` o `vista`, el de solo lectura). Luego pide `get_sprint_status` para confirmar que puede leer el sprint.
3. Si no conecta, comprueba que el servidor web está en marcha, que la URL termina en `/api` y que el token está en el `.env` que el servidor puede encontrar (sección 0).

> Los adaptadores usan `dist/`; los plugins de Claude Code incluyen sus propios bundles. Si contribuyes, mira [CONTRIBUTING.es.md](../CONTRIBUTING.es.md#bundles-de-los-plugins).
