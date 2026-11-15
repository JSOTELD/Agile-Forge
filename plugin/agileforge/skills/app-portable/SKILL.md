---
name: app-portable
description: Úsala cuando una app de escritorio deba funcionar desde un USB o disco externo en cualquier PC sin instalar nada, con sus datos, secretos y scripts de publicación (PowerShell) dentro del mismo medio.
title: "Apps portátiles (USB o disco externo)"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering","portable","dotnet","powershell","sqlite"]
---

# Apps portátiles (USB o disco externo)

## Cuándo usarla
Diseño, implementación, publicación y pruebas de una app que viaja en un medio extraíble y corre en PCs ajenas.

## Reglas
1. **Nada se instala ni se escribe fuera del medio:** sin registro de Windows, sin `%APPDATA%`, sin `ProgramData`. Todas las rutas se calculan desde `AppContext.BaseDirectory`; nunca rutas absolutas ni letras de unidad guardadas (la letra cambia en cada PC).
2. **Estructura fija en el medio:** `app/<rid>/` (ejecutables por sistema), `datos/` (base y configuración), `respaldos/`, `registros/`, `tmp/` (se vacía al cerrar) y lanzadores en la raíz.
3. **Publicación autocontenida en carpeta:** `dotnet publish -c Release -r <rid> --self-contained true -p:PublishReadyToRun=true`. Evitar el modo de un solo archivo con librerías nativas: las extrae en el `%TEMP%` de la PC. RIDs habituales: `win-x64`, `osx-arm64`, `linux-x64`.
4. **SQLite en medio extraíble:** `journal_mode=DELETE` y `synchronous=FULL` (más seguro que WAL si se desconecta); cerrar conexiones al salir; respaldo automático al cerrar con `VACUUM INTO 'respaldos/…'`; recordar «Expulsar con seguridad».
5. **Secretos portátiles:** baúl cifrado en el medio (AES-256-GCM con clave derivada de una contraseña maestra con Argon2id). No usar DPAPI ni el llavero del sistema: atan los secretos a una PC. Bloqueo automático por inactividad; las claves nunca van a registros.
6. **Navegador para automatizar:** usar Edge o Chrome instalados si existen; si no, un navegador portátil dentro del medio.
7. **Formato del medio:** exFAT (archivos de más de 4 GB y lectura en Windows, macOS y Linux).
8. **macOS y Linux:** la app sin firmar puede quedar en cuarentena en macOS (abrir con clic derecho → Abrir); revisar el permiso de ejecución en Linux.

## Scripts de publicación (PowerShell)
- `Set-StrictMode -Version Latest` y `$ErrorActionPreference = 'Stop'`; rutas desde `$PSScriptRoot`.
- Un script `publicar.ps1 -Destino E:\` que compila cada RID, arma la estructura, copia lo necesario, crea los lanzadores y escribe sumas SHA-256 para verificar la copia.

## Cómo verificar
- Correr la app desde el medio en una PC limpia sin .NET (Windows Sandbox o una máquina virtual).
- Revisar con Process Monitor que no escribe fuera del medio.
- Cambiar la letra de la unidad y volver a abrir; desconectar a la mitad de una escritura y comprobar que la base abre y hay respaldo.
