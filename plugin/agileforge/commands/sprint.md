---
name: sprint
description: Muestra el estado en vivo del sprint activo, objetivo, velocidad, puntos por columna y bloqueos de agentes.
---

# Comando: /sprint

Ejecuta la herramienta MCP `get_sprint_status` de AgileForge y presenta un resumen visual ejecutivo del Sprint activo:

1. **Sprint**: Número y Nombre oficial del Sprint.
2. **Meta del Sprint**: Objetivo acordado por el equipo.
3. **Métricas Clave**:
   - Días restantes
   - Velocidad total y Puntos completados
   - Porcentaje de avance hacia la Definition of Done (DoD)
4. **Desglose de Historias por Columna**:
   - Backlog (pts y cantidad)
   - En Progreso (pts y agentes trabajando con sus locks)
   - En Revisión (pts esperando auditoría de QA)
   - Completadas (pts finalizados con DoD certificada)
5. **Alertas & Bloqueos**: Indicar si alguna tarea tiene un lock activo o impedimento reportado.

