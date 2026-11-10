---
description: Muestra el equipo de agentes de AgileForge con su modelo, esfuerzo y especialidad.
argument-hint: "[agente]"
---

Muestra esta tabla tal cual (si `$ARGUMENTS` nombra un agente, solo su fila y su descripción). No llames a herramientas.

| agente | role_key | modelo | esfuerzo | especialidad |
|---|---|---|---|---|
| director | — | opus | high | Decide, prioriza y delega (`claude --agent agileforge:director` o `/agileforge:director`) |
| chief-architect | lead_architect | opus | high | Arquitectura, dependencias, refactors, ADR técnicos |
| qa-security | qa_security_guard | sonnet | high | Audita la DoD y dictamina; único que aprueba |
| backend | backend_architect | sonnet | medium | API REST, SQL/PDO, seguridad del servidor |
| frontend | frontend_master | sonnet | medium | UI, responsive, accesibilidad |
| creative-director | creative_director | sonnet | low | Ideas de producto y dirección visual |
| legal-compliance | legal_counsel | sonnet | low | Privacidad y términos (orientativo, no asesoría profesional) |
| project-manager | project_manager | haiku | low | Sprint, daily, historias, reuniones |

Añade una línea: para cambiar modelo o esfuerzo, ver la sección "Agentes" del README del plugin (por delegación, variables `CLAUDE_CODE_SUBAGENT_MODEL` / `CLAUDE_CODE_EFFORT_LEVEL`, o editando el frontmatter en un clon local).
