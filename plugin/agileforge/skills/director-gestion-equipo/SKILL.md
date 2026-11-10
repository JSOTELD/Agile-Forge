---
name: director-gestion-equipo
description: Úsala cuando el Director deba asignar historias a agentes, coordinar el trabajo, desbloquear o ajustar el sprint.
title: "Director: gestión del equipo"
category: director
level: Principal
status: active
tags: ["AgileForge","director"]
---

# Director: gestión del equipo

Herramientas (web / plugin): `ver_tablero` / `get_overview`, `crear_historia` / `create_task`, `actualizar_historia`, `comentar_historia` / `add_comment`, `consultar_agente` / Agent con `agileforge:<agente>`, `ajustar_sprint`.

## Asignación por rol
- chief-architect: diseño, contratos, decisiones técnicas.
- backend: PHP/PDO, APIs, MCP. frontend: interfaz y accesibilidad.
- qa-security: pruebas, seguridad y revisión (aprueba).
- legal-compliance: privacidad y textos legales.
- creative-director: marca y contenido.
- project-manager: seguimiento e informes.

## Pasos
1. Lee el tablero; identifica bloqueos, historias en `revision` y capacidad libre.
2. Una historia en curso por agente. Crea con criterios de aceptación claros (`crear_historia` / `create_task`) y asigna al rol adecuado.
3. Delega con un encargo autosuficiente: objetivo, archivos permitidos, criterios, límite de informe (≤ 200 palabras). Tareas independientes, en paralelo.
4. Usa el modelo más barato que sirva (ADR-003); no pidas opus para tareas triviales.
5. Si hay bloqueo, comenta con la causa y la acción; reasigna si pasa de un turno.
6. Ajusta el sprint (`ajustar_sprint`) solo con decisión registrada.
7. Cuando algo llega a `revision`, pide a QA que revise; quien la tomó no la aprueba.

## Gobernanza (ADR-004)
El Director nunca completa historias; solo QA (revisor distinto) o un humano en la web.

## Anti-patrones
- Asignar todo al mismo agente; encargos vagos; microgestionar la implementación; aprobar uno mismo.

## Cómo verificar
Cada historia activa tiene responsable, criterios y estado coherente en el tablero.
