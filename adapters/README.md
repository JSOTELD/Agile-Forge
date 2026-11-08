# AgileForge · Guía de Instalación y Adaptadores Multi-IA

Esta guía detalla cómo conectar AgileForge v2.0 a **Claude Code**, **Cursor**, **Codex CLI** y **Gemini CLI / Google Antigravity**.

---

## 1. Instalación en Claude Code (Modo Plugin Oficial)

AgileForge está empaquetado como un plugin de primera clase para Claude Code bajo `plugin/agileforge/`.

### Instalación Local
En tu terminal de Claude Code, añade el marketplace local y activa el plugin:

```bash
# 1. Registrar marketplace local
/plugin marketplace add .

# 2. Instalar y habilitar el plugin AgileForge
/plugin install agileforge
```

### Comandos de Barra Disponibles en Claude Code
Una vez activo el plugin, dispones de:
- `/sprint`: Muestra las métricas en vivo, Story Points y bloqueos del Sprint activo.
- `/tomar <US-id>`: Reclama una historia con lock atómico exclusivo.
- `/reunion <tipo> <tema>`: Convoca una sesión de Brainstorming, Consejo ADR, Compliance o Daily.
- `/dod <US-id>`: Audita la Definition of Done y la evidencia técnica entregada.
- `/daily`: Genera el resumen ejecutivo del standup por agente.

---

## 2. Instalación en Cursor

Cursor se comunica directamente con el servidor MCP de AgileForge mediante stdio.

1. Abre Cursor y ve a **Settings** > **Features** > **MCP Servers** (o edita `.cursor/mcp.json` en tu proyecto).
2. Añade la configuración disponible en `adapters/cursor.json`:

```json
{
  "mcpServers": {
    "agileforge": {
      "command": "node",
      "args": ["D:/xampp/htdocs/Open-to-code/mcp-server/dist/index.js"],
      "env": {
        "AGILEFORGE_API_URL": "http://localhost/Open-to-code/api",
        "AGILEFORGE_CLIENT": "cursor"
      }
    }
  }
}
```
3. Reinicia Cursor o recarga los servidores MCP. Las 14 herramientas (`get_sprint_status`, `claim_task`, etc.) estarán disponibles en Composer y Chat.

---

## 3. Instalación en Codex CLI

Para clientes basados en Codex CLI:

1. Localiza tu archivo de configuración de Codex (`~/.codex/config.json` o equivalente).
2. Agrega la definición desde `adapters/codex.json`:

```json
{
  "mcp_servers": {
    "agileforge": {
      "command": "node",
      "args": ["D:/xampp/htdocs/Open-to-code/mcp-server/dist/index.js"],
      "env": {
        "AGILEFORGE_API_URL": "http://localhost/Open-to-code/api",
        "AGILEFORGE_CLIENT": "codex"
      }
    }
  }
}
```

---

## 4. Instalación en Gemini CLI / Google Antigravity

Para Gemini CLI o el entorno Google Antigravity:

1. Configura el servidor MCP en tu archivo de configuración de extensiones (`settings.json` o configuración de plugins MCP):

```json
{
  "mcpServers": {
    "agileforge": {
      "command": "node",
      "args": ["D:/xampp/htdocs/Open-to-code/mcp-server/dist/index.js"],
      "env": {
        "AGILEFORGE_API_URL": "http://localhost/Open-to-code/api",
        "AGILEFORGE_CLIENT": "gemini"
      }
    }
  }
}
```

---

## 5. Verificación de Funcionamiento

Para verificar que el servidor MCP se comunica correctamente con la base de datos:
```powershell
cd d:\xampp\htdocs\Open-to-code\mcp-server
npm test
# O ejecutar directamente el script de prueba:
npx tsx ../scratch/test_mcp_stdio.ts
```
Debe retornar:
`[PASS] Handshake inicializado, 14 herramientas cargadas y sprint consultado.`

