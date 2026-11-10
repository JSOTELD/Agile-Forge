---
name: agile-project-manager
description: Úsala cuando debas planificar un sprint, dividir historias, mantener el tablero al día o dirigir una reunión Scrum.
title: "Gestión ágil de proyecto"
category: agile
level: Principal
status: active
tags: ["AgileForge","agile"]
---

# Gestión ágil de proyecto

## Cuándo usarla
Planificación de sprint, refinamiento del backlog, seguimiento diario, revisión y retrospectiva.

## Pasos
1. Empieza por el estado real: `get_overview` (MCP) o `ver_tablero` (web). No planifiques de memoria.
2. Historia bien formada: "Como <rol> quiero <acción> para <valor>", 3-5 criterios de aceptación verificables, estimación y prioridad. Si no cabe en ~1 día de trabajo de un agente, divídela (INVEST).
3. Planificación: capacidad realista, meta de sprint en una frase, historias ordenadas por valor y dependencias; deja margen del 20%.
4. Seguimiento: una historia `en_progreso` por agente; bloqueos visibles con `add_comment`; sin historias en `revision` más de un turno.
5. Revisión: los criterios se comprueban con evidencia, no con opinión; `completada` solo según gobernanza (revisión aprobada por QA o humano en la web).
6. Retrospectiva: 3 puntos (qué mantener, qué cambiar, una acción concreta con responsable) y se registra como decisión.
7. Reuniones cortas: agenda de 3 puntos, un turno por agente, cierre con acuerdos y tareas.
8. Informes de estado ≤ 150 palabras: avance, riesgos, siguiente paso.

## Anti-patrones
- Historias de "hacer todo el módulo".
- Tablero desactualizado respecto del trabajo real.
- Cambiar el alcance del sprint sin registrar la decisión.
- Marcar completada sin evidencia.

## Cómo verificar
- Cada historia nueva muestra criterios de aceptación y estimación.
- Pega el resumen del tablero antes y después (conteos por estado).
- Decisiones y acuerdos de reunión con su ID. Adjunta en `submit_for_review`.
