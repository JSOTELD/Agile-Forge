# RFC-002: Repositorio Autoinstalable y Migración del Núcleo a Node.js

- **Estado:** Propuesta (Diseño - Fase B)
- **Fecha:** 2026-11-09
- **Autores:** Equipo de Arquitectura AgileForge
- **Referencia:** ADR-001, ADR-002, ADR-004, RFC-001

---

## 1. Visión y Objetivo

**Meta principal:** Que cualquier desarrollador o cualquier IA (Claude Code, Codex, Cursor, Gemini Code Assist) pueda clonar el repositorio y dejar todo el entorno funcional ejecutando un único comando o siguiendo un único archivo, con cero fricción y cero ambigüedad.

Adicionalmente, se evalúa la **unificación del backend en Node.js**, eliminando la dependencia de PHP/Apache/XAMPP para que el runtime de ejecución sea 100% homogéneo con el servidor MCP.

---

## 2. Experiencia de Autoinstalación Universal

### 2.1 Enrutamiento de Guías para IAs y Personas

Para evitar dispersión y mantener una única fuente de verdad:

```
                    ┌────────────────────────┐
                    │      INSTALL.md        │
                    │ (Única fuente de verdad│
                    │   idempotente, paso a  │
                    │   paso, sin supuestos) │
                    └───────────▲────────────┘
                                │
        ┌───────────────────────┼────────────────────────┐
        │                       │                        │
┌───────┴────────┐      ┌───────┴────────┐      ┌────────┴────────┐
│   CLAUDE.md    │      │   AGENTS.md    │      │    README.md    │
│  (Claude Code) │      │ (Codex/Cursor/ │      │ (Personas y     │
│                │      │    Gemini)     │      │  bloque rápido) │
└────────────────┘      └────────────────┘      └─────────────────┘
```

- **`INSTALL.md`:** Contiene los pasos deterministas de instalación, comandos exactos, prerrequisitos de sistema, variables de entorno y verificación.
- **`CLAUDE.md` y `AGENTS.md`:** Archivos concisos en la raíz que instruyen a la IA:
  > *"Para instalar o configurar este repositorio, NO improvises comandos. Lee y ejecuta estrictamente las instrucciones de `INSTALL.md`."*
- **Bloque de README ("Copia esto a tu IA"):**
  ```markdown
  ### 🤖 Instalación Asistida por IA
  Copia y pega este mensaje en tu asistente (Claude Code, Cursor, Codex o Gemini):
  > "Configura el entorno de desarrollo de AgileForge siguiendo estrictamente la guía `INSTALL.md`. Ejecuta la instalación, corre el diagnóstico con `doctor` y confírmame cuando el servidor y el MCP estén listos para operar."
  ```

### 2.2 Un Solo Comando de Instalación Idempotente

El script de instalación detecta el entorno y prepara la base SQLite, instala dependencias y genera tokens si faltan, sin romper configuraciones preexistentes:

- Comando multiplataforma:
  ```bash
  npm run setup
  ```
  *(Internamente ejecuta `node bin/setup.mjs`, compatible con Windows PowerShell, macOS y Linux).*
- Acciones que ejecuta de forma idempotente:
  1. Verifica runtime (Node.js >= 18).
  2. Crea `.env` a partir de `.env.example` solo si no existe.
  3. Genera tokens aleatorios seguros (`AGILEFORGE_API_TOKEN`, `AGILEFORGE_VIEW_TOKEN`) si están vacíos.
  4. Crea carpetas de datos (`data/`, `data/backup/`).
  5. Instala paquetes de Node (`npm install` en raíz y `mcp-server/`).
  6. Compila TypeScript y genera los bundles MCP (`npm run build && npm run bundle`).
  7. Inicializa y migra la base SQLite (`Database::ensureSchema` / script equivalente).

### 2.3 Comando de Diagnóstico: `doctor`

Un comando de inspección de salud integral que valida el sistema sin comprometer credenciales:

```bash
npm run doctor
# o bien: af doctor
```

**Verificaciones que realiza:**
- [x] Versión de Node.js (mínimo 18.0.0, óptimo 20 o 22 LTS).
- [x] Versión de PHP (durante fase híbrida) y extensiones (`pdo_sqlite`, `mbstring`).
- [x] Integridad de SQLite (archivo accesible, permisos de lectura/escritura, modo WAL activo).
- [x] Servidor MCP (bundle generado en `dist/index.js`, handshake JSON-RPC funcional).
- [x] Estado de autenticación:
  - Token principal: `Configurado (64 chars; origen: .env)` *(¡NUNCA imprime el valor del token!)*.
  - Token de vista: `Configurado (64 chars; origen: .env)`.
- [x] Motor de IA activo y proveedores con clave configurada (`Anthropic: ✓, OpenAI: sin clave, IA local: Ollama activo`).

---

## 3. Evaluación Técnica: Migración del Backend PHP a Node.js

### 3.1 Motivación
Hoy en día, el usuario de AgileForge debe tener dos entornos instalados:
1. **PHP 8.2+ con servidor web (Apache / XAMPP / PHP CLI server)** para la API REST y el panel visual.
2. **Node.js 18+** para el servidor MCP y los bundles de Claude Code.

Unificar todo en **Node.js** reduce los requisitos de instalación a una única herramienta estándar en desarrollo moderno de software.

### 3.2 Principios de la Migración
1. **Mismo esquema SQLite:** No hay migración ni exportación de datos. La base de datos `data/agileforge.sqlite` se lee y escribe con las mismas tablas, columnas y semántica WAL.
2. **Misma API REST:** Los endpoints (`/api/state`, `/api/tasks`, `/api/overview`, etc.) mantienen exactamente la misma firma JSON para que `index.php` (o la SPA estática) funcione sin reescrituras de interfaz.
3. **MCP en el mismo proceso (In-Process Direct Storage):**
   - Actualmente, Claude Code llama a MCP (Node), y MCP hace una petición HTTP a `http://localhost/Open-to-code/api/...` (PHP).
   - Con Node unificado, las herramientas MCP pueden **llamar directamente al repositorio SQLite en memoria/disco**, eliminando la latencia de red HTTP, el puerto local y la necesidad de tokens de API para el uso en la misma máquina.
4. **Panel Web Opcional y Bajo Demanda:**
   - Para quien solo usa Claude Code vía CLI o MCP, no se levanta ningún servidor HTTP.
   - Si el usuario desea ver el tablero visual Kanban, ejecuta `npm run web` (o `af web`) y se levanta un servidor Fastify / Express ultraligero que sirve los estáticos y la API.

---

### 3.3 Comparativa Técnica: `node:sqlite` vs `better-sqlite3`

| Criterio | `node:sqlite` (Módulo Nativo de Node.js) | `better-sqlite3` (Librería npm) |
|---|---|---|
| **Disponibilidad** | Incluido de forma nativa en Node.js v22.5.0+. | Requiere instalación vía `npm install`. |
| **Compilación C++ (node-gyp)** | **No requiere compilación.** Zero build tools. | **Requiere compilador C++ (Visual Studio Build Tools en Windows / gcc).** Altísima tasa de fallos en PCs estándar sin toolchain C++. |
| **Estabilidad** | Marcado como experimental en v22, pero API estable de SQLite 3 C subyacente. | Extremadamente maduro, síncrono, probado en batalla. |
| **Modo de Concurrencia** | Síncrono (`DatabaseSync`). Idéntico a PDO SQLite en PHP. | Síncrono (`new Database()`). Excelente soporte de transacciones y WAL. |
| **Riesgo en Windows** | **Nulo.** No compila binarios, corre directo en cualquier Node 22. | **Alto.** Si el usuario no tiene Python y C++ Build Tools en Windows, `npm install` falla. |

#### Veredicto de Arquitectura:
- **Estrategia Primaria:** Usar **`node:sqlite`** requiriendo Node.js 22 LTS como runtime oficial de AgileForge. Esto garantiza instalación limpia en Windows sin compilar C++.
- **Fallback para Node 18/20:** Soporte a través de un adaptador sqlite ligero precompilado o manteniendo temporalmente el driver PHP.

---

### 3.4 Matriz de Riesgos y Esfuerzo

| Módulo a migrar | Complejidad | Riesgo | Mitigación |
|---|---|---|---|
| Capa de Persistencia (`Repository` + `Database`) | Media (3-4 días) | Bajo (SQLite es idéntico) | Tests unitarios comparativos leyendo la misma base de datos. |
| Transición de estados y Locks (`TaskController`) | Media (2 días) | Medio (concurrencia de locks) | Replicar la semántica de `BEGIN IMMEDIATE` y UTC timestamp. |
| Integración de Motores LLM (`LlmService`, `LocalAiService`) | Media (3 días) | Bajo | Usar SDKs oficiales de Node (`@anthropic-ai/sdk`, OpenAI, Gemini). |
| Servidor HTTP embebido (Fastify / Hono) | Baja (2 días) | Muy bajo | Mapeo 1:1 de los controladores existentes. |

---

## 4. Separación de Alcances: Inmediato vs. Migración

### 4.1 Fase 2.1: Lo que se puede hacer YA con PHP (Sin romper nada)
- Escribir `INSTALL.md` como estándar canónico de instalación.
- Crear `AGENTS.md` y `CLAUDE.md` apuntando a `INSTALL.md`.
- Implementar `bin/doctor.php` y `bin/af.ps1 doctor` para diagnóstico completo de PHP, SQLite, MCP y tokens.
- Añadir el bloque "Copia esto a tu IA" en `README.md`.

### 4.2 Fase 2.2: Preparación de la Transición
- Decisión de migración y benchmark de `node:sqlite`.
- Registro de roles en archivos Markdown/YAML (`roles/*.md`).
- Desacoplamiento de capacidades en la lógica del tablero.

### 4.3 Fase 3: Núcleo Node.js v3 y Forja v1
- Implementación del repositorio en TypeScript/Node.js en `src/core/`.
- MCP in-process (acceso directo a BD sin peticiones HTTP locales).
- Lanzamiento de la Forja de Agentes v1 (Forjar, Templar, Armería, Aleaciones).
- Mantenimiento de PHP como backend legado durante una versión menor para transición suave.

---

## 5. Propuesta y Justificación del Orden de Trabajo

El orden de trabajo recomendado para maximizar valor y minimizar riesgos es:

```
┌────────────────────────────────────────────────────────────────────────┐
│ 1. Fase 2.2 (Decisión de Migración & Banco de Pruebas de node:sqlite)  │
│    Valida la compatibilidad en Windows sin compiladores C++.           │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│ 2. Repo Autoinstalable (INSTALL.md, AGENTS.md, CLAUDE.md, doctor)      │
│    Cualquier IA o persona puede clonar y verificar el entorno actual.  │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│ 3. Registro de Roles en Archivos (Capacidades declarativas)            │
│    Independiza los agentes del lenguaje de backend (PHP o Node).       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│ 4. Núcleo Node.js v3 (Fastify + node:sqlite + MCP in-process)          │
│    Elimina XAMPP/PHP definitivamente; una sola dependencia.            │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
┌───────────────────────────────────▼────────────────────────────────────┐
│ 5. Forja de Agentes v1 (Forjar, Templar, Armería, Aleaciones)          │
│    Construida directamente sobre las capacidades y el núcleo moderno.  │
└────────────────────────────────────────────────────────────────────────┘
```

### Justificación Técnica:
1. **El Repo Autoinstalable debe hacerse antes del núcleo Node:** Permite a cualquier agente colaborar inmediatamente en el código actual de PHP/MCP sin tropezar con la configuración.
2. **El Registro de Roles en Archivos debe preceder a la Forja:** La Forja necesita un formato estándar donde escribir y probar los agentes. No tiene sentido construir la Forja sobre strings fijos de PHP.
3. **La Forja v1 es el broche de oro:** Al montarse sobre el Núcleo Node v3, la fase de "Templado" puede correr pruebas locales ultra-rápidas mediante llamadas in-process, sin saltos de red ni servidores web externos.
