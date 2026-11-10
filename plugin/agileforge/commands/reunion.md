---
description: Celebra una reunión entre los agentes de AgileForge (brainstorming, decision, compliance o daily) y deja el acta en el tablero.
argument-hint: "<brainstorming|decision|compliance|daily> <tema>"
---

Reunión pedida: `$ARGUMENTS`

Tú (hilo principal) moderas y escribes el acta; las posiciones las aportan los subagentes. No hace falta motor de IA en la web.

1. Primer término = tipo (`brainstorming`, `decision`, `compliance`, `daily`; si falta, `decision`); el resto = tema. Sin tema → pídelo y para.
2. Participantes (máx. 4) según el tipo, salvo que el usuario los nombre:
   - brainstorming: creative-director, frontend, chief-architect, project-manager
   - decision: chief-architect, backend, qa-security, project-manager
   - compliance: legal-compliance, qa-security, backend
   - daily: project-manager y los roles con trabajo en curso (`get_overview`)
3. `mcp__plugin_agileforge_board__start_meeting` con `meeting_type`, `topic` y `participants` (role_key: lead_architect, backend_architect, frontend_master, qa_security_guard, creative_director, legal_counsel, project_manager).
4. Ronda única: consulta a cada participante con la herramienta Agent en `agileforge:<agente>` (independientes → en paralelo). Prompt: tema, contexto mínimo y "responde en ≤120 palabras: posición, riesgo principal, propuesta concreta; no modifiques archivos ni el tablero".
5. Registra cada respuesta con `mcp__plugin_agileforge_board__add_meeting_turn` (`role_id`, `name`, `avatar`, `statement` resumido ≤280 caracteres, `ai_model` = modelo del agente). Si hay desacuerdo fuerte, una segunda ronda solo entre los implicados.
6. `mcp__plugin_agileforge_board__close_meeting` con `summary`, `resolution` y `action_items` (título, `assigned_to` = role_key, puntos, prioridad).
7. Tipo `decision` con acuerdo de arquitectura o alcance → `mcp__plugin_agileforge_board__record_decision`.
8. Responde: ID del acta (MEET-…), resolución en 2 líneas, action items y ADR creados.
