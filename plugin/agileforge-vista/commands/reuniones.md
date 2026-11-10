---
description: Últimas reuniones de AgileForge con su resolución y últimos turnos, o el detalle de una reunión.
argument-hint: "[MEET-id]"
allowed-tools: mcp__plugin_agileforge-vista_board__list_meetings, mcp__plugin_agileforge-vista_board__get_meeting, mcp__plugin_agileforge-vista_board__connection_info
---

Reunión pedida: `$ARGUMENTS`. Solo lectura.

- Con ID (`MEET-…`): `get_meeting` y muestra tipo, tema, participantes, resolución, action items y los turnos resumidos (una línea por turno: avatar nombre: idea principal).
- Sin ID: `list_meetings` con `limit: 5` y `compact: true`; por reunión: ID · título · tipo · fecha · resolución (≤20 palabras) · nº de turnos.
