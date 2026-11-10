# 🤝 Cómo contribuir a AgileForge v2

🌐 [English](CONTRIBUTING.md) · **Español**

¡Gracias por tu interés en contribuir a **AgileForge v2**! El proyecto combina el desarrollo de software humano con agentes de IA que trabajan juntos bajo Scrum y la 7.ª edición del PMBOK.

---

## 🏛️ Principios de arquitectura

1. **Persistencia centralizada en SQLite**
   - Todo cambio de estado (historias, reuniones, decisiones, comentarios) pasa por los servicios de `api/services/` (`BoardService`, `DirectorService`, `EngineService`, `LlmService`) o por `api/Repository.php`, usando `Database::transaction()`.
   - La base de datos funciona en modo `WAL` con `busy_timeout = 15000` ms y las transacciones de escritura empiezan con `BEGIN IMMEDIATE`.
   - **Cierra todos los cursores** (`$stmt->closeCursor()`) tras un `fetch()` de una sola fila, y nunca mantengas una transacción abierta mientras esperas a un modelo de IA: una llamada larga con un cursor de lectura abierto bloquea la base de datos para todos los demás ("database is locked").

2. **El servidor MCP es el puente estándar**
   - Toda capacidad nueva que se ofrezca a los agentes debe ser una herramienta tipada en `mcp-server/src/` (registrada desde `index.ts`), con la entrada validada por `zod`.
   - Los nombres de las herramientas van en inglés (snake_case); las descripciones deben ser claras y breves. Las herramientas de lectura llevan `readOnlyHint: true`; las de escritura nunca deben ser alcanzables desde el punto de entrada lector (`src/viewer.ts`), cuyo cliente solo hace `GET`.
   - Hay un solo código y dos bundles de plugin: **Control** (21 herramientas) y **Vista** (10 herramientas de lectura). Si añades o quitas herramientas, actualiza las cifras en la documentación.

3. **La gobernanza humana no es negociable**
   - Los agentes pueden pasar historias a `en_progreso` (con lock atómico) y a `revision` (con diffs y evidencia de pruebas).
   - Pasar una historia a `completada` está reservado al Product Owner humano (web) o a un veredicto explícito de QA (`review_task` con `decision: approve`) dado por el rol `qa_security_guard`, sobre una historia en `revision` con evidencia, y **nunca** por el agente que la tomó. El Director General tampoco puede hacerlo. No debilites estas comprobaciones; los rechazos deben devolver un 403/409 claro.
   - El lock se libera cuando la historia pasa a `completada` o vuelve a `backlog`; `request_changes` la devuelve a `en_progreso` y mantiene el lock del desarrollador.
   - Nunca leas indicadores de aprobación de `$_REQUEST` ni de la URL.

4. **Dos tokens, dos alcances**
   - `AGILEFORGE_API_TOKEN` da acceso total. `AGILEFORGE_VIEW_TOKEN` es de solo lectura: solo vale para `GET` en `api/index.php`, y `api/tasks.php` debe responderle `403`.
   - `vista.php`, el plugin Vista y el modo lector del servidor MCP solo pueden usar el token de solo lectura. Todo endpoint nuevo que escriba debe rechazarlo. Nunca incrustes el token principal en una página ni en un plugin.

5. **Las claves de API nunca salen del servidor**
   - Las claves viven solo en `.env` (se escriben con `Bootstrap::setEnvValue()`), nunca en la base de datos, los logs, el navegador ni Git. La interfaz solo recibe una pista como `sk-ant-…a1b2`.
   - El servidor solo llama a URL fijas de los proveedores; nunca a una URL enviada por el cliente.

6. **Honestidad técnica**
   - Sin certificaciones simuladas ni puntuaciones estáticas en la interfaz. Si un valor sale de una heurística, del DOM o de una API externa, la interfaz debe decirlo. Si no hay motor de IA disponible, dilo con claridad en vez de inventar una respuesta.

7. **Bilingüe por defecto (inglés + español)**
   - Los textos nuevos de la interfaz van en `assets/js/i18n.js` con claves `es` y `en`.
   - La documentación se mantiene en ambos idiomas: `README.md` ⇄ `README.es.md`, `BEGINNERS_GUIDE.md` ⇄ `MANUAL_NOVATOS.md`, `docs/API_KEYS.md` ⇄ `docs/API_KEYS.es.md`, `CONTRIBUTING.md` ⇄ `CONTRIBUTING.es.md`, `adapters/*.md` ⇄ `adapters/*.es.md`. Si cambias uno, actualiza el otro en el mismo PR (o indica en el PR que la traducción queda pendiente).

---

## 🛠️ Desarrollo local

```bash
# 1. Clona el repositorio
git clone https://github.com/JSOTELD/agile-forge.git
cd agile-forge

# 2. Crea la base de datos y el .env (se puede repetir sin riesgo: nunca sobrescribe datos)
php bin/migrate.php

# 3. Instala y compila el servidor MCP
cd mcp-server
npm install
npm run build     # tsc -> dist/ (lo usan Cursor, Codex, Gemini)
npm run bundle    # esbuild -> los dos bundles de los plugins (ver abajo)
cd ..

# 4. Inicia un servidor de desarrollo (el puerto 8000 es el de Colibri, usa el 8080).
#    Pasa siempre router.php: el servidor interno ignora .htaccess y serviría /.env y /data/*.sqlite
php -S localhost:8080 router.php
```

Para probar las funciones de IA puedes añadir tus propias claves en **⚙️ Motor IA → 🔑 Claves de API** (ver [docs/API_KEYS.es.md](docs/API_KEYS.es.md)) o usar un modelo local con Ollama. **Nunca subas `.env`, `data/*.sqlite` ni `data/logs/`** (ya están en `.gitignore`).

---

## 🧪 Comprobaciones antes de abrir un Pull Request

Son las mismas comprobaciones que ejecuta CI (`.github/workflows/ci.yml`):

```bash
# 1. Sintaxis PHP
find . -name "*.php" ! -path "./mcp-server/*" ! -path "./vendor/*" -exec php -l {} +

# 2. Migración (pruébala también en una copia limpia: AGILEFORGE_DB_PATH=/tmp/test.sqlite)
php bin/migrate.php

# 3. Sintaxis de los módulos ES
for f in assets/js/*.js; do node --check "$f" || exit 1; done

# 4. Comprueba tipos y compila el servidor MCP
cd mcp-server && npm ci && npm run check && npm run build && cd ..

# 5. Regenera los bundles de los plugins y comprueba que no cambia nada
cd mcp-server && npm run bundle && cd ..
git status --short plugin/    # no debe haber cambios inesperados tras subir los bundles

# 6. Valida el marketplace y los dos plugins (CLI de Claude Code)
claude plugin validate .
claude plugin validate plugin/agileforge
claude plugin validate plugin/agileforge-vista
```

### Bundles de los plugins

Los plugins son autocontenidos, así que cada uno incluye su propio servidor MCP como un solo archivo generado desde `mcp-server/src/`:

| Bundle | Se genera desde | Plugin |
|---|---|---|
| `plugin/agileforge/server/agileforge-mcp.mjs` | `src/index.ts` (21 herramientas) | AgileForge Control |
| `plugin/agileforge-vista/server/agileforge-vista-mcp.mjs` | `src/viewer.ts` (10 herramientas de lectura) | AgileForge Vista |

- **Tras cualquier cambio en `mcp-server/src/`, regenéralos:** `cd mcp-server && npm run bundle`.
- **Sube los bundles al repositorio** junto con el cambio de código. Están versionados a propósito: los usuarios instalan el plugin directamente desde Git, sin paso de compilación.
- CI comprueba que los bundles subidos estén al día (los reconstruye y falla si difieren), así que un bundle desactualizado bloquea el PR.
- Nunca edites un bundle a mano, y mantén sincronizada la versión del plugin en `.claude-plugin/marketplace.json` y en el manifiesto de cada plugin.
- `mcp-server/dist/` (de `npm run build`, `tsc`) es aparte: es lo que ejecutan los adaptadores de Cursor, Codex y Gemini. Recompílalo también cuando cambies `src/`.

Si añades un módulo JavaScript nuevo, agrégalo también al paso `Validate ES Modules Syntax` de `ci.yml` y al import map de `index.php`.

### Lista de verificación del Pull Request
- [ ] Las comprobaciones anteriores pasan (incluidos `npm run check`, `npm run bundle` con los bundles subidos y `claude plugin validate`).
- [ ] Las reglas de gobernanza y de tokens siguen intactas: solo un humano o un agente QA distinto completa una historia; el token de solo lectura nunca puede escribir.
- [ ] No hay claves, tokens, `.env` ni archivos de base de datos en el diff.
- [ ] El texto nuevo de la interfaz tiene traducciones `es` y `en`.
- [ ] Documentación actualizada en ambos idiomas (o traducción marcada como pendiente).

¡Bienvenido al equipo!
