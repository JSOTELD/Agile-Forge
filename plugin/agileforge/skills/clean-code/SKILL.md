---
name: clean-code
description: Úsala cuando escribas o revises código PHP o TypeScript y quieras que sea legible, pequeño y sin relleno típico de código generado por IA.
title: "Código limpio y sin relleno"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Código limpio y sin relleno

## Cuándo usarla
En toda implementación y en cada revisión antes de `submit_for_review`. Absorbe la antigua `antislop-code`.

## Pasos
1. Nombres que dicen la intención (`reservarHistoria`, no `procesar`); sin abreviaturas crípticas.
2. Funciones pequeñas con una sola tarea (idealmente < 30 líneas), pocos parámetros (≤ 3; si no, un objeto), retorno temprano en vez de `if` anidados.
3. Tipos y contratos: `declare(strict_types=1)`, tipos de parámetros y retorno en PHP; `strict` en TypeScript, sin `any`.
4. Errores explícitos: lanza excepciones con mensaje accionable; no tragues errores con `catch` vacío ni devuelvas `false` ambiguo.
5. Cierra recursos: statements y cursores PDO liberados antes de llamadas largas; transacciones cortas.
6. DRY con criterio: extrae cuando haya 3 repeticiones reales, no antes.
7. Elimina el relleno de IA:
   - comentarios que repiten el código ("// incrementa i");
   - `try/catch` defensivos que nada manejan;
   - capas, interfaces o parámetros "por si acaso";
   - código muerto, `TODO` sin dueño, `console.log`/`var_dump` olvidados;
   - validaciones imposibles de alcanzar;
   - README o docs no pedidos.
8. Comentarios solo para el porqué (decisión, trampa, límite), nunca para el qué.
9. Cambios mínimos: toca solo lo que exige la historia; no reformatees archivos ajenos.

## Anti-patrones
- Reescribir un archivo entero para cambiar tres líneas.
- Clases con estado global; funciones de 200 líneas; números mágicos sin constante.

## Cómo verificar
- `php -l` / `tsc --noEmit` / linter del proyecto en verde.
- `git diff --stat` que muestra solo archivos esperados.
- `grep -nE "var_dump|console\.log|TODO|catch *\(.*\) *\{ *\}"` sin hallazgos nuevos.
- Pega los tres resultados recortados en `submit_for_review`.
