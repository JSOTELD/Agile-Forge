---
description: Estado del sprint activo de AgileForge (meta, avance, columnas, quién trabaja en qué, bloqueos).
argument-hint: "[id-proyecto]"
allowed-tools: mcp__plugin_agileforge_board__get_overview, mcp__plugin_agileforge_board__get_sprint_status, mcp__plugin_agileforge_board__list_tasks, mcp__plugin_agileforge_board__get_task, mcp__plugin_agileforge_board__list_meetings, mcp__plugin_agileforge_board__get_meeting, mcp__plugin_agileforge_board__list_decisions, mcp__plugin_agileforge_board__get_activity, mcp__plugin_agileforge_board__get_role_brief, mcp__plugin_agileforge_board__connection_info
---

Muestra el estado del sprint de AgileForge. Proyecto: `$ARGUMENTS` (si está vacío, el proyecto activo).

1. Llama una sola vez a `get_overview` (con `project` solo si se indicó arriba).
2. Si falla, muestra el mensaje de error tal cual y sugiere `connection_info` y el README del plugin. No inventes datos.
3. Responde en ≤12 líneas:
   - **Sprint N · nombre** — meta en una línea; días restantes.
   - **Avance**: puntos hechos/total (progress_pct %) y velocidad.
   - **Columnas**: backlog · en progreso · revisión · completada (cantidad y puntos).
   - **En curso**: avatar rol → historia (desde cuándo), una línea cada uno.
   - **Atención**: historias en revisión esperando a QA, locks antiguos (>24 h) o prioridad crítica sin dueño.
