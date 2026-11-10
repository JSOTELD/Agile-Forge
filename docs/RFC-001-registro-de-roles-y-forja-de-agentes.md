# RFC-001: Registro de Roles en Archivos y Forja de Agentes

- **Estado:** Propuesta Aprobada (Diseño - Fase B)
- **Fecha:** 2026-11-09
- **Autores:** David & Equipo de Arquitectura AgileForge
- **Referencia:** ADR-003, ADR-004, ADR-005, RFC-002

---

## 1. Contexto y Motivación

Actualmente, AgileForge cuenta con un escuadrón predeterminado de roles (`director`, `lead_architect`, `backend_architect`, `frontend_master`, `qa_security_guard`, `creative_director`, `legal_counsel`, `project_manager`). Aunque este equipo cubre el flujo Scrum inicial, adolece de dos limitaciones arquitectónicas:

1. **Acoplamiento a identificadores fijos en el código:** La lógica de negocio (`TaskController`, `DirectorService`, `BoardService`, reglas de revisión de la DoD) comprueba identificadores específicos como `qa_security_guard` o `director` en lugar de evaluar **capacidades funcionales declaradas**.
2. **Inflexibilidad para dominios especializados:** Un proyecto de ciberseguridad requiere auditores PCI-DSS o ingenieros de pentesting; una fintech requiere oficiales de cumplimiento bancario; un proyecto de IA requiere ingenieros de evaluación de modelos o curadores de datasets.

La **Forja de Agentes** introduce un ciclo riguroso de diseño, prueba y activación de agentes autónomos, fundamentado en un registro canónico de roles en archivos y un modelo de permisos desacoplado por capacidades.

---

## 2. Registro Único de Roles en Archivos

Antes de permitir la creación dinámica de agentes, la definición de roles debe desacoplarse del motor de base de datos y del lenguaje de backend (sea PHP o Node.js). La Única Fuente de Verdad reside en archivos Markdown con Frontmatter YAML versionados en Git.

### 2.1 Definición Declarativa del Rol (`roles/<id>.md`)

Cada rol se define en el directorio de proyecto o plugin (`roles/<id>.md` o `.agileforge/roles/<id>.md`):

```yaml
---
id: sec-auditor-pci
name: "Auditor de Cumplimiento PCI-DSS"
alias: "The Vault Shield"
avatar: "🛡️"
description: "Especialista en seguridad de transacciones bancarias, tokenización y cumplimiento PCI-DSS v4.0."
specialty: "Ciberseguridad bancaria, OWASP API Top 10, cifrado AES-256 y mitigación de fugas de datos."
model: sonnet              # sonnet | opus | haiku
effort: high               # low | medium | high
color: red                 # red | green | blue | purple | yellow | cyan | orange | pink
capabilities:
  - can_claim_tasks
  - can_submit_evidence
  - can_audit_compliance
  # NOTA: No posee 'can_approve_dod', por lo que no puede dictaminar historias a completada
tools:
  - Read
  - Grep
  - Glob
  - mcp__plugin_agileforge_board__list_tasks
  - mcp__plugin_agileforge_board__get_task
  - mcp__plugin_agileforge_board__get_role_brief
  - mcp__plugin_agileforge_board__add_comment
  - mcp__plugin_agileforge_board__list_memories
  - mcp__plugin_agileforge_board__get_memory
disallowedTools:
  - Edit
  - Write
  - Bash
skills:
  - security-hardening
  - compliance-sentinel
  - clean-code
---

# Auditor de Cumplimiento PCI-DSS · `sec-auditor-pci`

## Misión
Auditar payloads, esquemas de bases de datos y flujos de pago para garantizar que ningún dato sensible de tarjeta (PAN, CVV, PIN) viaje en texto claro ni persista en registros o tablas sin encriptar.
```

### 2.2 Generación del Subagente para Claude Code (`.claude/agents/<id>.md`)

Cuando un rol se activa en un proyecto, AgileForge sintetiza automáticamente el subagente correspondiente en el workspace local (`.claude/agents/<id>.md`), aplicando las siguientes reglas de traducción deterministas:

1. **Mapeo de Modelo (`model`):**
   - `opus`: Tareas de alta complejidad arquitectónica, dirección ejecutiva o razonamiento profundo.
   - `sonnet`: Desarrollo de software, auditoría de código, pentesting y revisión técnica.
   - `haiku`: Facilitación ágil, categorización rápida, resumen de eventos o tareas ligeras.
2. **Nivel de Esfuerzo (`effort`):** Se traslada directamente (`low`, `medium`, `high`) para gobernar el presupuesto de razonamiento (extended thinking) en Claude Code.
3. **Lista Blanca de Herramientas (`tools`):**
   - **Herramientas Nativas de Claude Code:** Solo se autorizan las explícitamente declaradas en la whitelist (`Read`, `Grep`, `Glob`, `Edit`, `Write`, `Bash`).
   - **Herramientas MCP:** Solo herramientas con el prefijo `mcp__plugin_agileforge_board__*` declaradas en el rol.
   - **Herramientas Excluidas (`disallowedTools`):** Cualquier herramienta restringida se inyecta en el bloque `disallowedTools` de Claude Code para forzar su denegación por el runtime.
4. **Skills Asignadas:** Se contrastan contra el catálogo canónico (`plugin/agileforge/skills/`); solo las skills existentes y validadas se adjuntan en el frontmatter.

---

## 3. Seguridad de los Packs y Principio de Menor Privilegio

La importación o forja de packs de agentes expone el entorno de desarrollo a riesgos de inyección de prompts y ejecución no autorizada. Se establecen tres salvaguardas estructurales innegociables:

1. **Sin Bash ni Escritura por Defecto:**
   - Por defecto, ningún agente nuevo recibe permisos de ejecución de comandos (`Bash`) ni de modificación de disco (`Edit`, `Write`).
   - Los roles nacen con perfil de solo lectura (`Read`, `Grep`, `Glob` y herramientas MCP de consulta).
   - La inclusión de `Bash`, `Edit` o `Write` requiere declaración explícita en sus capacidades (`can_execute_scripts`, `can_modify_code`) y advertencia visible.
2. **Lista Blanca Estricta y Validación de Herramientas:**
   - El sistema rechaza cualquier herramienta desconocida o que intente eludir el namespace de AgileForge.
   - Toda herramienta declarada debe existir en el manifiesto del servidor MCP o en el estándar nativo de Claude Code.
3. **Aviso Obligatorio para Packs de Terceros:**
   - Al importar un pack desde un repositorio externo o aleación no certificada, el panel web y el comando CLI muestran un diálogo de advertencia de seguridad con el inventario de herramientas solicitadas (especialmente si incluye `Bash` o acceso a red).
   - Requiere confirmación humana explícita antes de escribir los archivos de rol en el proyecto.

---

## 4. Desacoplamiento por Capacidades (Capabilities)

Se reemplaza el acoplamiento a nombres fijos (`role_id === 'director'`) por la evaluación de capacidades funcionales:

| Capacidad | Descripción | Roles del pack Core |
|---|---|---|
| `can_claim_tasks` | Capacidad de reclamar historias (`claim_task`) y establecer lock de trabajo. | `backend_architect`, `frontend_master`, `chief_architect` |
| `can_submit_evidence` | Capacidad de enviar entregas técnicas a revisión con comandos y archivos. | `backend_architect`, `frontend_master`, `chief_architect` |
| `can_review_tasks` | Capacidad de auditar y dictaminar historias en estado `revision`. | `qa_security` (único con voto técnico en core) |
| `can_approve_dod` | Autoridad para declarar cumplida la DoD y transicionar a `completada`. | `qa_security`, Humano (web) |
| `can_record_adr` | Capacidad de asentar decisiones arquitectónicas en el libro de actas. | `director`, `chief_architect` |
| `can_facilitate_sprint` | Capacidad de iniciar sprints, controlar cadencia y facilitar dailies. | `director`, `project_manager` |
| `can_forge_agents` | Capacidad de forjar, templar y dar de alta agentes en la Armería. | `director`, `chief_architect` |

---

## 5. Inventario de Código Acoplado a IDs Fijos y Plan de Migración

### 5.1 Inventario de Puntos Actuales

| Archivo | Función / Método | ID Hardcodeado | Refactorización requerida |
|---|---|---|---|
| `api/controllers/TaskController.php` | `review()` | `'qa_security'`, `'qa_security_guard'` | Reemplazar por verificación: `roleHasCapability($roleId, 'can_review_tasks')`. |
| `api/controllers/TaskController.php` | `updateStatus()` | `'qa_security'` para mover a `completada` | Reemplazar por verificación: `roleHasCapability($roleId, 'can_approve_dod')`. |
| `api/controllers/TaskController.php` | `consolidateTaskCompletionMemory()` | Tarea completada | Desacoplado: ya usa `MEM-TASK-<id>` y el `assigned_to` de la tarea. |
| `api/services/BoardService.php` | `claimTask()` | Comprobación de asignación | Permitir cualquier rol con `can_claim_tasks`. |
| `api/services/DirectorService.php` | `const ROLE_ID = 'director'` | `'director'` | Mantener como fallback del rol ejecutivo principal, respaldado por `can_orchestrate`. |
| `api/controllers/MeetingController.php` | `beginMeeting()`, `runMeeting()` | Roles hardcodeados de asistentes | Invitar por capacidades (`can_contribute_architecture`, `can_audit_compliance`). |
| `api/Database.php` | `repairBoardData()` | Migración de IDs antiguos | Preservar mapeo histórico en la migración de datos. |
| `assets/js/chat.js` | `handleSendChat()` | `targetRole === 'director'` | Comprobar flag de capacidades en `role.capabilities.includes('orchestrator')`. |
| `assets/js/app.js` | Modales y badges de rol | `'director'`, `'qa_security'` | Consultar capacidades dinámicas en `appData.roles`. |

### 5.2 Migración del Escuadrón Actual al Pack "Core"

Para garantizar compatibilidad hacia atrás con las historias y auditorías existentes:
1. Se crea el pack canónico `plugin/agileforge/packs/core/` con los 8 roles vigentes (`director`, `chief_architect`, `backend_architect`, `frontend_master`, `qa_security`, `creative_director`, `legal_compliance`, `project_manager`).
2. Cada archivo de rol declara sus capacidades exactas.
3. Las tareas históricas mantienen su campo `assigned_to = 'backend_architect'`. El sistema resuelve las capacidades leyendo el archivo de rol correspondiente. Si una historia referencia un ID sin prefijo de pack, el resolver busca por defecto en el pack `core`.

---

## 6. El Ciclo de la Forja de Agentes

El ciclo de vida de un agente consta de cuatro etapas estrictas:

```mermaid
flowchart TD
    subgraph F1["1. Forjar"]
      A["Definición YAML / Markdown<br/>(/forjar o Editor Web)"]
    end

    subgraph F2["2. Templar (Sandbox Aislado)"]
      B["Nivel 1: Validación Fija Obligatoria<br/>(Esquema, herramientas, skills, secretos)"]
      B -->|Pasa| C{"¿Ejecutar Práctica con IA?<br/>(Nivel 2 - Opcional)"}
      C -->|Sí (Acepta costo tokens)| D["Prueba en Sandbox Aislado<br/>(data/sandbox.sqlite + workspace efímero)"]
      C -->|No / Bypass Humano| E["Insignia: Sin Templar ⚠️<br/>(Registra evento de auditoría)"]
      D -->|Supera DoD sintética| F["Certificado de Temple ✓"]
    end

    subgraph F3["3. Armería"]
      F --> G["Agente Activo en el Proyecto"]
      E --> G
    end

    subgraph F4["4. Aleaciones"]
      G -.-> H["Exportación en Pack Reutilizable"]
    end
```

### 6.1 Fase 1: Forjar (Creación)
- **Modo Plugin (Claude Code / MCP):** Comando `/forjar <descripción>` o herramienta MCP `forge_agent`.
- **Modo Web:** Formulario visual con autocompletado de skills, selección de modelo y checkboxes de capacidades.

### 6.2 Fase 2: Templar en Dos Niveles (Sandbox Aislado)

> [!IMPORTANT]
> **Decisión de Arquitectura (David):**
> El templado se ejecuta **en disco pero estrictamente aislado**, utilizando un proyecto temporal y una base de datos efímera (`data/sandbox.sqlite` + directorio efímero ignorado por Git). **Se rechazan los mocks en memoria** para asegurar que el agente interactúa con SQLite y el sistema de archivos real sin contaminar el tablero de producción.

El proceso de templado se divide en dos niveles claros:

#### Nivel 1: Validación Fija Obligatoria (Determinista y Gratuita)
Se ejecuta de forma inmediata en milisegundos sin llamadas a LLM:
- [x] **Formato y Esquema:** YAML frontmatter válido, campos requeridos presentes (`id`, `name`, `model`, `capabilities`, `tools`).
- [x] **Modelo Válido:** El modelo declarado pertenece a los proveedores soportados en `config/models.json`.
- [x] **Herramientas en Whitelist:** Todas las herramientas declaradas existen en la lista blanca y no violan restricciones de seguridad.
- [x] **Skills en Catálogo:** Toda skill referenciada existe en `plugin/agileforge/skills/`.
- [x] **Detección de Secretos:** Escaneo estático de texto para prevenir claves de API, tokens o credenciales incrustadas en el prompt.

#### Nivel 2: Práctica con IA (Opcional, con Aviso de Costo)
Permite someter al agente a una historia de prueba sintética antes de permitirle operar en el proyecto:
- **Aviso de Tokens:** La interfaz advierte el costo estimado en tokens (ej. `~3,500 tokens / ~$0.015 USD`).
- **Aislamiento Total:** Se instancia una base temporal `data/sandbox.sqlite` y una carpeta de trabajo efímera `.agileforge/sandbox/temp-<uuid>/` (registrada en `.gitignore`).
- **Desafío Sintético:**
  1. Se asigna una historia modelo acorde a la especialidad del agente.
  2. El agente reclama la tarea con `claim_task`.
  3. Ejecuta la tarea y entrega evidencia con `submit_for_review`.
  4. Se valida que no haya invocado capacidades ajenas y que haya emitido evidencia real.
- **Limpieza Inmediata:** Al terminar la prueba (sea exitosa o fallida), el directorio temporal y la base `sandbox.sqlite` se eliminan por completo.

### 6.3 Bypass del Templado (Condiciones Restrictivas)

> [!CAUTION]
> El bypass del templado está severamente restringido:
> 1. **Solo Humano desde la Web:** Solo puede autorizarse desde el panel web (`client=web`). **Nunca por MCP ni por llamadas automatizadas de agentes.**
> 2. **Solo para el Nivel 2:** El Nivel 1 (validación fija) **NUNCA** puede ignorarse.
> 3. **Advertencia Explícita:** Requiere confirmar un modal de advertencia de riesgo de ejecución.
> 4. **Registro de Auditoría:** Se genera un evento inmutable `role_forged_untested` en el feed de auditoría.
> 5. **Insignia Visible:** El agente luce permanentemente la etiqueta `⚠️ Sin templar` en su avatar, en el tablero de equipo y en la cabecera de su brief.

---

## 7. Armería y Límites Operativos por Proyecto

La **Armería** gestiona el catálogo de agentes disponibles para el proyecto activo.

### 7.1 Límite de Agentes Activos
Para evitar sobrecarga de contexto, dispersión en reuniones y contención de bloqueos en SQLite:
- **Límite Máximo Recomendado:** **8 a 10 agentes activos por proyecto simultáneamente**.
- Los roles excedentes permanecen en estado "En Reserva" dentro de la Armería (inactivos en el tablero y no invocables por Claude Code).
- El Líder de Producto puede rotar especialistas según las necesidades del sprint activo (ej. activar al Auditor PCI durante el sprint de pasarela de pagos y regresarlo a la reserva al concluir).

---

## 8. Estimación de Esfuerzo por Fase

| Fase | Entregables Principales | Complejidad | Estimación |
|---|---|---|---|
| **Fase 1: Registro de Roles en Archivos** | Especificación del esquema YAML, refactor de `TaskController` / `BoardService` para verificar capacidades, soporte de `.claude/agents/<id>.md`. | Media | 4-5 días |
| **Fase 2: Forja y Templado con Sandbox** | Validador determinista (Nivel 1), runner de sandbox aislado en `data/sandbox.sqlite` (Nivel 2), bypass web con auditoría. | Alta | 5-6 días |
| **Fase 3: Armería, Web UI y Aleaciones** | Gestor visual de la Armería en panel web, límite de escuadrón activo, importador/exportador de packs de agentes. | Media | 4 días |
| **Total Estimado** | | | **13-15 días** |
