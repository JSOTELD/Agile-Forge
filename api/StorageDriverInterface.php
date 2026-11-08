<?php
/**
 * AgileForge - StorageDriverInterface
 * Abstracción de capa de almacenamiento para desacoplar el motor de base de datos
 * (SQLite por defecto, con extensibilidad nativa a PostgreSQL o MySQL).
 */

declare(strict_types=1);

namespace AgileForge;

use PDO;

interface StorageDriverInterface
{
    /**
     * Obtiene la instancia activa de PDO configurada con los parámetros óptimos del motor.
     */
    public function getPdo(): PDO;

    /**
     * Ejecuta un callback dentro de una transacción transaccional ACID.
     */
    public function transaction(callable $callback): mixed;

    /**
     * Asegura que el esquema de tablas e índices exista de forma idempotente.
     */
    public function ensureSchema(): void;

    /**
     * Identificador del driver (ej: 'sqlite', 'pgsql', 'mysql').
     */
    public function getDriverName(): string;
}

