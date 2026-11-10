# Changelog

Todas las notas notables de cambios en este proyecto se documentarán en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y este proyecto se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Español

#### Añadido
- **Tester (v0, experimental):** agente `tester` (opus) y comando `/agileforge:probar [alcance]`. Encuentra fallos y los demuestra con pruebas ejecutables en un entorno desechable; no arregla código, no aprueba y no prioriza. Método: reglas de negocio primero; cuatro ataques por regla (actor, estado, dueño y concurrencia); entradas hostiles; perfiles de la receta más "usuario nuevo" y "sin credenciales"; revisión de errores 500, log y `git status` tras cada tanda; interfaz al final. Cada bug confirmado se registra con `create_task` como `[BUG][critica|alta|media|baja] …`. Sin herramientas para tomar, entregar, dictaminar ni mover historias. No es un rol del tablero ni forma parte del pack core.
- **Receta de pruebas por proyecto:** plantilla `plugin/agileforge/templates/testing.md` para `.agileforge/testing.md` (entorno desechable, datos de ejemplo, perfiles con credenciales de prueba, log, límite de peticiones, reinicio y apagado, y carpeta donde puede escribir el tester). Sin receta aprobada el tester no prueba: propone un borrador y se detiene hasta que David lo apruebe.
- **Receta del propio AgileForge** (`.agileforge/testing.md`, en borrador): `npm test` con base temporal y carpeta temporal propia, y un entorno exploratorio en un clon limpio con tokens y log propios. Perfiles: token principal, token de vista, sin token y usuario nuevo.

#### Cambiado
- **Director:** puede posponer un hallazgo del tester dejando el motivo con `add_comment`; no puede descartar ni cerrar hallazgos críticos o de seguridad (los decide David) y nunca borra historias de bug.

### English

#### Added
- **Tester (v0, experimental):** `tester` agent (opus) and `/agileforge:probar [scope]` command. It finds failures and proves them with executable tests in a disposable environment; it does not fix code, approve or prioritize. Method: business rules first; four attacks per rule (actor, state, owner and concurrency); hostile inputs; the recipe's profiles plus "new user" and "no credentials"; a check for 500 errors, the server log and `git status` after each round; the UI last. Each confirmed bug is logged with `create_task` as `[BUG][critica|alta|media|baja] …`. It has no tools to claim, submit, review or move stories. It is not a board role and is not part of the core pack.
- **Per-project test recipe:** template `plugin/agileforge/templates/testing.md` for `.agileforge/testing.md` (disposable environment, sample data, profiles with test credentials, log, rate limit, restart and shutdown, and the only folder the tester may write to). Without an approved recipe the tester does not test: it proposes a draft and stops until David approves it.
- **AgileForge's own recipe** (`.agileforge/testing.md`, draft): `npm test` with a temporary database and its own temporary folder, plus an exploratory environment in a clean clone with its own tokens and log. Profiles: main token, view token, no token and new user.

#### Changed
- **Director:** may postpone a tester finding, leaving the reason with `add_comment`; cannot dismiss or close critical or security findings (David decides them) and never deletes bug stories.

---

## [2.3.0] - 2026-11-10

### Español

#### Añadido
- **Revisión independiente y firma humana (`human_signoff`):**
  - Ajuste configurable por proyecto (encendido por defecto) en la tabla `settings` (`{ "default": true, "projects": { "<project_id>": true|false } }`).
  - Con firma activa, `review_task approve` de QA no pasa la historia a `completada`: permanece en `revision` con la marca `qa_approved` (revisor, fecha, checklist, verificación y duración) dentro de `review_evidence` y registra el evento `qa_approved`. Solo la interfaz web (`client=web`) mediante el botón de firma humana pasa la historia a `completada`.
  - Con firma inactiva, `review_task approve` de QA completa la historia directamente.
  - Consolidación de memoria `MEM-TASK` activada únicamente al alcanzar el estado `completada`.
- **Barreras automáticas de entrega y revisión:**
  - `submit_for_review`: valida obligatoriamente que `evidence.tests_run` contenga al menos 1 prueba ejecutada y que `summary` tenga un mínimo de 20 caracteres (HTTP 422).
  - `review_task approve`: exige bloque `verification` con `commands_run` (mínimo 1 comando) y `notes` (mínimo 30 caracteres) (HTTP 422).
  - Tiempo mínimo de revisión: `review_task approve` rechaza revisiones prematuras si han transcurrido menos de `AGILEFORGE_MIN_REVIEW_SECONDS` (por defecto 60 segundos; configurable vía entorno o `.env`) desde el último evento `submitted_for_review` (HTTP 422).
  - Medición y registro de `review_duration_seconds` en eventos y evidencia.
- **Panel Web y UI:**
  - Insignia destacada "Aprobada por QA · falta tu firma" en las tarjetas de historias aprobadas técnicamente.
  - Botón de acción rápida "Firmar y Completar" para que el usuario registre la firma humana desde el tablero Kanban.
  - Insignia "Revisión rápida" si la revisión técnica duró menos de 300 segundos.
  - Modal e interruptor de gobernanza en los ajustes del proyecto para activar/desactivar la firma humana por proyecto.
- **Herramientas MCP y Agentes:**
  - Esquema de `submit_for_review` y `review_task` actualizado en el servidor MCP con tipado estricto en Zod.
  - Actualización de instrucciones y prompts para `qa-security` y `director`.

#### Corregido
- **Correcciones integrales de estabilidad y gobernanza (testeo 2.2.0):**
  - **Locks vigentes:** `update_task_status` y `update_task` desde clientes externos rechazan con HTTP 409 si la historia tiene un lock activo de otro rol; solo el rol dueño del lock o el panel web pueden moverla o editarla.
  - **Validación de rol en claim:** `claim_task` valida de forma temprana que `role_key` exista antes de cualquier otra comprobación (HTTP 422 si no existe).
  - **Roles en salas de reunión:** `start_meeting`, `add_meeting_turn` y `close_meeting` rechazan roles inexistentes con HTTP 422 (participantes, turnos y `assigned_to` en acuerdos). `convert_meeting_to_tasks` reasigna acuerdos con roles inválidos a `project_manager` sin arrojar error 500 y reporta el ajuste en la respuesta.
  - **Guardado de actas en workspace:** `save_meeting_to_workspace` guarda el acta en el workspace del proyecto de la reunión (resolviendo rutas relativas contra la raíz de la app igual que la DoD), impidiendo que se guarde en la raíz de AgileForge cuando el proyecto tiene su propio workspace.
  - **Aislamiento multi-proyecto:** `claim_task`, `submit_for_review`, `update_task_status`, `update_task` y `add_comment` desde clientes no web rechazan con HTTP 409 si la historia no pertenece al proyecto activo.
  - **Bloqueo de salto a revisión:** `update_task_status` hacia `revision` desde clientes no web responde HTTP 409 exigiendo el uso de `submit_for_review` con evidencia.
  - **Límites de entrada y unicidad:** longitud máxima de título de historia (≤ 200 caracteres, HTTP 422), contenido de comentario (≤ 5000 caracteres y rol existente, HTTP 422), nombre de proyecto (≤ 120 caracteres HTTP 422 y prevención de nombres duplicados exactos HTTP 409), y descripción de skill (≤ 1000 caracteres, HTTP 422).
  - **Tipografía y recursos 100% locales:** eliminación de fuentes remotas de Google Fonts en `index.php`, sirviendo una pila de fuentes del sistema moderna y monospace local con cero peticiones externas al cargar.
- **Invalidación y protección de la marca `qa_approved`:**
  - Bloqueo de operaciones de modificación (`claim_task`, `update_task_status`, `update_task`) por clientes externos (no web) sobre historias con `qa_approved` activo esperando firma humana (HTTP 409).
  - Revocación automática de `qa_approved` y registro del evento `qa_approval_revoked` ante solicitudes de cambio (`request_changes`) o cuando el panel web mueve la historia a un estado distinto de `completada`.
  - Prevención de aprobaciones dobles: `review_task approve` sobre historias ya aprobadas devuelve HTTP 409 sin duplicar eventos ni comentarios.
- **Seguridad y validación del interruptor de firma humana:**
  - Exigencia estricta de `client=web` para modificar `human_signoff` (HTTP 403 para otros clientes).
  - Validación booleana estricta con `FILTER_VALIDATE_BOOLEAN` y comprobación de existencia del `project_id` (HTTP 404).
  - Validación de tipos en `verification.commands_run` aceptando únicamente cadenas de texto no vacías (HTTP 422) previniendo advertencias de PHP.

### English

#### Added
- **Independent review & human signoff (`human_signoff`):**
  - Per-project toggle in `settings` table (`{ "default": true, "projects": { "<project_id>": true|false } }`, enabled by default).
  - When enabled, QA approval via `review_task approve` keeps the story in `revision` status, records `qa_approved` metadata and event, and awaits human signoff from the web UI (`client=web`).
  - When disabled, QA approval transitions directly to `completada`.
  - Task completion memory (`MEM-TASK`) only consolidates upon transition to `completada`.
- **Automated gates for submission and review:**
  - `submit_for_review` enforces non-empty `evidence.tests_run` and minimum 20-character `summary` (HTTP 422).
  - `review_task approve` requires a `verification` block containing at least 1 command in `commands_run` and minimum 30-character `notes` (HTTP 422).
  - Enforced minimum review duration via `AGILEFORGE_MIN_REVIEW_SECONDS` (default 60s) calculated against the last `submitted_for_review` event (HTTP 422).
  - Measurement and storage of `review_duration_seconds`.
- **Web Dashboard & UI:**
  - Badge "Aprobada por QA · falta tu firma" on Kanban story cards.
  - One-click "Firmar y Completar" button on QA-approved cards.
  - "Revisión rápida" badge when technical review completed in under 300 seconds.
  - Project settings modal with toggle switch for human signoff.
- **MCP Server & Agents:**
  - Updated Zod input schemas for `submit_for_review` and `review_task` in MCP server.
  - Prompt updates for `qa-security` and `director` agents.

#### Fixed
- **Testing and governance bug fixes:**
  - **Active task locks:** `update_task_status` and `update_task` from non-web clients return HTTP 409 if another role holds an active lock; only the lock owner or the web UI can move or edit the story.
  - **Early role validation on claim:** `claim_task` validates `role_key` existence prior to any other checks (HTTP 422 if invalid).
  - **Meeting role validation & graceful conversion:** `start_meeting`, `add_meeting_turn`, and `close_meeting` reject nonexistent roles with HTTP 422 (participants, turns, and action item assignees). `convert_meeting_to_tasks` safely reassigns tasks with invalid legacy roles to `project_manager` without throwing 500 errors and notes the fallback in the response.
  - **Meeting minutes in project workspace:** `save_meeting_to_workspace` resolves and saves markdown minutes to the meeting project's configured workspace path instead of AgileForge app root.
  - **Cross-project isolation:** `claim_task`, `submit_for_review`, `update_task_status`, `update_task`, and `add_comment` reject operations on stories belonging to other projects with HTTP 409 for non-web clients.
  - **Gatekeeping direct revision transition:** `update_task_status` transitioning to `revision` from non-web clients returns HTTP 409, directing users to `submit_for_review`.
  - **Field limits & project uniqueness:** title limit (≤ 200 chars, HTTP 422), comment content limit (≤ 5000 chars with valid role, HTTP 422), project name limit (≤ 120 chars HTTP 422 and exact duplicate rejection HTTP 409), and skill description limit (≤ 1000 chars, HTTP 422).
  - **Zero external typography requests:** removed Google Fonts dependency from `index.php`, adopting a fast local system font stack.
- **Invalidation and protection of `qa_approved` mark:**
  - Blocked non-web operations (`claim_task`, `update_task_status`, `update_task`) on tasks in `revision` with active `qa_approved` awaiting human signoff (HTTP 409).
  - Automatic revocation of `qa_approved` and dispatch of `qa_approval_revoked` event when QA requests changes (`request_changes`) or web moves story to any non-completed state.
  - Double approval guard: `review_task approve` on already approved stories returns HTTP 409 without duplicating events or comments.
- **Security & validation for human signoff toggle:**
  - Enforced `client=web` requirement for `human_signoff` mutations (HTTP 403).
  - Strict boolean validation using `FILTER_VALIDATE_BOOLEAN` and project existence verification (HTTP 404).
  - Type-safe validation in `verification.commands_run` accepting only non-empty strings (HTTP 422), avoiding PHP array-to-string notices.

---

## [2.2.0] - 2026-11-09

### Español

#### Añadido
- **Memoria nodular por proyecto y rol:**
  - Sistema de memoria interna persistente (`memories`) clasificada por tipo (`progress`, `decision`, `learning`, `scratchpad`, `domain`) con puntuación de importancia (1 a 10).
  - Herramientas MCP `list_memories`, `get_memory`, `remember` y `forget` disponibles para el Director y todos los subagentes especialistas (`backend`, `frontend`, `chief-architect`, `qa-security`, `legal-compliance`, `project-manager`, `creative-director`).
  - Inyección automática de las 5 memorias más relevantes del rol en `GET roles/{id}/brief` y en el `system_prompt` del subagente.
  - Consolidación determinista al completar historias (`MEM-TASK-<id>`) evitando duplicados y desactivación automática si la historia se reabre.
- **Presupuesto y contabilidad por sprint:**
  - Asociación estricta del `sprint_id` activo en la tabla `ai_token_usage` (reemplazando valor `'default'`).
  - Cálculo de `costs_summary` en `Repository::getFullState` y `SprintController::status` filtrado por el sprint activo.
  - Modal de costos y banner del sprint comparan el gasto contra el límite del sprint activo en lugar del acumulado histórico global.
- **Tailwind CSS local:**
  - Distribución local de hojas de estilo Tailwind en `assets/css/` para operación offline sin depender de CDN externa.
- **Documentación de diseño y arquitectura:**
  - `docs/RFC-001`: Registro canónico de roles en archivos, matriz cerrada de capacidades y Forja de Agentes (Forjar, Templar en sandbox aislado por UUID, Armería y Aleaciones).
  - `docs/RFC-002`: Repositorio autoinstalable, único comando de diagnóstico `doctor` en Node.js, concurrencia multiproceso con `WAL` y `BEGIN IMMEDIATE`, y plan de port a Node.js 22 LTS.

#### Corregido
- **Validación de Definition of Done (DoD) con Git:**
  - Ejecución de comandos `git` extraída fuera de `Database::transaction`, eliminando bloqueos de SQLite durante la validación de archivos.
  - Soporte para rutas absolutas y relativas con `./`.
  - Diagnóstico explícito si `git` no está disponible en el PATH del sistema o `exec` está deshabilitado.
- **Streaming SSE y estabilidad de Chat:**
  - Corrección de `TypeError` en `ob_implicit_flush(true)` bajo `declare(strict_types=1);`.
  - Eliminación del retraso artificial con `usleep(15000)`; emisión inmediata de deltas.
  - Validación de disponibilidad de motor en Director antes de negociar SSE; respuesta síncrona JSON descriptiva si no tiene motor configurado.
  - Manejo de evento `done` con error en `assets/js/chat.js` mostrando aviso informativo en modo plugin.
- **Pulido general y validaciones:**
  - Cálculo de locks de 30 minutos y fecha de fin de sprint (`ends_at`) en formato UTC.
  - Feed de actividad reciente filtrado estrictamente por el proyecto activo.
  - Validación de skills contra el catálogo (`Repository::cleanSkillIds`) y redondeo de story points a la serie Fibonacci (1, 2, 3, 5, 8, 13).
  - Reuniones sin participantes con motor responden código HTTP 409 sin crear registros vacíos.
  - Sanitización de argumentos inline con `jsArg()` y `localTime()` en frontend para prevención de XSS.
  - Validación estricta de límites de memoria (título ≤ 200, contenido ≤ 4000) e ID alfanumérico seguro (`[A-Za-z0-9_-]`).
  - Validación numérica estricta en presupuesto de sprint (0 a 100,000 USD).
  - Corrección de llamadas `apiFetch` y `postAction` en panel de memoria y costos.
  - Lectura correcta de columnas de memoria (`content_text` e `importance_score`) en `RoleController::getBrief`.

---

### English

#### Added
- **Modular Project & Role Memory:**
  - Internal persistent memory system (`memories`) categorised by node type (`progress`, `decision`, `learning`, `scratchpad`, `domain`) with importance ranking (1 to 10).
  - MCP tools `list_memories`, `get_memory`, `remember`, and `forget` enabled for Director and all specialist subagents (`backend`, `frontend`, `chief-architect`, `qa-security`, `legal-compliance`, `project-manager`, `creative-director`).
  - Automatic injection of top-5 active role memories into `GET roles/{id}/brief` and subagent `system_prompt`.
  - Deterministic story consolidation (`MEM-TASK-<id>`) preventing duplicates on task completion and auto-deactivation upon story reopening.
- **Per-Sprint Budgeting & Token Accounting:**
  - Strict recording of active `sprint_id` in `ai_token_usage` table (replacing hardcoded `'default'`).
  - Sprint-scoped `costs_summary` in `Repository::getFullState` and `SprintController::status`.
  - Cost modal and sprint banner evaluate spend against the active sprint budget rather than all-time database totals.
- **Local Tailwind CSS:**
  - Bundled local Tailwind styles in `assets/css/` enabling full offline operation without CDN dependencies.
- **Design & Architecture Documentation:**
  - `docs/RFC-001`: File-based role registry, closed capability matrix, and Agent Forge lifecycle (Forge, Temper with UUID-isolated sandbox, Armory, and Alloys).
  - `docs/RFC-002`: Self-installing repo, unified Node.js `doctor` check, multiprocess concurrency with `WAL` and `BEGIN IMMEDIATE`, and Node.js 22 LTS port roadmap.

#### Fixed
- **DoD Git Verification:**
  - Git command validation moved outside `Database::transaction`, preventing SQLite lock contention during evidence inspection.
  - Full support for absolute paths and relative `./` paths.
  - Clear diagnostics returned when `git` is absent from PATH or `exec()` is restricted.
- **SSE Streaming & Chat Stability:**
  - Fixed `TypeError` in `ob_implicit_flush(true)` under strict types.
  - Removed artificial `usleep(15000)` replay delay; deltas stream immediately.
  - Director verifies engine readiness before initiating SSE; falls back to synchronous JSON response when unconfigured.
  - Frontend client handles `done` error payloads, surfacing informative plugin mode toasts.
- **General Polish & Security:**
  - 30-minute lock TTL and sprint `ends_at` calculated in standard UTC.
  - Recent activity feed strictly filtered by active project.
  - Skill validation against official catalog (`Repository::cleanSkillIds`) and Fibonacci rounding for story points (1, 2, 3, 5, 8, 13).
  - Meetings with no engine-ready participants return HTTP 409 without creating orphan rows.
  - Inline event handling escaped via `jsArg()` and `localTime()` preventing XSS.
  - Memory ID validation (`[A-Za-z0-9_-]`, max 80 chars) and cross-project overwrite safeguards.
  - Sprint budget numeric range validation (0 to 100,000 USD).
  - Fixed `apiFetch` call signatures using `postAction()` in memory and cost modals.
  - Fixed column mapping (`content_text` and `importance_score`) in `RoleController::getBrief`.

