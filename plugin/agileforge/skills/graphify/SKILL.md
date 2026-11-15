---
name: graphify
title: "Graphify (alias antiguo)"
description: "Alias antiguo (ADR-005). Para consultar el grafo de conocimiento de graphify usa la skill knowledge-graph; para construirlo o actualizarlo, /agileforge:grafo; para mapear dependencias sin grafo, dependency-mapping."
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Graphify (alias antiguo)

Este identificador se conserva por compatibilidad: en la base, `graphify` se reasigna a `dependency-mapping` (ADR-005).

- **Consultar el grafo** (`graphify-out/graph.json`): skill `knowledge-graph` (`graphify query`, `graphify path`, `graphify explain`).
- **Construirlo, actualizarlo y exportar la bibliografía a Obsidian:** el Curador de grafos, `/agileforge:grafo`.
- **Sin grafo:** skill `dependency-mapping` (grep y lectura de imports).
