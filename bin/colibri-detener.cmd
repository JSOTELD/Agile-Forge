@echo off
setlocal
title AgileForge - Detener Colibri
if not exist "D:\colibri\c\coli" (
  echo Colibri no esta instalado en D:\colibri
  pause
  exit /b 1
)
cd /d "D:\colibri"
py -3 c\coli stop 2>nul || python c\coli stop
echo Colibri detenido. La memoria que usaba ya quedo libre.
timeout /t 5 >nul
