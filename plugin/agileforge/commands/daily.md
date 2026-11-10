---
description: Daily standup de AgileForge a partir del tablero (qué se hizo desde ayer, quién hace qué, revisión, bloqueos).
argument-hint: "[id-proyecto]"
allowed-tools: mcp__plugin_agileforge_board__get_overview, mcp__plugin_agileforge_board__get_sprint_status, mcp__plugin_agileforge_board__list_tasks, mcp__plugin_agileforge_board__get_task, mcp__plugin_agileforge_board__list_meetings, mcp__plugin_agileforge_board__get_meeting, mcp__plugin_agileforge_board__list_decisions, mcp__plugin_agileforge_board__get_activity, mcp__plugin_agileforge_board__get_role_brief, mcp__plugin_agileforge_board__connection_info
---

Prepara el daily del proyecto `$ARGUMENTS` (vacío = proyecto activo).

1. Una llamada a `get_overview`. Usa `get_activity` solo si `since_yesterday` no basta para explicar lo ocurrido.
2. Formato (≤15 líneas, sin relleno):
   - **Desde ayer**: completadas, enviadas a revisión, decisiones y reuniones (de `since_yesterday`).
   - **Por rol** (avatar nombre): en qué está (historia y desde cuándo) · qué espera revisión.
   - **Bloqueos**: locks de más de 24 h, historias en revisión sin dictamen, críticas sin dueño.
   - **Sprint**: avance % y días restantes; una recomendación para hoy.
3. No modifiques el tablero. Si quieren dejar acta, sugiere `/agileforge:reunion daily <tema>`.
