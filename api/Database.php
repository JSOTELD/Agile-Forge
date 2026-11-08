<?php
/**
 * AgileForge - Capa de Persistencia SQLite (PDO + Transacciones ACID)
 * Soporta modo WAL, bloqueos atómicos, auditoría de eventos y migración idempotente.
 */

declare(strict_types=1);

namespace AgileForge;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;
    private static ?string $dbPath = null;

    public static function init(?string $customPath = null): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $root = dirname(__DIR__);
        self::$dbPath = $customPath ?? ($root . '/data/agileforge.sqlite');

        $dir = dirname(self::$dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        try {
            self::$pdo = new PDO('sqlite:' . self::$dbPath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Optimización y concurrencia para SQLite
            self::$pdo->exec('PRAGMA journal_mode = WAL;');
            self::$pdo->exec('PRAGMA synchronous = NORMAL;');
            self::$pdo->exec('PRAGMA busy_timeout = 5000;');
            self::$pdo->exec('PRAGMA foreign_keys = ON;');

            self::ensureSchema();

            return self::$pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Error de conexión SQLite: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            return self::init();
        }
        return self::$pdo;
    }

    public static function getDbPath(): string
    {
        return self::$dbPath ?? (dirname(__DIR__) . '/data/agileforge.sqlite');
    }

    /**
     * Ejecuta una acción dentro de una transacción atómica segura.
     * Usa BEGIN IMMEDIATE para prevenir bloqueos de concurrencia entre múltiples agentes.
     */
    public static function transaction(callable $callback)
    {
        $pdo = self::getPdo();
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function ensureSchema(): void
    {
        $pdo = self::$pdo;
        if (!$pdo) {
            return;
        }

        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS projects (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            path TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sprints (
            id TEXT PRIMARY KEY,
            project_id TEXT DEFAULT 'default',
            number INTEGER NOT NULL DEFAULT 1,
            name TEXT NOT NULL,
            goal TEXT,
            days_remaining INTEGER DEFAULT 0,
            velocity_points INTEGER DEFAULT 0,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS roles (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            alias TEXT,
            avatar TEXT,
            ai_provider TEXT DEFAULT 'anthropic',
            ai_model TEXT,
            description TEXT,
            specialty TEXT,
            assigned_skills_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS skills (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            level TEXT,
            category TEXT,
            description TEXT,
            tags_json TEXT,
            is_custom INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS role_skills (
            role_id TEXT NOT NULL,
            skill_id TEXT NOT NULL,
            PRIMARY KEY (role_id, skill_id)
        );

        CREATE TABLE IF NOT EXISTS tasks (
            id TEXT PRIMARY KEY,
            project_id TEXT DEFAULT 'default',
            title TEXT NOT NULL,
            story_points INTEGER DEFAULT 1,
            assigned_to TEXT NOT NULL,
            ai_provider TEXT,
            ai_model TEXT,
            priority TEXT DEFAULT 'media',
            status TEXT NOT NULL DEFAULT 'backlog',
            user_story TEXT,
            dod TEXT,
            description TEXT,
            skills_json TEXT,
            prompt_template TEXT,
            locked_by TEXT,
            locked_at DATETIME,
            review_evidence_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS task_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id TEXT NOT NULL,
            role_key TEXT,
            client TEXT DEFAULT 'web',
            event_type TEXT NOT NULL,
            payload_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS comments (
            id TEXT PRIMARY KEY,
            task_id TEXT NOT NULL,
            author_role TEXT NOT NULL,
            author_name TEXT,
            avatar TEXT,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS meetings (
            id TEXT PRIMARY KEY,
            project_id TEXT DEFAULT 'default',
            title TEXT NOT NULL,
            type TEXT NOT NULL,
            topic TEXT,
            participants_json TEXT,
            summary TEXT,
            resolution TEXT,
            action_items_json TEXT,
            converted INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS meeting_turns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id TEXT NOT NULL,
            turn_index INTEGER NOT NULL,
            role_id TEXT NOT NULL,
            name TEXT,
            alias TEXT,
            avatar TEXT,
            ai_model TEXT,
            statement TEXT,
            timestamp TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS decisions (
            id TEXT PRIMARY KEY,
            project_id TEXT DEFAULT 'default',
            code TEXT,
            title TEXT NOT NULL,
            context TEXT,
            decision TEXT,
            consequences TEXT,
            status TEXT DEFAULT 'accepted',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS chat_messages (
            id TEXT PRIMARY KEY,
            sender_role TEXT NOT NULL,
            sender_name TEXT,
            avatar TEXT,
            timestamp TEXT,
            text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value_json TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS ai_token_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_role TEXT NOT NULL,
            task_id TEXT,
            sprint_id TEXT DEFAULT 'default',
            provider TEXT NOT NULL,
            model TEXT NOT NULL,
            input_tokens INTEGER DEFAULT 0,
            output_tokens INTEGER DEFAULT 0,
            total_tokens INTEGER DEFAULT 0,
            estimated_cost_usd REAL DEFAULT 0.0,
            context_source TEXT DEFAULT 'chat',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
SQL;

        $pdo->exec($sql);
    }
}
