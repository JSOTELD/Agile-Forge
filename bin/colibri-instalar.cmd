@echo off
setlocal EnableExtensions
title AgileForge - Instalar Colibri + Qwen3.6-35B-A3B
rem ---------------------------------------------------------------------------
rem Instala Colibri (https://github.com/JustVugg/colibri) en D:\colibri y descarga
rem el modelo Qwen3.6-35B-A3B (23 GB) en D:\colibri-models.
rem - Usa la version revisada del repositorio (commit SHA fijo).
rem - El instalador oficial (START-HERE.bat) elige CUDA para la RTX 3050 y verifica
rem   el motor descargado con el SHA256SUMS de la release.
rem - Al terminar, Colibri queda corriendo en segundo plano en http://127.0.0.1:8000
rem ---------------------------------------------------------------------------
set "SHA=0a5504a1dc64f86fcd0fe7db298b0448c730e7e6"
set "DEST=D:\colibri"
set "MODELS=D:\colibri-models"

if exist "%DEST%\START-HERE.bat" goto :setup

echo.
echo [1/2] Descargando Colibri (version %SHA:~0,7%) desde GitHub...
powershell -NoProfile -Command ^
  "$ErrorActionPreference='Stop';" ^
  "$zip=Join-Path $env:TEMP 'colibri-%SHA%.zip'; $x=Join-Path $env:TEMP 'colibri-extract';" ^
  "Invoke-WebRequest -UseBasicParsing -Uri 'https://github.com/JustVugg/colibri/archive/%SHA%.zip' -OutFile $zip;" ^
  "if (Test-Path $x) { Remove-Item $x -Recurse -Force };" ^
  "Expand-Archive -LiteralPath $zip -DestinationPath $x -Force;" ^
  "Move-Item -LiteralPath (Join-Path $x 'colibri-%SHA%') -Destination '%DEST%';" ^
  "Remove-Item $x -Recurse -Force; Remove-Item $zip -Force"
if errorlevel 1 (
  echo.
  echo No se pudo descargar o descomprimir Colibri. Revisa tu conexion e intentalo de nuevo.
  pause
  exit /b 1
)

:setup
if not exist "%MODELS%" mkdir "%MODELS%"
echo.
echo [2/2] Instalando el motor y descargando Qwen3.6-35B-A3B (23 GB) en %MODELS% ...
echo       Puedes seguir trabajando mientras descarga. Si se corta, vuelve a abrir este archivo y continua.
echo.
cd /d "%DEST%"
call START-HERE.bat --yes --model qwen36-35b --dir "%MODELS%" --background --no-browser
if errorlevel 1 exit /b 1

echo.
echo Listo. Colibri corre en segundo plano en http://127.0.0.1:8000
echo En AgileForge: boton "IA local" ^> Motor IA ^> elige "Colibri" en el selector de modelo.
echo Para detenerlo: bin\colibri-detener.cmd  ^|  Para iniciarlo otro dia: bin\colibri-iniciar.cmd
echo.
pause
