---
description: Lanza al Curador de grafos (v0, experimental). Con graphify mantiene el grafo que consultan los agentes (graphify-out/) y un vault de Obsidian que sirve de bibliografía del proyecto (código, historias, decisiones, reuniones y fuentes).
argument-hint: "[estado | construir | actualizar | bitacora | consultar \"<pregunta>\" | fuente <url> [US-…]]"
---

Acción pedida: `$ARGUMENTS` (vacío = `estado`).

1. `mcp__plugin_agileforge_board__get_overview` una vez para saber cuál es el proyecto activo.
2. Comprueba la receta `.agileforge/grafos.md` en la raíz del proyecto abierto:
   - Acción `estado`, o `consultar` con `graphify-out/graph.json` ya creado → no hace falta receta: delega (paso 3).
   - No existe → delega igual: el curador propondrá un borrador desde la plantilla y se detendrá.
   - Existe con `estado: borrador` y la acción construye o escribe (`construir`, `actualizar`, `bitacora`, `fuente`) → dile a David que la revise y la cambie a `estado: aprobada`, y para.
3. Delega con la herramienta Agent en `agileforge:graph-curator` (sin pasar `model` ni `effort`). Prompt:
   "Proyecto activo: <nombre> (id <id>). Acción: <acción y argumentos, o 'estado'>. Receta: `.agileforge/grafos.md`. Plantilla por si falta: `${CLAUDE_PLUGIN_ROOT}/templates/grafos.md`. Bitácora: `${CLAUDE_PLUGIN_ROOT}/scripts/bitacora.mjs`. Sigue tus reglas duras: sin receta aprobada no construyes; con motor `ninguno`, `GRAPHIFY_BACKEND=none` en cada comando de graphify; primero `export obsidian` y después la bitácora; no instalas nada ni tocas código, configuración ni git. Termina con tu informe final."
4. Tú no corriges lo que encuentre ni decides sobre los hallazgos: solo transmites el resultado.
5. Muestra el informe del curador tal cual. Si recomienda algo que cambia la configuración del proyecto (instalar graphify o su hook, editar `.gitignore`, aprobar la receta), termina con: "Requiere decisión de David".
