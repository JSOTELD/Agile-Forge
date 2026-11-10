---
description: Asigna una historia a un especialista de AgileForge, que la toma con lock (claim_task) y la desarrolla hasta enviarla a revisión.
argument-hint: "<US-id> [agente: backend|frontend|chief-architect]"
---

Historia y agente pedidos: `$ARGUMENTS`

1. Separa el ID (`US-…`) y el agente opcional. Sin ID → pide el ID y para.
2. `mcp__plugin_agileforge_board__get_task` con ese ID. Si está `completada`, o con lock de otro rol, informa y para.
3. Elige el agente: el indicado; si no, según `assigned_to` (`backend_architect`→`backend`, `frontend_master`→`frontend`, `lead_architect`→`chief-architect`). Otro rol (QA, legal, creativo, PM) → explica que esos roles no desarrollan y para.
4. Delega con la herramienta Agent en `agileforge:<agente>` (sin pasar `model` ni `effort`). Prompt: ID, título, DoD y "sigue tu flujo: claim_task antes de editar; si falla, no toques nada; termina con submit_for_review".
5. Resume su entrega en ≤8 líneas y sugiere `/agileforge:dod <US-id>` para el dictamen de QA.
