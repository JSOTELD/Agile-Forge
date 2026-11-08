---
name: reunion
description: Convoca y lidera una sesión formal de deliberación entre agentes (Brainstorming, Consejo ADR, Compliance o Daily).
---

# Comando: /reunion <tipo> <tema>

Convoca y modera una reunión multi-agente en AgileForge:

1. **Parámetros**:
   - `<tipo>`: Tipo de sala (`brainstorming`, `decision`, `compliance`, `daily`).
   - `<tema>`: Tópico o desafío a debatir.
2. **Procedimiento**:
   - Inicia la sesión mediante `start_meeting`.
   - Modera los turnos de los agentes participantes (`add_meeting_turn`), aportando cada uno desde su especialidad (Diseño, Arquitectura, Backend, QA o Legal).
   - Sintetiza los acuerdos y cierra la reunión mediante `close_meeting` generando el acta oficial con compromisos derivados (`action_items`).
   - Muestra el identificador del acta (ej: `MEET-003`) y recuerda que los action items pueden convertirse en historias de backlog desde el panel web.

