---
description: Muestra el equipo de agentes de AgileForge con su modelo, esfuerzo y especialidad.
argument-hint: "[agente]"
---

Muestra esta tabla tal cual (si `$ARGUMENTS` nombra un agente, solo su fila y su descripción). No llames a herramientas.

| agente | role_key | modelo | esfuerzo | especialidad |
|---|---|---|---|---|
| director | — | gemini-4-argon | high | Decide, prioriza y delega (`agileforge:director` o `/agileforge:director`) |
| chief-architect | lead_architect | gemini-4-argon | high | Arquitectura, dependencias, refactors, ADR técnicos |
| qa-security | qa_security_guard | gemini-4-hydrogen | high | Audita la DoD y dictamina; único que aprueba |
| backend | backend_architect | gemini-4-hydrogen | medium | API REST, SQL/PDO, seguridad del servidor |
| frontend | frontend_master | gemini-4-hydrogen | medium | UI, responsive, accesibilidad |
| creative-director | creative_director | gemini-3.8-flash | medium | Ideas de producto y dirección visual |
| legal-compliance | legal_counsel | gemini-3.8-flash | low | Privacidad y términos (orientativo, no asesoría profesional) |
| project-manager | project_manager | gemini-3.8-flash | low | Sprint, daily, historias, reuniones |
| tester | — | gemini-4-argon | high | Tester (v0, experimental): encuentra fallos y los demuestra con pruebas en un entorno desechable; no arregla ni aprueba (`/agileforge:probar`) |
| graph-curator | — | gemini-4-hydrogen | medium | Curador de grafos (v0, experimental): con graphify mantiene el grafo que consultan los agentes y un vault de Obsidian como bibliografía; no edita código ni toca historias (`/agileforge:grafo`) |

Añade una línea: para cambiar modelo o esfuerzo, ver la sección "Agentes" del README del plugin (por delegación, variables `CLAUDE_CODE_SUBAGENT_MODEL` / `CLAUDE_CODE_EFFORT_LEVEL`, o editando el frontmatter en un clon local).
