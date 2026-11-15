# Roles en archivos (RFC-001, fase 1)

Cada rol del tablero se define en un archivo Markdown con frontmatter. El código decide por **capacidades**, no por el id del rol.

- `core/` — los 8 roles del tablero. Sus ids no cambian (`lead_architect`, `qa_security_guard`, …): las historias existentes siguen asignadas a ellos.
- `<tu proyecto>/.agileforge/roles/<id>.md` — roles propios de un proyecto. Un archivo con el mismo id que uno del core lo sustituye **solo en ese proyecto**.

## Capacidades (lista cerrada)

| Capacidad | Qué permite |
|---|---|
| `can_claim_tasks` | Tomar historias (`claim_task`). |
| `can_submit_evidence` | Entregar a revisión (`submit_for_review`). |
| `can_review_tasks` | Dictaminar una historia en revisión (aprobar o pedir cambios). |
| `can_approve_dod` | Aprobar (además de `can_review_tasks`). |
| `can_record_adr` | Registrar decisiones de arquitectura. |
| `can_facilitate_sprint` | Facilitar el sprint; recibe las historias sin asignar. |
| `can_forge_agents` | Forjar agentes (fase 2). |
| `can_audit_compliance` | Auditorías legales y de cumplimiento. |
| `can_orchestrate` | Orquestar el equipo (va primero en el panel). |

Un rol que existe en la base pero no tiene archivo conserva lo de siempre: `can_claim_tasks` y `can_submit_evidence`.

## Ejemplo de rol del proyecto

```yaml
---
id: auditor_pci                 # snake_case, igual que el nombre del archivo
name: "Auditor PCI-DSS"
description: "Seguridad de pagos con tarjeta."
model: sonnet                   # sonnet | opus | haiku | fable | inherit | claude-…
effort: high                    # low | medium | high | xhigh | max
color: red
capabilities: [can_claim_tasks, can_submit_evidence, can_audit_compliance]
tools: [Read, Grep, board__get_activity]   # board__<herramienta>: el generador pone el prefijo MCP
skills: [security-hardening, compliance-sentinel]
---
# Auditor PCI
Lo que el agente debe saber de su misión.
```

## Comandos

```bash
php bin/roles.php list                 # roles y capacidades del proyecto activo
php bin/roles.php validate             # validación fija; sale con 1 si hay errores
php bin/roles.php agents [--dry-run]   # genera .claude/agents/<id>.md para los roles del proyecto
```

`--workspace=CARPETA` usa otra carpeta de proyecto. El generador escribe siempre una lista explícita de herramientas (sin ella Claude Code daría todas al subagente), prohíbe `Bash`, `Edit` y `Write` si el rol no los declara, y no sobrescribe archivos de `.claude/agents` que no generó él.

Todavía no hay alta de roles nuevos en el tablero desde el panel: eso llega con la Armería (fase 3).
