---
name: database-optimization
description: Úsala cuando diseñes tablas, escribas consultas o veas lentitud o bloqueos en SQLite (PDO, WAL) o en MySQL.
title: "Optimización de bases de datos"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Optimización de bases de datos

## Cuándo usarla
Al crear o migrar tablas, escribir consultas nuevas, o diagnosticar `database is locked` y lentitud.

## Pasos
1. Conexión SQLite: `PRAGMA journal_mode=WAL`, `PRAGMA foreign_keys=ON`, `PRAGMA busy_timeout=5000`, `synchronous=NORMAL`. Una sola conexión por petición.
2. Escrituras con `BEGIN IMMEDIATE ... COMMIT` y `try/catch` con `ROLLBACK`. Así el bloqueo se toma al inicio y no falla a mitad.
3. Transacciones cortas: nada de llamadas HTTP, a IA o `sleep` dentro. Cierra cursores (`closeCursor()` o libera el statement) antes de cualquier llamada larga; un SELECT abierto retiene el lector y bloquea el checkpoint WAL.
4. Siempre sentencias preparadas con parámetros; nunca concatenes valores.
5. Índices solo donde `EXPLAIN QUERY PLAN` muestre `SCAN` sobre tablas grandes en filtros u ordenaciones frecuentes. Índice compuesto en el orden igualdad -> rango -> orden.
6. Evita N+1: una consulta con `JOIN` o `IN (...)` en lugar de un bucle de consultas. Selecciona solo las columnas necesarias; pagina con `LIMIT`.
7. Migraciones idempotentes (`CREATE TABLE IF NOT EXISTS`, comprobar columna antes de `ALTER`) y reversibles en lo posible; haz copia antes de migrar datos reales.
8. Integridad: `NOT NULL`, `CHECK`, `UNIQUE` y claves foráneas en el esquema, no solo en código.

## Anti-patrones
- Transacción abierta durante una llamada de red.
- `SELECT *` en listados.
- Índice por cada columna "por si acaso".
- Manejar el error de bloqueo con reintentos infinitos.

## Cómo verificar
- Salida de `EXPLAIN QUERY PLAN` antes y después.
- `PRAGMA integrity_check` y `PRAGMA foreign_key_check` con resultado `ok` o vacío.
- Prueba con dos escrituras concurrentes sin error de bloqueo. Adjunta todo a `submit_for_review`.
