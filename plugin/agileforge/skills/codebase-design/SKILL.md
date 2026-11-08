---
name: codebase-design
title: "Clean Architecture & System Design"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Clean Architecture & System Design

> **Especialidad:** `codebase-design` | **Categoría:** engineering | **Nivel:** Principal | **Estado:** ACTIVE

Habilidad técnica especializada dentro del catálogo del ecosistema AgileForge.

## Clean Architecture y Diseño de Sistemas
1. **Separación de Responsabilidades**: Las vistas no contienen lógica de persistencia; los controladores despachan a repositorios; las entidades representan el dominio.
2. **Bajo Acoplamiento y Alta Cohesión**: Diseñar interfaces y contratos desacoplados que permitan cambiar de motor de almacenamiento o framework sin reescribir la lógica central.
3. **Transaccionalidad Defensiva**: Toda mutación en base de datos debe orquestarse dentro de transacciones atómicas con rollback garantizado.

## Criterio de Verificación
- Todo entregable generado con esta skill debe satisfacer la Definition of Done (DoD).
- La evidencia debe registrarse mediante la herramienta MCP `submit_for_review`.
