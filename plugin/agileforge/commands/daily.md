---
name: daily
description: Genera el resumen diario del Scrum Standup con el progreso por agente, historias en curso y bloqueos.
---

# Comando: /daily

Genera el reporte ejecutivo del Daily Standup del equipo:

1. **Procedimiento**:
   - Consulta el estado global con `get_sprint_status`.
   - Consulta historias en progreso y revisión con `list_tasks`.
   - Genera para cada agente activo:
     - 🚀 **En qué está trabajando**: Historias con lock en `en_progreso`.
     - 🔍 **Esperando revisión**: Entregables en `revision`.
     - ⚠️ **Impedimentos / Bloqueos**: Tareas retenidas o dependencias pendientes.
   - Concluye con el avance numérico del sprint hacia la meta.

