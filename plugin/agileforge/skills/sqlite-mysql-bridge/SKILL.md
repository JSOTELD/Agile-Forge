---
name: sqlite-mysql-bridge
description: Úsala cuando escribas SQL que deba funcionar tanto en SQLite local como en MySQL/MariaDB de producción, o migres entre ambos.
title: "Puente SQLite y MySQL"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Puente SQLite y MySQL

## Cuándo usarla
Cuando el mismo código corre sobre SQLite (desarrollo, XAMPP) y MySQL/MariaDB (hosting), o al portar un esquema.

## Pasos
1. Centraliza las diferencias en una capa pequeña (`db.php`) que reciba el driver (`sqlite`/`mysql`) y exponga helpers; el resto del código no pregunta por el motor.
2. Diferencias frecuentes:
   - Autoincremento: `INTEGER PRIMARY KEY AUTOINCREMENT` vs `INT AUTO_INCREMENT PRIMARY KEY`.
   - Upsert: `INSERT ... ON CONFLICT(col) DO UPDATE` vs `ON DUPLICATE KEY UPDATE`.
   - Fechas: `datetime('now')` vs `NOW()`; guarda UTC en ISO 8601 desde PHP y evita funciones del motor.
   - Concatenar: `||` vs `CONCAT()`.
   - Booleanos: enteros 0/1 en ambos.
   - `INSERT OR IGNORE` vs `INSERT IGNORE`.
3. Transacciones: SQLite usa `BEGIN IMMEDIATE`; en MySQL equivale a `START TRANSACTION` más `SELECT ... FOR UPDATE` sobre la fila a proteger.
4. Tipos: SQLite es de tipado flexible; declara tipos estrictos y valida en PHP para que MySQL no rechace datos que SQLite aceptó. Longitud de `VARCHAR` explícita.
5. Collation e igualdad de texto: SQLite distingue mayúsculas en `=`, MySQL (utf8mb4_general_ci) no. Normaliza en la aplicación.
6. Migraciones en archivos separados por motor cuando el SQL difiera; un test las ejecuta ambas.
7. Datos: exporta/importa con PDO fila a fila en lotes y transacción, y compara conteos por tabla.

## Anti-patrones
- Funciones de fecha propias del motor dentro de consultas.
- `ALTER TABLE` complejo asumiendo que SQLite lo soporta (no soporta todo).
- Asumir orden de resultados sin `ORDER BY`.

## Cómo verificar
- Ejecuta el esquema y las consultas clave en ambos motores y pega el resultado.
- Tabla de conteos por tabla origen vs destino tras migrar. Úsalo como evidencia en `submit_for_review`.
