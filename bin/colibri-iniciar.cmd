@echo off
setlocal
title AgileForge - Iniciar Colibri
if not exist "D:\colibri\START-HERE.bat" (
  echo Colibri no esta instalado. Ejecuta primero bin\colibri-instalar.cmd
  pause
  exit /b 1
)
cd /d "D:\colibri"
call START-HERE.bat --background --no-browser
if errorlevel 1 exit /b 1
echo Colibri esta corriendo en segundo plano en http://127.0.0.1:8000
timeout /t 5 >nul
