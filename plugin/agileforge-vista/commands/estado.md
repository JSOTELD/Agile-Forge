---
description: Resumen de 5 líneas del tablero AgileForge (avance y desde ayer, quién hace qué, reuniones, decisiones).
argument-hint: "[id-proyecto]"
allowed-tools: mcp__plugin_agileforge-vista_board__get_overview, mcp__plugin_agileforge-vista_board__connection_info
---

Estado del proyecto `$ARGUMENTS` (vacío = proyecto activo). Solo lectura.

Llama una vez a `get_overview` y responde exactamente con estas 5 líneas, sin encabezados ni texto extra:

1. **Avance**: Sprint N · progress_pct % (hechos/total pts) · días restantes · desde ayer: +X completadas, Y a revisión.
2. **Haciendo**: avatar rol → historia (máx. 3; "+N más" si hay más; "nadie" si `working` está vacío).
3. **En revisión / bloqueos**: historias en revisión y locks de más de 24 h (o "ninguno").
4. **Reuniones**: la última (ID, título, resolución en ≤15 palabras) y cuántas en 24 h.
5. **Decisiones**: la última (ID, título) y cuántas en 24 h.

Si falla, una línea con el error y "ejecuta connection_info o revisa el README (URL y AGILEFORGE_VIEW_TOKEN)".
