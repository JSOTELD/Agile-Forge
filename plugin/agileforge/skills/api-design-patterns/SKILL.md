---
name: api-design-patterns
title: "RESTful API & Idempotencia"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# RESTful API & Idempotencia

> **Especialidad:** `api-design-patterns` | **Categoría:** engineering | **Nivel:** Principal | **Estado:** ACTIVE

Habilidad técnica especializada dentro del catálogo del ecosistema AgileForge.

## Diseño de APIs RESTful e Idempotencia
1. **Códigos de Estado HTTP Rigurosos**:
   - 200 OK / 201 Created para éxito.
   - 400 Bad Request / 422 Unprocessable Entity para validaciones fallidas.
   - 401 Unauthorized / 403 Forbidden para accesos no autenticados o externos.
   - 415 Unsupported Media Type si no se envía application/json.
2. **Idempotencia**: Garantizar que reintentos de peticiones no dupliquen recursos en la base de datos.

## Criterio de Verificación
- Todo entregable generado con esta skill debe satisfacer la Definition of Done (DoD).
- La evidencia debe registrarse mediante la herramienta MCP `submit_for_review`.
