---
name: dod
description: Audita exhaustivamente la Definition of Done y la evidencia técnica de una historia en revisión antes de su aprobación.
---

# Comando: /dod <US-id>

Ejecuta una auditoría estricta de Definition of Done (DoD) sobre una historia de usuario:

1. **Parámetro**:
   - `<US-id>`: Identificador de la historia de usuario (ej: `US-102`).
2. **Procedimiento**:
   - Invoca `get_task({ task_id: "<US-id>" })` para inspeccionar la evidencia técnica entregada:
     - Archivos modificados o creados (`files_changed`).
     - Resultados de pruebas ejecutadas (`tests_run`).
     - Resumen técnico del desarrollador (`summary`).
   - Verifica los 4 pilares estándar de DoD:
     1. Cero errores de sintaxis y linter.
     2. Pruebas unitarias/integración en verde y sin regresiones.
     3. Código limpio, anti-slop y conforme a Clean Architecture.
     4. Cumplimiento con la normativa legal de la jurisdicción activa.
   - Si todo es conforme, permite al rol QA dictaminar `review_task({ task_id: "<US-id>", decision: "approve" })`.

