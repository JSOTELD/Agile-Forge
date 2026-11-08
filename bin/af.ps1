# AgileForge CLI Bridge para Claude Code en VS Code

param (
    [Parameter(Position = 0)]
    [string]$Command = "tasks",

    [Parameter(Position = 1)]
    [string]$Arg1 = "",

    [Parameter(Position = 2, ValueFromRemainingArguments = $true)]
    [string[]]$Arg2 = @()
)

$ErrorActionPreference = "Stop"

# Auto-deteccion de directorio base (soporta Open-to-code o renombre a AgileForge)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BaseDir = Split-Path -Parent $ScriptDir
$DataFile = Join-Path $BaseDir "data\team.json"
if (-not (Test-Path $DataFile)) {
    if (Test-Path "D:\xampp\htdocs\AgileForge\data\team.json") {
        $DataFile = "D:\xampp\htdocs\AgileForge\data\team.json"
        $BaseDir = "D:\xampp\htdocs\AgileForge"
    } elseif (Test-Path "D:\xampp\htdocs\Open-to-code\data\team.json") {
        $DataFile = "D:\xampp\htdocs\Open-to-code\data\team.json"
        $BaseDir = "D:\xampp\htdocs\Open-to-code"
    }
}

if ($env:AGILEFORGE_API_URL) {
    $ApiUrl = $env:AGILEFORGE_API_URL
} else {
    $ApiUrl = "http://localhost/Open-to-code/api/tasks.php"
}

# Cargar token de autenticacion desde .env o variable de entorno
$ApiToken = $env:AGILEFORGE_API_TOKEN
$EnvFile = Join-Path $BaseDir ".env"
if (-not $ApiToken -and (Test-Path $EnvFile)) {
    Get-Content $EnvFile | ForEach-Object {
        if ($_ -match '^\s*AGILEFORGE_API_TOKEN\s*=\s*(.+)$') {
            $ApiToken = $matches[1].Trim().Trim('"').Trim("'")
        }
    }
}

function Get-AfHeaders {
    $headers = @{}
    if ($ApiToken) {
        $headers["Authorization"] = "Bearer $ApiToken"
    }
    return $headers
}

function Invoke-AfApi {
    param ([hashtable]$Body)
    try {
        $json = $Body | ConvertTo-Json -Depth 5
        $res = Invoke-RestMethod -Uri $ApiUrl -Method Post -Body $json -ContentType "application/json" -Headers (Get-AfHeaders) -TimeoutSec 5
        return $res
    } catch {
        Write-Warning "Error comunicando con AgileForge API ($ApiUrl): $_"
        return $null
    }
}

function Get-AfData {
    try {
        $res = Invoke-RestMethod -Uri $ApiUrl -Method Get -Headers (Get-AfHeaders) -TimeoutSec 3
        if ($res -and $res.tasks) { return $res }
    } catch {
        Write-Warning "Fallo consulta a AgileForge API: $_"
    }

    if (Test-Path $DataFile) {
        $raw = Get-Content $DataFile -Raw -Encoding UTF8
        return $raw | ConvertFrom-Json
    }
    throw "No se pudo conectar a AgileForge ni leer data\team.json"
}

$cmd = $Command.ToLower()

if ($cmd -in @("tasks", "list", "backlog")) {
    $data = Get-AfData
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host "AgileForge - Sprint $($data.sprint.number): $($data.sprint.name)" -ForegroundColor White
    Write-Host "Meta: $($data.sprint.goal)" -ForegroundColor Gray
    Write-Host "Workspace: $($data.active_workspace)" -ForegroundColor Yellow
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host ""
    
    foreach ($t in $data.tasks) {
        $color = switch ($t.status) {
            "completada" { "Green" }
            "en_progreso" { "Yellow" }
            "revision" { "Magenta" }
            default { "DarkGray" }
        }
        $statusLabel = switch ($t.status) {
            "completada" { "[HECHA]    " }
            "en_progreso" { "[PROGRESO] " }
            "revision" { "[REVISION] " }
            default { "[BACKLOG]  " }
        }
        Write-Host $statusLabel -NoNewline -ForegroundColor $color
        Write-Host "$($t.id) " -NoNewline -ForegroundColor White
        Write-Host "($($t.story_points) SP) " -NoNewline -ForegroundColor Cyan
        Write-Host "[$($t.priority.ToUpper())] " -NoNewline -ForegroundColor DarkYellow
        Write-Host "$($t.title)" -ForegroundColor White
        Write-Host "           Rol: $($t.assigned_to) | DoD: $($t.dod)" -ForegroundColor DarkGray
    }
    Write-Host ""
} elseif ($cmd -in @("get", "prompt", "pull")) {
    if (-not $Arg1) {
        Write-Host "Uso: af get [ID_HISTORIA] (ej: af get US-101)" -ForegroundColor Red
        exit 1
    }
    $data = Get-AfData
    $task = $data.tasks | Where-Object { $_.id -eq $Arg1 }
    if (-not $task) {
        Write-Host "No se encontro la tarea $Arg1" -ForegroundColor Red
        exit 1
    }
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host "PROMPT DE MISION: $($task.id) - $($task.title)" -ForegroundColor White
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host $task.prompt_template -ForegroundColor Green
    Write-Host "==========================================================" -ForegroundColor Cyan
} elseif ($cmd -in @("start", "progreso")) {
    if (-not $Arg1) { Write-Host "Uso: af start [ID]"; exit 1 }
    $res = Invoke-AfApi @{ action = "update_status"; task_id = $Arg1; status = "en_progreso" }
    Write-Host "Tarea $Arg1 movida a 'EN PROGRESO' en AgileForge" -ForegroundColor Yellow
} elseif ($cmd -in @("review", "revision")) {
    if (-not $Arg1) { Write-Host "Uso: af review [ID]"; exit 1 }
    $res = Invoke-AfApi @{ action = "update_status"; task_id = $Arg1; status = "revision" }
    Write-Host "Tarea $Arg1 movida a 'EN REVISION (DoD)' en AgileForge" -ForegroundColor Magenta
} elseif ($cmd -in @("done", "completar")) {
    if (-not $Arg1) { Write-Host "Uso: af done [ID]"; exit 1 }
    $res = Invoke-AfApi @{ action = "update_status"; task_id = $Arg1; status = "completada" }
    Write-Host "Tarea $Arg1 marcada como 'COMPLETADA' con DoD verde en AgileForge" -ForegroundColor Green
} elseif ($cmd -in @("ask", "chat")) {
    $role = if ($Arg1) { $Arg1 } else { "project_manager" }
    $msg = if ($Arg2.Count -gt 0) { $Arg2 -join ' ' } else { "Analisis del sprint" }
    $res = Invoke-AfApi @{ action = "send_chat"; target_role = $role; message = $msg }
    if ($res -and $res.messages) {
        $last = $res.messages[-1]
        Write-Host ""
        Write-Host "$($last.sender_name):" -ForegroundColor Cyan
        Write-Host $last.text -ForegroundColor White
        Write-Host ""
    } else {
        Write-Host "No se pudo obtener respuesta del agente." -ForegroundColor Red
    }
} elseif ($cmd -in @("ws", "workspace")) {
    if ($Arg1) {
        $res = Invoke-AfApi @{ action = "update_workspace"; workspace = $Arg1 }
        Write-Host "Workspace actualizado a: $Arg1" -ForegroundColor Green
    } else {
        $data = Get-AfData
        Write-Host "Workspace activo: $($data.active_workspace)" -ForegroundColor Yellow
    }
} else {
    Write-Host "Comandos de AgileForge CLI:" -ForegroundColor White
    Write-Host "  af tasks               - Lista el backlog y estado del sprint actual"
    Write-Host "  af get [US-ID]         - Obtiene el prompt completo con skills y DoD"
    Write-Host "  af start [US-ID]       - Mueve la tarea a 'En Progreso'"
    Write-Host "  af review [US-ID]      - Mueve la tarea a 'En Revision'"
    Write-Host "  af done [US-ID]        - Marca la tarea como 'Completada'"
    Write-Host "  af ask [rol] [msg]     - Consulta al Scrum Master o Director Creativo"
    Write-Host "  af ws [ruta]           - Consulta o cambia la carpeta de trabajo"
}
