---
name: context-sentinel
description: Úsala cuando vayas a explorar un repositorio o el tablero, elegir modelo y esfuerzo, o decidir si delegar en un subagente para no llenar el hilo principal.
title: "Centinela de contexto y alcance"
category: agile
level: Principal
status: active
tags: ["AgileForge","agile"]
---

# Centinela de contexto y alcance

Economía de tokens en la entrada: el contexto es un presupuesto finito. Complementa `lean-compact` (qué escribir).

## Cuándo usarla
Al empezar cualquier tarea, antes de leer archivos o consultar el tablero, y cuando el hilo ya es largo.

## Reglas
1. **Define el alcance primero:** en una frase, qué archivos y qué datos hacen falta. Si no puedes nombrarlos, busca con `grep`/glob antes de leer.
2. **Leer lo mínimo:** busca el símbolo, lee solo el rango de líneas (`offset`/`limit`); no abras archivos enteros ni el repo "para orientarte". Un archivo ya leído no se relee.
3. **Tablero:** empieza con `get_overview` (o `ver_tablero`) y no con listados. `list_tasks` en modo compacto (por defecto), con filtros por estado o sprint; `get_task` solo de la historia que vas a trabajar. No cargues el estado completo, todas las reuniones ni el historial de actividad; usa `limit`.
4. **Modelo y esfuerzo por tarea (ADR-003):** director opus/high, chief-architect opus/high, qa-security sonnet/high, backend y frontend sonnet/medium, creative-director y legal-compliance sonnet/low, project-manager haiku/low, observador haiku/low. Usa lo más barato que cumpla la tarea; sube de nivel solo si falla por razonamiento.
5. **Delegar para proteger el hilo:** si la tarea implica leer muchos archivos, buscar en todo el repo o producir salida larga, delega en un subagente (herramienta Agent con `agileforge:<agente>`) y pide un resumen ≤ 150 palabras con rutas. No delegues lo trivial ni dupliques el trabajo.
   Paralelo: lanza subagentes independientes en el mismo turno.
6. **Corta pronto:** si tras 2-3 lecturas no hay respuesta, para y cambia de estrategia o pregunta.
7. **Salida de herramientas:** limita con `head`, `grep -c`, `--stat`; evita listados recursivos de `vendor/` o `node_modules/`.

## Anti-patrones
- `ls -R`, `cat` de archivos grandes, listar todas las historias para buscar una.
- Usar opus/high para renombrar variables.
- Mantener en el hilo principal exploraciones ya resumidas.

## Cómo verificar
- Declara en el informe qué leíste (archivos/rangos y llamadas MCP), sin lecturas innecesarias.
- Si delegaste, cita el resumen recibido. Incluye esa línea en `submit_for_review`.
