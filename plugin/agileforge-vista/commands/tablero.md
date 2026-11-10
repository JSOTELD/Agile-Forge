---
description: Tablero AgileForge por columnas (backlog, en progreso, revisión, completada) con dueño y puntos.
argument-hint: "[estado o rol]"
allowed-tools: mcp__plugin_agileforge-vista_board__get_overview, mcp__plugin_agileforge-vista_board__list_tasks, mcp__plugin_agileforge-vista_board__connection_info
---

Filtro opcional: `$ARGUMENTS` (un estado como `revision`, o un rol como `backend_architect`). Solo lectura.

1. `get_overview` (usa su `tasks`); `list_tasks` solo si el filtro lo requiere.
2. Por columna, en este orden: backlog · en_progreso · revision · completada. Cabecera: "Columna (n · pts)". Debajo, una línea por historia: `ID` título (≤60 car.) · avatar rol · pts · prioridad · 🔒 si tiene lock.
3. Máx. 10 historias por columna (completada: las 5 más recientes); indica "+N más".
