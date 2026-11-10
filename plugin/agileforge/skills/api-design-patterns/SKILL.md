---
name: api-design-patterns
description: Úsala cuando crees o cambies un endpoint REST en PHP o una herramienta MCP y necesites contrato, validación y errores consistentes.
title: "Diseño de APIs"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Diseño de APIs

## Cuándo usarla
Al añadir o modificar endpoints de `api/` o herramientas del servidor MCP.

## Pasos
1. Revisa `team/contrato-api.md`: el contrato manda. Cambia el contrato primero si hace falta.
2. Recursos en plural y verbos HTTP correctos: GET lee sin efectos, POST crea, PUT/PATCH actualiza, DELETE borra. Nunca cambies estado en un GET.
3. Valida toda entrada en el borde: tipos, rangos, longitud, valores de enum. Rechaza lo desconocido con 422 y un mensaje que diga qué campo falla.
4. Códigos coherentes: 200/201 éxito, 400 petición mal formada, 401 sin credencial, 403 sin permiso (por ejemplo token de solo lectura), 404, 409 conflicto de estado, 422 validación.
5. Errores siempre como JSON `{ "error": "mensaje accionable" }`; sin trazas ni rutas internas.
6. Listados con paginación (`limit`, `offset`) y modo compacto por defecto; el detalle se pide aparte.
7. Escrituras idempotentes cuando sea posible y protegidas por transacción `BEGIN IMMEDIATE`.
8. En MCP: nombres en snake_case, descripción de una línea, esquema de entrada estricto, `isError: true` ante fallos.
9. Nunca leas permisos de `$_REQUEST`; la autoridad viene de la sesión o el token.

## Anti-patrones
- Respuestas con formas distintas según el caso.
- Devolver 200 con `{ok:false}`.
- Endpoints que aceptan "cualquier cosa" y la guardan.
- Romper clientes existentes sin versionar o avisar.

## Cómo verificar
- Pega 3 llamadas `curl` (éxito, validación 422, permiso 403) con sus códigos y cuerpos recortados.
- `php -l` limpio.
- Indica qué fila del contrato cambió, si alguna. Incluye todo en `submit_for_review`.
