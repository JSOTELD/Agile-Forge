---
description: Pide a QA (qa-security) que audite la Definition of Done de una historia en revisión y dictamine approve o request_changes.
argument-hint: "<US-id>"
---

Historia a auditar: `$ARGUMENTS`

1. Sin ID → pide el ID y para.
2. `mcp__plugin_agileforge_board__get_task`. Si no está en `revision`, informa el estado actual y para.
3. Delega con la herramienta Agent en `agileforge:qa-security` (sin pasar `model` ni `effort`): "Audita <US-id> contra su DoD, reproduce la evidencia con comandos de solo verificación y dictamina con review_task".
4. Tú no llamas a `review_task` ni marcas `completada`: solo QA dictamina, o el humano desde la web.
5. Muestra el dictamen de QA: decisión, checklist (OK/falla) y cambios pedidos, en ≤10 líneas.
