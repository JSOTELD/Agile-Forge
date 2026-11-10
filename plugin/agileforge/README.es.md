# AgileForge Control · plugin de Claude Code

[English](README.md)

Maneja el tablero de AgileForge desde Claude Code: un **Director** que decide y delega, siete especialistas con su propio modelo y esfuerzo, historias con lock, revisiones de QA, reuniones y decisiones (ADR). El cerebro es Claude Code: **no necesita claves de API ni IA local**.

Requisitos: Claude Code, Node.js 18+ en el `PATH` y la web de AgileForge en marcha (PHP 8.2, p. ej. XAMPP).

## Instalación

Desde GitHub:

```text
/plugin marketplace add JSOTELD/agile-forge
/plugin install agileforge@agileforge
```

Desde un clon local (la carpeta que contiene `.claude-plugin/marketplace.json`):

```text
/plugin marketplace add D:/xampp/htdocs/Open-to-code
/plugin install agileforge@agileforge
```

Después ejecuta `/reload-plugins` o abre una sesión nueva. En la terminal funcionan igual `claude plugin marketplace add …` y `claude plugin install …`.

**Control y Vista son excluyentes.** Instala solo uno. Si tienes Vista: `/plugin uninstall agileforge-vista@agileforge`.

## Configuración (URL y token)

- **URL por defecto:** `http://localhost/Open-to-code/api`. Si tu app está en otra ruta o puerto, define `AGILEFORGE_API_URL`.
- **Token:** está en el `.env` de la raíz de la app AgileForge (p. ej. `D:\xampp\htdocs\Open-to-code\.env`, clave `AGILEFORGE_API_TOKEN`). Lo crea `php bin/migrate.php` o el primer arranque. No lo subas a Git.

El servidor MCP busca la configuración en este orden (un valor vacío cuenta como ausente):

1. Variables `AGILEFORGE_API_URL` y `AGILEFORGE_API_TOKEN` del entorno donde abres `claude`.
2. `$AGILEFORGE_HOME/.env`: apunta `AGILEFORGE_HOME` a la carpeta de la app. Es lo más cómodo si trabajas en otros proyectos (`setx AGILEFORGE_HOME "D:\xampp\htdocs\Open-to-code"` en Windows; reabre la terminal).
3. Un `.env` con `AGILEFORGE_API_TOKEN` buscando hacia arriba desde el proyecto abierto (hasta 6 niveles). Abrir Claude Code dentro de la carpeta de la app basta.
4. `~/.agileforge/config.json`: `{"api_url": "…", "api_token": "…"}`.

Para comprobarlo, pide "ejecuta connection_info": muestra la URL, el modo y de dónde salió la configuración (nunca el token). Al iniciar sesión, un hook muestra un resumen del sprint de ≤6 líneas o indica qué URL falló.

El bundle Control expone 21 herramientas (10 de lectura + 11 de escritura, incluida `start_sprint`); 23 con las de motor de abajo.

Opcional: `AGILEFORGE_ENGINE_TOOLS=on` registra además `director_instruction` y `ask_agent`, que usan el motor de IA de la web (necesitan una clave o IA local configurada en la app).

## Comandos

| comando | qué hace |
|---|---|
| `/agileforge:sprint [proyecto]` | Estado del sprint: meta, avance, columnas, quién trabaja en qué |
| `/agileforge:daily [proyecto]` | Daily: desde ayer, por rol, bloqueos |
| `/agileforge:tomar <US-id> [agente]` | El especialista toma la historia (lock), la desarrolla y la envía a revisión |
| `/agileforge:dod <US-id>` | QA audita la DoD y dictamina |
| `/agileforge:reunion <tipo> <tema>` | Reunión entre agentes con acta en el tablero (sin motor web) |
| `/agileforge:director <instrucción>` | El hilo principal actúa como Director: decide, registra, crea y delega |
| `/agileforge:equipo [agente]` | Agentes con modelo, esfuerzo y especialidad |

Para una sesión entera como Director (opus/high, delega con la herramienta Agent): `claude --agent agileforge:director`.

## Agentes

| agente | role_key | modelo | esfuerzo |
|---|---|---|---|
| director | — | opus | high |
| chief-architect | lead_architect | opus | high |
| qa-security | qa_security_guard | sonnet | high |
| backend | backend_architect | sonnet | medium |
| frontend | frontend_master | sonnet | medium |
| creative-director | creative_director | sonnet | low |
| legal-compliance | legal_counsel | sonnet | low |
| project-manager | project_manager | haiku | low |

Solo backend, frontend y chief-architect editan archivos. QA ejecuta comandos de verificación pero no edita. **Gobernanza:** `claim_task` antes de editar; la entrega va con evidencia (`submit_for_review`); nadie aprueba su propio trabajo; solo QA (`review_task`) o el humano desde la web pasan una historia a `completada`.

**Cambiar modelo o esfuerzo:**

- Para una tarea concreta: pídelo ("delega en backend con opus"); Claude pasa `model`/`effort` a esa invocación.
- Para todos los subagentes: `CLAUDE_CODE_SUBAGENT_MODEL` (con `CLAUDE_CODE_SUBAGENT_MODEL_FORCE=1` se impone al frontmatter) y `CLAUDE_CODE_EFFORT_LEVEL`.
- De forma permanente: en un clon local, edita `model:` y `effort:` en `plugin/agileforge/agents/<agente>.md`, instala desde ese marketplace local y ejecuta `/reload-plugins`. No edites la caché de plugins: se sobrescribe al actualizar.

## Avisos

- En modo plugin, todo el trabajo de los agentes consume **tu plan o tu cuota de Claude Code**. Opus/high es lo más caro; la tabla de arriba lo reserva para decidir y diseñar.
- AgileForge es un proyecto independiente (MIT), **sin afiliación con Anthropic**.
- El rol Legal & Compliance es orientativo y **no es asesoría legal profesional**.
