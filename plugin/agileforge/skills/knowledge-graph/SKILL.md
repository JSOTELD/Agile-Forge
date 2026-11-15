---
name: knowledge-graph
description: Úsala antes de buscar o leer archivos en un proyecto que tenga graphify-out/graph.json, para responder preguntas de código, dependencias o impacto consultando el grafo de conocimiento en vez de recorrer el repositorio.
title: "Grafo de conocimiento (graphify)"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering","graphify"]
---

# Grafo de conocimiento (graphify)

El grafo de la IA lo construye y mantiene el **Curador de grafos** (`/agileforge:grafo`). Tú solo lo consultas: es más barato que leer archivos y te dice qué leer.

## Cuándo usarla
Al empezar una historia, antes de `grep`/glob o de abrir archivos; al evaluar el impacto de un cambio; al buscar quién usa un símbolo o cómo se conectan dos módulos.

## Pasos
1. ¿Existe `graphify-out/graph.json`? Si no, sigue con `dependency-mapping` (grep) y, si el proyecto es grande, sugiere `/agileforge:grafo construir`.
2. **Frescura:** si tu cambio o el último commit es más nuevo que `graphify-out/GRAPH_REPORT.md`, el grafo puede no tener lo último: úsalo como mapa, pero confirma en el código.
3. Consulta con el CLI (ejecuta desde la raíz del proyecto):
   - Pregunta abierta: `graphify query "<pregunta>" --budget 1500`
   - Cómo se conectan A y B: `graphify path "A" "B"`
   - Qué es y con qué se relaciona X: `graphify explain "X"`
   - Código de salida `3` = nombre ambiguo (elige un candidato de la lista); `4` = no existe ese nodo.
4. Lee **solo** los archivos y líneas que señala el grafo (`source_location`), con `offset`/`limit`.
5. Visión general sin CLI: `graphify-out/wiki/index.md` (un artículo por comunidad) o las secciones "God Nodes" y "Surprising Connections" de `GRAPH_REPORT.md` (Grep con `-A`, no lo leas entero).

## Reglas
- No reconstruyas ni modifiques `graphify-out/`: es trabajo del Curador. Si falta algo, dilo en tu entrega ("grafo desactualizado").
- Distingue `EXTRACTED` (está en el código) de `INFERRED` (lo dedujo graphify): un `INFERRED` se confirma leyendo el código antes de basar un cambio en él.
- Sin `graphify` instalado: usa la wiki o el informe con Read; nunca instales herramientas por tu cuenta.

## Cómo verificar
- En `submit_for_review` (o en tu informe), anota la consulta usada y los nodos que confirmaste en el código (`ruta:línea`).
