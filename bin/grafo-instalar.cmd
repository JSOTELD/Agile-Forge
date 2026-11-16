@echo off
rem AgileForge - Grafos: instala graphify si falta y construye el grafo de la IA y el vault de Obsidian.
rem Doble clic, o:  bin\grafo-instalar.cmd -SoloInstalar
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0grafo-instalar.ps1" %*
