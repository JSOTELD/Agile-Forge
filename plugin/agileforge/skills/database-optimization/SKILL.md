---
name: database-optimization
title: "SQL Performance & Transacciones"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# SQL Performance & Transacciones

> **Especialidad:** `database-optimization` | **Categoría:** engineering | **Nivel:** Principal | **Estado:** ACTIVE

Habilidad técnica especializada dentro del catálogo del ecosistema AgileForge.

## Rendimiento SQL y Transacciones ACID
1. **Modo WAL en SQLite**: Activar PRAGMA journal_mode = WAL y synchronous = NORMAL para concurrencia masiva.
2. **Prevención de Bloqueos**: Configurar busy_timeout = 5000ms y usar transacciones atómicas con sentencias preparadas (PDO).

## Criterio de Verificación
- Todo entregable generado con esta skill debe satisfacer la Definition of Done (DoD).
- La evidencia debe registrarse mediante la herramienta MCP `submit_for_review`.
