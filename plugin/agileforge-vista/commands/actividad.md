---
description: Actividad reciente del tablero AgileForge (eventos de historias, revisiones, reuniones y decisiones).
argument-hint: "[cantidad, 1-100]"
allowed-tools: mcp__plugin_agileforge-vista_board__get_activity, mcp__plugin_agileforge-vista_board__connection_info
---

Cantidad: `$ARGUMENTS` (vacío = 30). Solo lectura.

`get_activity` con ese `limit`. Agrupa por día (hoy, ayer, fecha) y muestra una línea por evento: hora · actor · mensaje (≤100 caracteres). Cierra con una línea de totales por tipo.
