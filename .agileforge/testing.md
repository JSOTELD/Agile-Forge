---
estado: borrador          # borrador | aprobada. El tester solo prueba con "aprobada", y solo David la cambia.
aprobada_por:
fecha:
---

# Receta de pruebas · AgileForge

> Receta del Tester de AgileForge (v0, experimental) para el propio AgileForge, hecha con la plantilla
> `plugin/agileforge/templates/testing.md`. Aquí no hay ninguna credencial: los tokens de prueba los genera
> el entorno desechable, se leen a variables y nunca se imprimen.

**Por qué un clon en una carpeta temporal:** la app lee los tokens **siempre** del `.env` de su raíz y escribe el log
**siempre** en su `data/logs/agileforge.log`, aunque la base sea temporal. Si usáramos la instalación real, el tester
usaría tokens reales y ensuciaría el log real. Con un clon limpio todo es propio y desechable: `.env` con tokens
nuevos, log propio, base temporal y datos de ejemplo (el clon no trae `data/team.json`, que no se versiona).
El clon parte del último commit: lo que no esté confirmado no se prueba.

## 1. Entorno desechable

### 1a. Suite de contrato (siempre primero, debe estar en verde)
```bash
npm test          # Windows con XAMPP: set AGILEFORGE_PHP=D:\xampp\php\php.exe
```
`tests/run.mjs` ya lo aísla todo: `AGILEFORGE_DB_PATH` con una base temporal, carpeta temporal propia
(`TMPDIR`/`TMP`/`TEMP`, para no compartir el contador del límite de peticiones con el tablero real) y servidor PHP
en un puerto libre que se apaga al terminar. Esperado: 0 fallos.

### 1b. Entorno exploratorio (clon limpio, puerto 8099)
bash (Linux, macOS, Git Bash):
```bash
export TESTER_TMP="$(mktemp -d)"
git clone --quiet . "$TESTER_TMP/app"
export TMPDIR="$TESTER_TMP" TMP="$TESTER_TMP" TEMP="$TESTER_TMP"
export AGILEFORGE_DB_PATH="$TESTER_TMP/tester.sqlite"
(cd "$TESTER_TMP/app" && npm run setup && npm run doctor)
php -S 127.0.0.1:8099 -t "$TESTER_TMP/app" "$TESTER_TMP/app/router.php" 2> "$TESTER_TMP/php-server.log" &
echo $! > "$TESTER_TMP/server.pid"
```
PowerShell (Windows):
```powershell
$env:TESTER_TMP = Join-Path $env:TEMP ("agileforge-tester-" + [guid]::NewGuid())
New-Item -ItemType Directory $env:TESTER_TMP | Out-Null
git clone --quiet . "$env:TESTER_TMP\app"
$env:TMPDIR = $env:TMP = $env:TEMP = $env:TESTER_TMP
$env:AGILEFORGE_DB_PATH = "$env:TESTER_TMP\tester.sqlite"
Push-Location "$env:TESTER_TMP\app"; npm run setup; npm run doctor; Pop-Location
$php = if ($env:AGILEFORGE_PHP) { $env:AGILEFORGE_PHP } else { 'php' }
$srv = Start-Process $php -ArgumentList '-S','127.0.0.1:8099','-t',"$env:TESTER_TMP\app","$env:TESTER_TMP\app\router.php" -RedirectStandardError "$env:TESTER_TMP\php-server.log" -PassThru -WindowStyle Hidden
$srv.Id | Set-Content "$env:TESTER_TMP\server.pid"
```
- **URL base:** `http://127.0.0.1:8099` · API: `/api/index.php/<ruta>` y `/api/tasks.php` · panel: `/index.php` · vista de solo lectura: `/vista.php`.
- `npm run setup` necesita red (`npm ci` en `mcp-server/`). Sin red, sustitúyelo por `php bin/migrate.php` dentro del clon: también crea un `.env` con tokens nuevos y siembra los datos de ejemplo.

## 2. Datos de ejemplo
`npm run setup` (o `php bin/migrate.php`) siembra `data/team.example.json`: un proyecto, los 8 roles del core y
2 historias de ejemplo. Para volver a empezar: apaga el servidor, borra `$TESTER_TMP/tester.sqlite` y repite la siembra.

## 3. Perfiles (credenciales DE PRUEBA, generadas en el `.env` del clon)
Leer los tokens a variables sin mostrarlos:
```bash
export AF_TEST_TOKEN="$(grep -E '^AGILEFORGE_API_TOKEN=' "$TESTER_TMP/app/.env" | cut -d= -f2-)"
export AF_TEST_VIEW_TOKEN="$(grep -E '^AGILEFORGE_VIEW_TOKEN=' "$TESTER_TMP/app/.env" | cut -d= -f2-)"
```
```powershell
$envLines = Get-Content "$env:TESTER_TMP\app\.env"
$env:AF_TEST_TOKEN = ($envLines -match '^AGILEFORGE_API_TOKEN=') -replace '^AGILEFORGE_API_TOKEN=',''
$env:AF_TEST_VIEW_TOKEN = ($envLines -match '^AGILEFORGE_VIEW_TOKEN=') -replace '^AGILEFORGE_VIEW_TOKEN=',''
```

| perfil | qué puede hacer | cómo se usa |
|---|---|---|
| token principal | todo. El campo `client` distingue el panel web (`web`) de los agentes (`mcp`, `claude-code`): solo `web` firma y completa historias y cambia el interruptor de firma humana | `Authorization: Bearer $AF_TEST_TOKEN` |
| token de vista | solo `GET` en `api/index.php` y solo en su lista blanca (overview, events, meetings, decisions, tasks, memories, sprint/status, roles/{id}/brief); escribir debe dar 403 | `Authorization: Bearer $AF_TEST_VIEW_TOKEN` |
| sin token | nada de la API: 401. Además, un `Host` u `Origin` que no sea local debe dar 403 | sin cabecera |
| usuario nuevo | instalación desde cero con los datos de ejemplo: el propio clon de 1b (`npm run setup` + `npm run doctor` sin errores) | primeras pantallas y primeras acciones sobre los datos de ejemplo |

## 4. Log del servidor
- **Log de la app:** `$TESTER_TMP/app/data/logs/agileforge.log` (excepciones, errores fatales y avisos).
- **Salida del servidor PHP:** `$TESTER_TMP/php-server.log` (avisos de PHP y una línea por petición).
- **Solo lo nuevo de una tanda:** anota el número de líneas de ambos antes (`wc -l` / `(Get-Content f).Count`) y lee desde ahí después.

## 5. Límite de peticiones
- **Límite del sistema:** 180 peticiones por minuto por IP y token, contadas en la carpeta temporal (`TMPDIR`). En el entorno exploratorio **no** se define `AGILEFORGE_RATE_LIMIT`: el límite real también se prueba (la petición 181 debe dar 429 con `Retry-After`).
- **Ritmo del tester:** como máximo 2 peticiones por segundo fuera de la prueba del límite; ante un 429, esperar 60 s.
- `npm test` sube el límite a 1000 solo dentro de su propio servidor de pruebas (`tests/run.mjs`).

## 6. Reiniciar y apagar
```bash
# Reiniciar desde cero
kill "$(cat "$TESTER_TMP/server.pid")"; rm -f "$TESTER_TMP/tester.sqlite"*
(cd "$TESTER_TMP/app" && php bin/migrate.php)        # y vuelve a lanzar php -S como en 1b

# Apagar y limpiar
kill "$(cat "$TESTER_TMP/server.pid")"; rm -rf "$TESTER_TMP"
unset TESTER_TMP AGILEFORGE_DB_PATH AF_TEST_TOKEN AF_TEST_VIEW_TOKEN
```
```powershell
Stop-Process -Id (Get-Content "$env:TESTER_TMP\server.pid")
Remove-Item -Recurse -Force $env:TESTER_TMP
Remove-Item Env:TESTER_TMP, Env:AGILEFORGE_DB_PATH, Env:AF_TEST_TOKEN, Env:AF_TEST_VIEW_TOKEN
```
Al final, `git status --porcelain` en el repo debe mostrar solo cambios en la carpeta del tester.

## 7. Carpeta del tester
- **Único lugar donde el tester puede escribir:** `tests/tester/` (en el repo, no en el clon).
- **Cómo ejecutar sus pruebas** (con el entorno de 1b levantado y los tokens de 3 cargados):
  ```bash
  export AF_TEST_BASE="http://127.0.0.1:8099" AF_TEST_ROOT="$TESTER_TMP/app"
  node --test --test-concurrency=1 "tests/tester/*.test.mjs"
  ```
  Las pruebas importan `../helpers.mjs` (`api`, `action`, `rawGet`, `newTask`).
- `npm test` solo ejecuta `tests/*.test.mjs` del primer nivel: las pruebas de `tests/tester/`, que fallan a propósito mientras el bug siga abierto, **no** entran en el CI.

## 8. Fuera de alcance
- El motor de IA de la web (claves de API y proveedores externos): sin clave configurada, reuniones y `director_instruction` responden 409, y eso es lo esperado.
- La instalación real: `data/agileforge.sqlite`, el `.env` de la raíz y el XAMPP o el `npm start` del usuario.
- Publicar en GitHub: nada de PR, issues ni push. El CI se consulta solo en modo lectura.

