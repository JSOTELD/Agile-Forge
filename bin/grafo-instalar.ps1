# AgileForge - Grafos (Curador de grafos)
# 1) Instala graphify (paquete oficial "graphifyy") si no esta: uv, pipx o pip --user.
# 2) Con la receta .agileforge/grafos.md aprobada: grafo de la IA en graphify-out/ (solo codigo, 0 tokens),
#    notas del codigo en .agileforge/vault/codigo/ y bitacora del tablero en .agileforge/vault/bitacora/.
# Registro: data\logs\grafo-instalar.log
# Uso: doble clic en bin\grafo-instalar.cmd   |   bin\grafo-instalar.cmd -SoloInstalar
param([switch]$SoloInstalar)

$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root
$LogDir = Join-Path $Root 'data\logs'
New-Item -ItemType Directory -Force $LogDir | Out-Null
Start-Transcript -Path (Join-Path $LogDir 'grafo-instalar.log') -Force | Out-Null

function Paso($n, $t) { Write-Host ''; Write-Host "[$n] $t" -ForegroundColor Cyan }
function Fin($code) {
    Write-Host "FIN codigo=$code"
    Stop-Transcript | Out-Null
    Write-Host ''
    if ($code -eq 0) { Write-Host 'Listo.' -ForegroundColor Green } else { Write-Host 'Se detuvo antes de terminar (ver arriba).' -ForegroundColor Yellow }
    Read-Host 'Pulsa Enter para cerrar'
    exit $code
}

# Carpetas donde uv, pipx y pip --user dejan los ejecutables
function Rutas-Herramientas {
    $r = @("$env:USERPROFILE\.local\bin")
    $r += Get-ChildItem "$env:APPDATA\Python\Python3*\Scripts" -Directory -ErrorAction SilentlyContinue | ForEach-Object { $_.FullName }
    return $r | Where-Object { Test-Path $_ }
}
function Refrescar-Path {
    foreach ($p in Rutas-Herramientas) { if ($env:Path -notlike "*$p*") { $env:Path = "$p;$env:Path" } }
}

function Buscar-Graphify {
    $c = Get-Command graphify -ErrorAction SilentlyContinue
    if ($c) { return ,@($c.Source) }
    foreach ($py in @('py', 'python')) {
        if (Get-Command $py -ErrorAction SilentlyContinue) {
            & $py -c 'import graphify' 2>$null
            if ($LASTEXITCODE -eq 0) { return ,@($py, '-m', 'graphify') }
        }
    }
    return $null
}

function Python310 {
    foreach ($py in @('py', 'python')) {
        if (Get-Command $py -ErrorAction SilentlyContinue) {
            & $py -c 'import sys; raise SystemExit(0 if sys.version_info >= (3, 10) else 1)' 2>$null
            if ($LASTEXITCODE -eq 0) { return $py }
        }
    }
    return $null
}

# ---------------------------------------------------------------- 1. graphify
Paso 1 'graphify'
Refrescar-Path
$G = Buscar-Graphify
if (-not $G) {
    Write-Host 'graphify no esta instalado. Instalando el paquete oficial graphifyy...'
    if (Get-Command uv -ErrorAction SilentlyContinue) {
        uv tool install graphifyy
        uv tool update-shell
    } elseif (Get-Command pipx -ErrorAction SilentlyContinue) {
        pipx install graphifyy
        pipx ensurepath
    } else {
        $py = Python310
        if (-not $py) {
            Write-Host 'No encontre uv, pipx ni Python 3.10 o superior.' -ForegroundColor Yellow
            Write-Host 'Instala uv (winget install astral-sh.uv), cierra esta ventana y vuelve a abrir bin\grafo-instalar.cmd.'
            Fin 1
        }
        & $py -m pip install --user --upgrade graphifyy
    }
    Refrescar-Path
    $G = Buscar-Graphify
    if (-not $G) { Write-Host 'Se instalo, pero no consigo ejecutar graphify. Revisa el registro.' -ForegroundColor Yellow; Fin 1 }
}

# Los agentes (Git Bash de Claude Code) llaman a "graphify": su carpeta debe estar en el PATH del usuario
if ($G[0] -notmatch '^(py|python)$') {
    $dir = Split-Path -Parent $G[0]
    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    if ($userPath -notlike "*$dir*") {
        [Environment]::SetEnvironmentVariable('Path', ($userPath.TrimEnd(';') + ";$dir"), 'User')
        Write-Host "Anadido al PATH de tu usuario: $dir (abre una terminal nueva para usarlo)"
    }
} else {
    Write-Host 'Aviso: graphify funciona como modulo de Python, pero no hay comando "graphify" en el PATH.' -ForegroundColor Yellow
}
$pre = @(); if ($G.Count -gt 1) { $pre = $G[1..($G.Count - 1)] }
# Ejecuta graphify mostrando (y registrando) su salida; devuelve solo el codigo de salida
function G { & $G[0] @pre @args 2>&1 | ForEach-Object { "$_" } | Out-Host; return $LASTEXITCODE }
$null = G --version
if ($SoloInstalar) { Fin 0 }

# ---------------------------------------------------------------- 2. receta
Paso 2 'Receta .agileforge\grafos.md'
$receta = Join-Path $Root '.agileforge\grafos.md'
if (-not (Test-Path $receta)) { Write-Host 'No existe la receta. Pidela con /agileforge:grafo en Claude Code.' -ForegroundColor Yellow; Fin 1 }
$lineas = Get-Content $receta -Encoding UTF8
if (-not ($lineas | Select-String -Pattern '^estado:\s*aprobada' -Quiet)) {
    # La aprobacion es tuya: se muestra lo esencial y solo sigue si escribes "s"
    Write-Host 'La receta esta en BORRADOR. Lo esencial:' -ForegroundColor Yellow
    $lineas | Select-String -Pattern '^\s*- \*\*(Raiz|Raíz|Motor|Carpeta del vault|Proyecto del tablero|.Se versiona)' | ForEach-Object { Write-Host "  $($_.Line.Trim())" }
    Write-Host "  (completa: $receta)"
    $resp = Read-Host 'Apruebas esta receta? Escribe s para aprobarla y seguir, cualquier otra cosa para salir'
    if ($resp -notmatch '^\s*[sS]\s*$') { Write-Host 'Sin aprobar: no se construye nada.'; Fin 1 }
    $hoy = Get-Date -Format 'yyyy-MM-dd'
    $nuevo = foreach ($l in $lineas) {
        if ($l -match '^estado:\s*borrador') { $l -replace '^estado:\s*borrador', 'estado: aprobada' }
        elseif ($l -match '^aprobada_por:') { "aprobada_por: $env:USERNAME (bin\grafo-instalar.cmd)" }
        elseif ($l -match '^fecha:') { "fecha: $hoy" }
        else { $l }
    }
    [IO.File]::WriteAllText($receta, (($nuevo -join "`n") + "`n"), (New-Object Text.UTF8Encoding($false)))
    $lineas = $nuevo
    Write-Host "Receta aprobada por $env:USERNAME el $hoy." -ForegroundColor Green
}
# .graphifyignore = bloque ```text de la seccion 2 + exclusiones obligatorias
$excl = @(); $dentro = $false
foreach ($l in $lineas) {
    if ($l -match '^\s*```text') { $dentro = $true; continue }
    if ($dentro -and $l -match '^\s*```') { break }
    if ($dentro) { $t = $l.Trim(); if ($t -and -not $t.StartsWith('#') -and -not $t.StartsWith('<')) { $excl += $t } }
}
$oblig = @('graphify-out/', '.agileforge/vault/codigo/', '.agileforge/vault/.obsidian/', '.agileforge/vault/.agileforge-bitacora.json')
$todo = @('# Generado por el Curador de grafos desde .agileforge/grafos.md - no editar a mano') + (($oblig + $excl) | Select-Object -Unique)
[IO.File]::WriteAllText((Join-Path $Root '.graphifyignore'), (($todo -join "`n") + "`n"))
Write-Host ".graphifyignore: $($todo.Count - 1) exclusiones"

# ---------------------------------------------------------------- 3. grafo de la IA
$env:GRAPHIFY_BACKEND = 'none'   # nunca llamar a un LLM: ni para extraer ni para nombrar comunidades
Paso 3 'Grafo de la IA (solo codigo, 0 tokens)'
if (Test-Path 'graphify-out\graph.json') { $c = G update . } else { $c = G extract . --code-only }
if ($c -ne 0) { Write-Host 'graphify no pudo construir el grafo.' -ForegroundColor Yellow; Fin 1 }
if ((G cluster-only .) -ne 0) { Fin 1 }
if ((G export wiki) -ne 0) { Write-Host 'Aviso: no se pudo generar la wiki.' -ForegroundColor Yellow }

# ---------------------------------------------------------------- 4. vault
Paso 4 'Vault de Obsidian: mapa del codigo'
if ((G export obsidian --dir '.agileforge\vault\codigo') -ne 0) { Fin 1 }

Paso 5 'Vault de Obsidian: bitacora del tablero'
$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) { Write-Host 'No encontre Node.js en el PATH: la bitacora se queda para luego.' -ForegroundColor Yellow }
else {
    $bitArgs = @('plugin\agileforge\scripts\bitacora.mjs', '--vault', '.agileforge\vault')
    $proy = $lineas | Select-String -Pattern '\*\*Proyecto del tablero:\*\*\s*`([A-Za-z0-9_.-]+)`' | Select-Object -First 1
    if ($proy) { $bitArgs += @('--project', $proy.Matches[0].Groups[1].Value); Write-Host "Proyecto del tablero: $($proy.Matches[0].Groups[1].Value)" }
    else { Write-Host 'Proyecto del tablero: el activo (la receta no fija uno)' }
    & node @bitArgs 2>&1 | ForEach-Object { "$_" } | Out-Host
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'La bitacora necesita la web de AgileForge encendida (XAMPP: Apache). Enciendela y vuelve a abrir este archivo.' -ForegroundColor Yellow
    }
}

# ---------------------------------------------------------------- 6. resumen
Paso 6 'Resumen'
Get-Content 'graphify-out\GRAPH_REPORT.md' -Encoding UTF8 -TotalCount 12 | Select-String -Pattern 'nodes|Token cost|files' | ForEach-Object { Write-Host "  $($_.Line.Trim())" }
Write-Host ''
Write-Host '  Grafo de la IA:   graphify-out\  (los agentes: graphify query "..." / path / explain)'
Write-Host '  Bibliografia:     .agileforge\vault\  -> en Obsidian: "Abrir carpeta como vault" y empieza por Inicio.md'
Write-Host '  En Claude Code:   /agileforge:grafo estado  |  /agileforge:grafo actualizar'
Fin 0
