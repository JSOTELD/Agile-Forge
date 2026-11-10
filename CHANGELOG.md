# Changelog

Todas las notas notables de cambios en este proyecto se documentarán en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y este proyecto se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
