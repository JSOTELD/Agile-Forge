@echo off
title Crear acceso directo "Colibri IA"
rem Crea en tu Escritorio el acceso directo "Colibri IA": enciende Colibri, lo abre en una
rem ventana propia y lo apaga al cerrarla (salvo que AgileForge lo este usando).
powershell -NoProfile -ExecutionPolicy Bypass -File "C:\Users\jsoto\AppData\Local\colibri\agileforge\crear-acceso-directo.ps1"
echo.
echo Listo: busca "Colibri IA" en tu Escritorio.
timeout /t 6 >nul
