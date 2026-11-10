# RFC-001: Registro de Roles en Archivos y Forja de Agentes

- **Estado:** Aprobado por David (2026-11-10)
- **Fecha:** 2026-11-09
- **Autores:** David & Equipo de Arquitectura AgileForge
- **Referencia:** ADR-003, ADR-004, ADR-005, RFC-002

---

## 1. Contexto y Motivación

Actualmente, AgileForge cuenta con un escuadrón predeterminado de roles en el tablero (`director`, `lead_architect`, `backend_architect`, `frontend_master`, `qa_security_guard`, `creative_director`, `legal_counsel`, `project_manager`). Aunque este equipo cubre el flujo Scrum inicial, adolece de dos limitaciones arquitectónicas:

1. **Acoplamiento a identificadores fijos en el código:** La lógica de negocio (`TaskController`, `DirectorService`, `BoardService`, `ProjectController`, reglas de revisión de la DoD) comprueba identificadores específicos como `qa_security_guard` o `director` en lugar de evaluar **capacidades funcionales declaradas**.
2. **Inflexibilidad para dominios especializados:** Un proyecto de ciberseguridad requiere auditores PCI-DSS o ingenieros de pentesting; una fintech requiere oficiales de cumplimiento bancario; un proyecto de IA requiere ingenieros de evaluación de modelos o curadores de datasets.

La **Forja de Agentes** introduce un ciclo riguroso de diseño, prueba y activación de agentes autónomos, fundamentado en un registro canónico de roles en archivos y un modelo de permisos desacoplado por capacidades.

---

## 2. Registro Único de Roles en Archivos

Antes de permitir la creación dinámica de agentes, la definición de roles debe desacoplarse del motor de base de datos y del lenguaje de backend (sea PHP o Node.js). La Única Fuente de Verdad reside en archivos Markdown con Frontmatter YAML versionados en Git.

> [!IMPORTANT]
> **Convención de Nomenclatura e IDs:**
> Los identificadores del tablero en la base de datos SQLite y en el campo `assigned_to` de las historias utilizan `snake_case` canónico (`lead_architect`, `qa_security_guard`, `legal_counsel`).
> Los archivos de definición de subagentes en plugins o workspaces suelen nombrarse en `kebab-case` (`chief-architect.md`, `qa-security.md`, `legal-compliance.md`).
> El pack canónico `core` y el registro de roles en archivos **deben preservar obligatoriamente los IDs reales del tablero** (`lead_architect`, `qa_security_guard`, `legal_counsel`), de lo contrario se romperían todas las historias existentes asignadas a esos roles.

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
model: sonnet              # sonnet | opus | haiku | fable | inherit | ID completo (ej. claude-sonnet-5-5)
effort: high               # low | medium | high | xhigh | max
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

1. **Declaración Explícita de Herramientas (`tools`):**
   - **Regla Crítica de Claude Code:** Si el bloque `tools` se omite, Claude Code otorga al agente **TODAS** las herramientas disponibles, incluyendo `Bash` y herramientas del sistema. Por lo tanto, el generador escribe **SIEMPRE** una lista blanca explícita de `tools`. El bloque `disallowedTools` actúa como capa secundaria de defensa en profundidad.
2. **Cálculo Dinámico del Prefijo MCP:**
   - El prefijo de herramientas MCP depende del método de montaje:
     - Instalado como plugin oficial de Claude Code: `mcp__plugin_agileforge_board__<tool>`.
     - Configurado localmente desde `.mcp.json` en el proyecto: `mcp__<server-name>__<tool>`.
   - El generador calcula el prefijo del entorno activo; nunca utiliza un prefijo hardcodeado.
3. **Mapeo de Modelo (`model`):**
   - Acepta alias (`sonnet`, `opus`, `haiku`, `fable`, `inherit`), o identificadores de modelo vigentes (`claude-sonnet-5-5`, `claude-opus-5-5`, etc.).
4. **Nivel de Esfuerzo (`effort`):**
   - Mapea a `low`, `medium`, `high`, `xhigh` o `max`, gobernando el presupuesto de razonamiento (extended thinking) según soporte del modelo.
5. **Soporte de `permissionMode` y `hooks`:**
   - En subagentes generados en `.claude/agents/<id>.md` del proyecto, Claude Code respeta configuraciones como `permissionMode: plan` o `permissionMode: default`, así como `hooks` de ciclo de vida. (En agentes empaquetados dentro de un plugin distribuido, Claude Code ignora estos campos por diseño de seguridad).
6. **Skills Validadas contra Catálogo Combinado:**
   - Se validan contra el catálogo unificado: skills empaquetadas en el plugin (`plugin/agileforge/skills/*/SKILL.md`) más las skills personalizadas creadas en la base de datos (`skills WHERE is_custom = 1`).

---

## 3. Seguridad de los Packs y Principio de Menor Privilegio

> [!IMPORTANT]
> **Alcance del Menor Privilegio:**
> El principio de menor privilegio se aplica de forma estricta a los **roles FORJADOS (nuevos)** para evitar riesgos de inyección o daños al workspace. Los 8 roles del **pack core** mantienen intactas sus capacidades operativas actuales para preservar el 100% del comportamiento del tablero y las historias existentes.

1. **Sin Bash ni Escritura por Defecto en Roles Forjados:**
   - Por defecto, ningún agente forjado recibe permisos de ejecución de comandos (`Bash`) ni de modificación de disco (`Edit`, `Write`).
   - Los roles forjados nacen con perfil de solo lectura (`Read`, `Grep`, `Glob` y herramientas MCP de consulta).
   - Otorgar `Bash`, `Edit` o `Write` a un rol forjado requiere declaración explícita en su manifiesto y emite una advertencia de riesgo visible en la UI.
2. **Lista Blanca Estricta y Validación de Herramientas:**
   - El sistema rechaza cualquier herramienta desconocida o que intente eludir el namespace de AgileForge.
   - Toda herramienta declarada debe existir en el manifiesto del servidor MCP o en el estándar nativo de Claude Code.
3. **Aviso Obligatorio para Packs de Terceros:**
   - Al importar un pack desde un repositorio externo o aleación no certificada, el panel web y el CLI muestran un desglose detallado de las herramientas solicitadas (alertando si incluye `Bash` o acceso a red).
   - Requiere confirmación humana explícita antes de escribir los archivos de rol en el proyecto.

---

## 4. Matriz Cerrada de Capacidades (Capabilities)

En el tablero real de AgileForge existen historias asignadas a `creative_director` (1 historia), `legal_counsel` (3 historias), `project_manager` (2 historias), `qa_security_guard` (3 historias), `lead_architect`, `backend_architect` y `frontend_master`.
Para que ninguna historia existente se bloquee, **todos los roles del pack core que reciben historias conservan `can_claim_tasks` y `can_submit_evidence`**.

Se establece una **lista cerrada y canónica** de 9 capacidades. Ninguna otra capacidad es reconocida por el sistema:

| Capacidad | Descripción | Roles del pack Core que la poseen |
|---|---|---|
| `can_claim_tasks` | Reclamar historias (`claim_task`) y establecer lock de trabajo de 30 min. | `lead_architect`, `backend_architect`, `frontend_master`, `qa_security_guard`, `creative_director`, `legal_counsel`, `project_manager` |
| `can_submit_evidence` | Enviar entregas técnicas a revisión con comandos y archivos. | `lead_architect`, `backend_architect`, `frontend_master`, `qa_security_guard`, `creative_director`, `legal_counsel`, `project_manager` |
| `can_review_tasks` | Auditar y dictaminar historias en estado `revision` (`approve` o `request_changes`). | `qa_security_guard` |
| `can_approve_dod` | Autoridad para declarar cumplida la DoD y transicionar historias a `completada`. | `qa_security_guard`, Humano (web) |
| `can_record_adr` | Asentar decisiones arquitectónicas formales (ADR) en el sistema. | `director`, `lead_architect` |
| `can_facilitate_sprint` | Iniciar sprints, controlar cadencia, gestionar backlog y facilitar dailies. | `director`, `project_manager` |
| `can_forge_agents` | Forjar, templar y dar de alta agentes en la Armería. | `director`, `lead_architect` |
| `can_audit_compliance` | Emitir dictámenes de privacidad, términos legales y compliance normativo. | `legal_counsel` |
| `can_orchestrate` | Descomponer requerimientos de negocio, coordinar roles y delegar trabajo en el tablero. | `director` |

---

## 5. Inventario de Código Acoplado a IDs Fijos y Plan de Migración

### 5.1 Comando de Auditoría
El inventario fue extraído directamente del código fuente mediante el comando:

```bash
git grep -n -E "director|lead_architect|backend_architect|frontend_master|qa_security_guard|creative_director|legal_counsel|project_manager" api/ assets/js/ mcp-server/ plugin/
```

### 5.2 Inventario de Puntos Acoplados

| Archivo | Función / Ubicación Exacta | Rol / ID Hardcodeado | Refactorización con Capacidades |
|---|---|---|---|
| `api/Database.php` | `seedDirectorRole()` | Inserta el rol `director` por defecto si falta en SQLite | Cargar desde `plugin/agileforge/packs/core/director.md`. |
| `bin/migrate.php` | Paso 4 (sección roles) | Siembra los otros 7 roles del escuadrón importando desde `data/team.json` | Reemplazar por importación canónica de `plugin/agileforge/packs/core/`. |
| `api/Database.php` | `repairBoardData()` | `LEGACY_ROLE_IDS` (`qa_security` → `qa_security_guard`, `frontend` → `frontend_master`, etc.) | Preservar mapeo de migración histórica. |
| `api/controllers/TaskController.php` | `review()` | `'qa_security_guard'` (valida dos veces que el revisor sea QA) | Verificar `roleHasCapability($reviewerRole, 'can_review_tasks')`. |
| `api/controllers/TaskController.php` | `updateStatus()` | Regla de negocio: mover a `completada` solo permitido con `client === 'web'` o dictamen de QA | Evaluar `roleHasCapability($role, 'can_approve_dod')` o `client === 'web'`. |
| `api/controllers/TaskController.php` | `addComment()` | `'project_manager'` asignado por defecto si no consta rol | Resolver rol activo con `can_facilitate_sprint`. |
| `api/controllers/TaskController.php` | `consolidateTaskCompletionMemory()` | fallback a `'all'` si no hay asignado | Desacoplado: usa `'all'` o el rol asignado en la historia. |
| `api/controllers/ProjectController.php` | `importWorkspace()` | `$roles = ['backend_architect', 'frontend_master', 'lead_architect', 'qa_security_guard']` y asignaciones fijas | Asignar según capacidades de desarrollo del proyecto activo. |
| `api/controllers/MeetingController.php` | `start()` | Participantes por defecto: `['project_manager', 'lead_architect']` | Seleccionar roles con `can_facilitate_sprint` y `can_record_adr`. |
| `api/controllers/MeetingController.php` | `synthesize()` | Filtra `'director'`, asigna tareas derivadas a `'project_manager'` por defecto | Utilizar rol con `can_orchestrate` o `can_facilitate_sprint`. |
| `api/services/BoardService.php` | `createTask()` | Asignación por defecto: `'project_manager'` | Asignar a rol con `can_facilitate_sprint` o dejar sin asignar. |
| `api/services/DirectorService.php` | `const ROLE_ID = 'director'` | `'director'` | Rol ejecutivo default, respaldado por `can_orchestrate`. |
| `api/Repository.php` | `getFullState()` | `ORDER BY CASE id WHEN 'director' THEN 0 ELSE 1 END` | Priorizar roles con capacidad `can_orchestrate`. |
| `api/tasks.php` | acción `'generate_review_prompt'` | `$isLegal` comprueba si el rol asignado es `'legal_counsel'` | Resolver dinámicamente si el rol tiene `can_audit_compliance`. |
| `api/tasks.php` | acción `'director_instruction'` | Ejecuta la instrucción sobre el rol `'director'` | Invocar al agente que posea `can_orchestrate`. |
| `assets/js/chat.js` | `handleSendChat()` | `targetRole === 'director'` | Evaluar si el rol destino posee capacidad `can_orchestrate`. |
| `assets/js/meetings.js` | Generación de participantes | `'project_manager'`, `'lead_architect'` | Consultar capacidades activas en `appData.roles`. |
| `assets/js/director.js` | Funciones del Director | `'director'` | Reemplazar por chequeo de `can_orchestrate`. |
| `assets/js/kanban.js` | Filtros y avatares | IDs fijos del escuadrón | Iterar dinámicamente sobre la lista de roles del proyecto. |
| `assets/js/app.js` | Modales y badges de rol | `'director'`, `'qa_security_guard'` | Consultar capacidades dinámicas en `appData.roles`. |
| `assets/js/skills.js` | Mapeo de skills por rol | Asignación de skills a roles específicos | Mapear habilidades mediante el manifiesto de cada rol. |
| `assets/js/bindings.js` | Event listeners | Selectores de roles fijos | Vincular listeners dinámicamente según roles disponibles. |
| `mcp-server/src/tools/write.ts` | Herramienta `remember` | Documentación `backend_architect, lead_architect` | Texto de ayuda ilustrativo; no acopla lógica dura. |
| `plugin/agileforge/commands/*.md` | `equipo.md`, `tomar.md`, `reunion.md` | IDs de roles en ejemplos | Ejemplos de uso del pack core. |
| `plugin/agileforge/agents/*.md` | `chief-architect.md`, `qa-security.md`, `legal-compliance.md`, etc. | Nombran IDs reales del tablero (`lead_architect`, `qa_security_guard`, `legal_counsel`) | Mapeo de subagente preservando los IDs del tablero. |

### 5.3 Migración del Escuadrón Actual al Pack "Core"

Para garantizar compatibilidad retroactiva absoluta con las historias y auditorías existentes:
1. Se crea el pack canónico `plugin/agileforge/packs/core/` con los 8 roles vigentes preservando exactamente sus IDs de base de datos: `director`, `lead_architect`, `backend_architect`, `frontend_master`, `qa_security_guard`, `creative_director`, `legal_counsel`, `project_manager`.
2. Las tareas existentes en la base de datos mantienen su campo `assigned_to` sin modificaciones.
3. El motor de resolución resuelve primero roles locales del proyecto y, ante IDs sin prefijo de pack, resuelve sobre el pack `core`.

---

## 6. El Ciclo de la Forja de Agentes

```mermaid
flowchart TD
    subgraph F1["1. Forjar"]
      A["Definición YAML / Markdown<br/>(/forjar o Editor Web)"]
    end

    subgraph F2["2. Templar (Sandbox Aislado)"]
      B["Nivel 1: Validación Fija Obligatoria<br/>(Esquema, herramientas, skills, secretos)"]
      B -->|Pasa| C{"¿Ejecutar Práctica con IA?<br/>(Nivel 2 - Opcional)"}
      C -->|Sí (Acepta costo tokens)| D["Prueba en Sandbox Aislado<br/>(data/sandbox-&lt;uuid&gt;.sqlite + workspace efímero)"]
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
- **Modo Web:** Formulario interactivo con autocompletado de skills, selección de modelo y asignación de capacidades.

### 6.2 Fase 2: Templar en Dos Niveles (Sandbox Aislado con UUID)

> [!IMPORTANT]
> **Aislamiento Multiproceso por UUID:**
> Para evitar colisiones y bloqueos si dos agentes se forjan simultáneamente, cada ejecución de templado utiliza una base de datos efímera única: `data/sandbox-<uuid>.sqlite` y un directorio de trabajo temporal `.agileforge/sandbox/<uuid>/` (ignorado en Git). Al concluir, ambos artefactos se destruyen automáticamente.

#### Nivel 1: Validación Fija Obligatoria (Determinista y Gratuita)
Se ejecuta de forma inmediata en milisegundos sin llamadas a LLM:
- [x] **Formato y Esquema:** YAML frontmatter válido, campos requeridos presentes (`id`, `name`, `model`, `capabilities`, `tools`).
- [x] **Herramientas en Whitelist:** Lista blanca obligatoria explícita; ninguna herramienta desconocida ni violaciones de menor privilegio.
- [x] **Capacidades Válidas:** Solo capacidades pertenecientes a la lista cerrada de 9 capacidades canónicas.
- [x] **Skills en Catálogo:** Toda skill referenciada existe en el catálogo unificado (plugin + `is_custom=1`).
- [x] **Modelo Soportado:** Pertenece a `config/models.json` o es un alias válido (`sonnet`, `opus`, `haiku`, `fable`, `inherit`, o ID como `claude-sonnet-5-5`).
- [x] **Escaneo Anti-Secretos:** Análisis estático para impedir claves de API o tokens hardcodeados en el prompt.

#### Nivel 2: Práctica con IA (Opcional, con Aviso de Costo)

> [!CAUTION]
> **Prevención de Escritura en el Tablero Real en Modo Plugin:**
> `AGILEFORGE_DB_PATH` es consumido por la API PHP (`api/bootstrap.php`), no por el proceso Node del servidor MCP. Si el subagente de prueba utilizara las herramientas predeterminadas `mcp__plugin_agileforge_board__*`, estaría interactuando con el servidor MCP de producción y corrompería el tablero real.
>
> **Arquitectura de Ejecución Aislada mientras conviva PHP:**
> 1. Se levanta una instancia temporal de la API en un puerto efímero libre mediante PHP CLI:  
>    `php -S 127.0.0.1:<puerto-efimero> router.php` con la variable de entorno `AGILEFORGE_DB_PATH=data/sandbox-<uuid>.sqlite`.
> 2. Se lanza un proceso temporal del servidor MCP (denominado `board-sandbox`) con `AGILEFORGE_API_URL=http://127.0.0.1:<puerto-efimero>/api`.
> 3. El subagente de prueba se genera configurando **únicamente las herramientas del prefijo de sandbox**: `mcp__board-sandbox__*` (Claude Code conserva el guion del nombre del servidor). **NUNCA** se le otorga acceso al prefijo `mcp__plugin_agileforge_board__*`.
> 4. Claude Code ejecuta el subagente contra esa instancia de prueba. El agente reclama la tarea sintética, genera evidencia y la somete a revisión.
> 5. Al concluir la verificación, se mata el proceso de PHP efímero, se detiene el MCP de sandbox y se eliminan la base `data/sandbox-<uuid>.sqlite` y el directorio temporal `.agileforge/sandbox/<uuid>/`.
> 6. *En el Núcleo Node v3 (RFC-002):* El servidor MCP de sandbox abre directamente la base temporal en disco sin necesidad de levantar procesos HTTP intermedios.
>
> **En Modo Panel Web (con motor de IA configurado):** El backend ejecuta el runner agéntico utilizando las credenciales de API del servidor contra la base sandbox aislada.

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
- El Líder de Producto puede rotar especialistas según las necesidades del sprint activo.

---

## 8. Estimación de Esfuerzo por Fase

| Fase | Entregables Principales | Complejidad | Estimación |
|---|---|---|---|
| **Fase 1: Registro de Roles en Archivos** | Esquema YAML, refactor de controladores y servicios para verificar la matriz de 9 capacidades, generador de `.claude/agents/<id>.md`. | Media | 4-5 días |
| **Fase 2: Forja y Templado con Sandbox UUID** | Validador Nivel 1, runner de sandbox aislado en `data/sandbox-<uuid>.sqlite` con MCP `board-sandbox` independiente, bypass web con auditoría. | Alta | 5-6 días |
| **Fase 3: Armería, Web UI y Aleaciones** | Gestor visual de la Armería en panel web, límite de escuadrón activo, importador/exportador de packs de agentes. | Media | 4 días |
| **Total Estimado** | | | **13-15 días** |
