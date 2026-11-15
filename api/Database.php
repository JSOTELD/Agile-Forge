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
    private static int $txDepth = 0;

    public static function init(?string $customPath = null): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $root = dirname(__DIR__);
        self::$dbPath = $customPath ?? self::configuredPath($root);

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
            self::$pdo->exec('PRAGMA busy_timeout = 15000;');
            self::$pdo->exec('PRAGMA foreign_keys = ON;');

            self::ensureSchema();

            return self::$pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Error de conexión SQLite: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /** Ruta de la base: AGILEFORGE_DB_PATH (relativa a la raíz de la app o absoluta) o data/agileforge.sqlite. */
    private static function configuredPath(string $root): string
    {
        // La variable del proceso manda sobre el .env (útil para pruebas: AGILEFORGE_DB_PATH=/tmp/x.sqlite php bin/migrate.php)
        $val = (string)(getenv('AGILEFORGE_DB_PATH') ?: '');
        if ($val === '') {
            $val = class_exists(Bootstrap::class, false) ? Bootstrap::env('AGILEFORGE_DB_PATH') : (string)($_ENV['AGILEFORGE_DB_PATH'] ?? '');
        }
        $val = trim($val);
        if ($val === '' || str_contains($val, "\0")) {
            return $root . '/data/agileforge.sqlite';
        }
        $isAbs = str_starts_with($val, '/') || str_starts_with($val, '\\') || (bool)preg_match('#^[A-Za-z]:[\\\\/]#', $val);
        return $isAbs ? $val : $root . '/' . $val;
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
        if (self::$txDepth > 0 || $pdo->inTransaction()) {
            return $callback($pdo);
        }

        // BEGIN IMMEDIATE toma el bloqueo de escritura al inicio: evita "database is locked"
        // cuando otra petición escribe entre la lectura y la escritura de esta transacción.
        $pdo->exec('BEGIN IMMEDIATE');
        self::$txDepth++;
        try {
            $result = $callback($pdo);
            $pdo->exec('COMMIT');
            self::$txDepth--;
            return $result;
        } catch (\Throwable $e) {
            self::$txDepth--;
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable $ignored) {
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

        CREATE TABLE IF NOT EXISTS agent_memories (
            id TEXT PRIMARY KEY,
            project_id TEXT NOT NULL DEFAULT 'default',
            role_id TEXT NOT NULL,
            node_type TEXT NOT NULL DEFAULT 'progress',
            title TEXT NOT NULL,
            content_text TEXT NOT NULL,
            importance_score INTEGER NOT NULL DEFAULT 5,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_agent_memories_lookup 
        ON agent_memories(project_id, role_id, is_active, importance_score);
SQL;

        $pdo->exec($sql);

        // Migraciones aditivas: columnas nuevas en tablas existentes
        self::addColumnIfMissing($pdo, 'chat_messages', 'ai_model', 'TEXT');
        self::addColumnIfMissing($pdo, 'chat_messages', 'ai_provider', 'TEXT');
        self::addColumnIfMissing($pdo, 'chat_messages', 'meta_json', 'TEXT');
        // H7: una reunión cerrada (close_meeting / conclude) no admite más turnos
        self::addColumnIfMissing($pdo, 'meetings', 'closed_at', 'DATETIME');
        // Fecha de cierre del sprint (UTC): los días restantes se calculan con el calendario en vez de quedar fijos
        self::addColumnIfMissing($pdo, 'sprints', 'ends_at', 'DATETIME');
        // Presupuesto de costos por sprint (USD)
        self::addColumnIfMissing($pdo, 'sprints', 'budget_usd', 'REAL DEFAULT 0.0');
        self::backfillSprintEnds($pdo);
        self::repairBoardData($pdo);

        self::runDataMigrations($pdo);
    }

    /** Versión objetivo del esquema de datos (settings.schema_version). */
    private const SCHEMA_TARGET = 5;

    /**
     * Migraciones de datos versionadas (se ejecutan una sola vez por versión).
     * Antes de aplicarlas sobre una base con datos se guarda una copia en data/backup/ (H7).
     */
    private static function runDataMigrations(PDO $pdo): void
    {
        self::syncModelCatalog($pdo);

        $target = self::SCHEMA_TARGET;
        if (self::currentSchemaVersion($pdo) < $target) {
            // Un solo proceso migra y hace la copia: los demás esperan y vuelven a leer la versión (sin copias duplicadas)
            $lock = @fopen(sys_get_temp_dir() . '/agileforge_migrate_' . md5((string)self::$dbPath) . '.lock', 'c');
            if ($lock !== false) {
                @flock($lock, LOCK_EX);
            }
            try {
                $version = self::currentSchemaVersion($pdo);
                if ($version < $target) {
                    self::backupBeforeMigration($pdo, $target);
                    self::applyDataMigrations($pdo, $version, $target);
                }
            } finally {
                if ($lock !== false) {
                    @flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }
        }

        // H3: la tabla skills sigue a los SKILL.md (alta de nuevas, baja de eliminadas); solo trabaja si cambió la carpeta
        try {
            self::syncSkillCatalog($pdo, false);
        } catch (\Throwable $e) {
            error_log('AgileForge sincronización de skills: ' . $e->getMessage());
        }
    }

    private static function currentSchemaVersion(PDO $pdo): int
    {
        $row = $pdo->query("SELECT value_json FROM settings WHERE key = 'schema_version'")->fetch();
        return $row ? (int)json_decode($row['value_json'], true) : 1;
    }

    private static function applyDataMigrations(PDO $pdo, int $version, int $target): void
    {
        $pdo->exec('BEGIN IMMEDIATE');
        self::$txDepth++; // PDO no detecta un BEGIN lanzado con exec(): se lleva la cuenta aquí
        try {
            if ($version < 2) {
                self::seedDirectorRole($pdo);
                self::cleanAiEngineSetting($pdo);
                self::repairChatOrder($pdo);
            }
            // v3: catálogo de modelos actualizado (Claude 5.5, Gemini 3): reasigna modelos retirados
            self::normalizeRoleEngines($pdo);
            if ($version < 4) {
                // v4 (ADR-005): skills 34 → 27 (reasigna IDs) y modelo del motor global con el catálogo vigente
                self::remapSkillIds($pdo);
                self::normalizeGlobalEngine($pdo);
            }
            if ($version < 5) {
                // v5: las reuniones que ya tenían resumen o resolución cuentan como cerradas
                $pdo->exec("UPDATE meetings SET closed_at = COALESCE(updated_at, CURRENT_TIMESTAMP)
                            WHERE closed_at IS NULL AND (COALESCE(summary, '') != '' OR COALESCE(resolution, '') != '')");
                // v5 (H3): skills sincronizadas con los SKILL.md y sin IDs eliminados en roles/pool
                self::syncSkillCatalog($pdo, true);
            }
            $pdo->prepare("INSERT INTO settings (key, value_json, updated_at) VALUES ('schema_version', ?, CURRENT_TIMESTAMP)
                           ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = CURRENT_TIMESTAMP")->execute([(string)$target]);
            $pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable $ignored) {
            }
            error_log('AgileForge migración v' . $target . ': ' . $e->getMessage());
        } finally {
            self::$txDepth--;
        }
    }

    /**
     * Copia de seguridad previa a una migración de datos: <carpeta de la base>/backup/agileforge-YYYYmmdd-HHMMSS-pre-v<N>.sqlite.
     * Solo si la base ya tiene datos (roles, historias o reuniones). VACUUM INTO genera una copia consistente aunque haya WAL.
     * Devuelve la ruta de la copia o null si no hizo falta o no se pudo (nunca bloquea la migración).
     */
    private static function backupBeforeMigration(PDO $pdo, int $target): ?string
    {
        try {
            $has = 0;
            foreach (['roles', 'tasks', 'meetings', 'decisions'] as $t) {
                $has += (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            }
            if ($has === 0) {
                return null;
            }
            $dir = dirname((string)self::$dbPath) . '/backup';
            if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
                return null;
            }
            // Una sola copia por versión de destino: si la migración falla y se reintenta, no se llena la carpeta de copias
            if (glob($dir . '/agileforge-*-pre-v' . $target . '*.sqlite')) {
                return null;
            }
            $dest = $dir . '/agileforge-' . date('Ymd-His') . '-pre-v' . $target . '.sqlite';
            $n = 1;
            while (file_exists($dest)) {
                $dest = $dir . '/agileforge-' . date('Ymd-His') . '-pre-v' . $target . '-' . (++$n) . '.sqlite';
            }
            $pdo->exec('VACUUM INTO ' . $pdo->quote($dest));
            @chmod($dest, 0600);
            error_log('AgileForge: copia previa a la migración en ' . $dest);
            return $dest;
        } catch (\Throwable $e) {
            error_log('AgileForge copia previa a la migración: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * H3: sincroniza la tabla skills con plugin/agileforge/skills/*\/SKILL.md (única fuente de verdad).
     * - Alta/actualización de cada skill de los archivos.
     * - Baja de las filas que ya no tienen archivo (salvo las personalizadas creadas desde la interfaz).
     * - Aplica el mapa ADR-005 y quita de roles / role_skills / pool los IDs que no existen en el catálogo.
     * Idempotente. Si no hay ningún SKILL.md no toca nada. Con $force=false se salta si la carpeta no cambió (hash de nombres, tamaños y fechas).
     */
    public static function syncSkillCatalog(PDO $pdo, bool $force = true): void
    {
        $files = glob(dirname(__DIR__) . '/plugin/agileforge/skills/*/SKILL.md') ?: [];
        if (!$files) {
            return;
        }
        $sig = [];
        foreach ($files as $f) {
            $sig[] = basename(dirname($f)) . '|' . (int)@filesize($f) . '|' . (int)@filemtime($f);
        }
        sort($sig);
        $hash = md5(implode("\n", $sig));
        $row = $pdo->query("SELECT value_json FROM settings WHERE key = 'skills_catalog_hash'")->fetch();
        if (!$force && $row && json_decode($row['value_json'], true) === $hash) {
            return;
        }

        $catalog = Repository::skillsFromFiles();
        if (!$catalog) {
            return;
        }
        $own = self::$txDepth === 0 && !$pdo->inTransaction();
        if ($own) {
            $pdo->exec('BEGIN IMMEDIATE');
        }
        try {
            $up = $pdo->prepare(
                "INSERT INTO skills (id, name, level, category, description, tags_json, is_custom) VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT(id) DO UPDATE SET name = excluded.name, level = excluded.level, category = excluded.category,
                    description = excluded.description, tags_json = excluded.tags_json, is_custom = excluded.is_custom"
            );
            $ids = [];
            foreach ($catalog as $sk) {
                $ids[] = (string)$sk['id'];
                $up->execute([$sk['id'], $sk['name'], $sk['level'], $sk['category'], $sk['desc'],
                    json_encode($sk['tags'], JSON_UNESCAPED_UNICODE), !empty($sk['is_custom']) ? 1 : 0]);
            }

            // Bajas: filas sin archivo (las personalizadas de la interfaz, is_custom = 1, se conservan salvo que sean un ID del mapa ADR-005)
            $del = $pdo->prepare("DELETE FROM skills WHERE id = ?");
            foreach ($pdo->query("SELECT id, is_custom FROM skills")->fetchAll() as $r) {
                if (in_array($r['id'], $ids, true)) {
                    continue;
                }
                if (empty($r['is_custom']) || isset(self::SKILL_ID_MAP[$r['id']])) {
                    $del->execute([$r['id']]);
                }
            }

            // IDs eliminados en roles, role_skills y pool: mapa ADR-005 y, después, fuera los que no existen
            self::remapSkillIds($pdo);
            $valid = $pdo->query("SELECT id FROM skills")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $validSet = array_flip(array_map('strval', $valid));
            $updRole = $pdo->prepare("UPDATE roles SET assigned_skills_json = ? WHERE id = ?");
            foreach ($pdo->query("SELECT id, assigned_skills_json FROM roles")->fetchAll() as $r) {
                $list = json_decode((string)($r['assigned_skills_json'] ?? ''), true);
                if (!is_array($list)) {
                    continue;
                }
                $clean = array_values(array_filter($list, fn($x) => is_string($x) && isset($validSet[$x])));
                if ($clean !== $list) {
                    $updRole->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $r['id']]);
                }
            }
            foreach ($pdo->query("SELECT role_id, skill_id FROM role_skills")->fetchAll() as $r) {
                if (!isset($validSet[$r['skill_id']])) {
                    $pdo->prepare("DELETE FROM role_skills WHERE role_id = ? AND skill_id = ?")->execute([$r['role_id'], $r['skill_id']]);
                }
            }
            $pool = $pdo->query("SELECT value_json FROM settings WHERE key = 'available_skills_pool'")->fetch();
            if ($pool) {
                $list = json_decode((string)$pool['value_json'], true);
                if (is_array($list)) {
                    $clean = array_values(array_filter($list, fn($x) => is_string($x) ? isset($validSet[$x]) : (is_array($x) && isset($validSet[(string)($x['id'] ?? '')]))));
                    if ($clean !== $list) {
                        $pdo->prepare("UPDATE settings SET value_json = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'available_skills_pool'")
                            ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            }

            $pdo->prepare("INSERT INTO settings (key, value_json, updated_at) VALUES ('skills_catalog_hash', ?, CURRENT_TIMESTAMP)
                           ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = CURRENT_TIMESTAMP")
                ->execute([json_encode($hash)]);
            if ($own) {
                $pdo->exec('COMMIT');
            }
        } catch (\Throwable $e) {
            if ($own) {
                try {
                    $pdo->exec('ROLLBACK');
                } catch (\Throwable $ignored) {
                }
            }
            throw $e;
        }
    }

    /**
     * Si cambió config/models.json (modelos retirados o nuevos), reasigna a cada rol un modelo vigente.
     */
    private static function syncModelCatalog(PDO $pdo): void
    {
        $file = dirname(__DIR__) . '/config/models.json';
        if (!is_file($file)) {
            return;
        }
        $hash = md5_file($file) ?: '';
        $row = $pdo->query("SELECT value_json FROM settings WHERE key = 'models_catalog_hash'")->fetch();
        if ($row && json_decode($row['value_json'], true) === $hash) {
            return;
        }
        try {
            self::normalizeRoleEngines($pdo);
            self::normalizeGlobalEngine($pdo);
            $pdo->prepare("INSERT INTO settings (key, value_json, updated_at) VALUES ('models_catalog_hash', ?, CURRENT_TIMESTAMP)
                           ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = CURRENT_TIMESTAMP")
                ->execute([json_encode($hash)]);
        } catch (\Throwable $e) {
            error_log('AgileForge catálogo de modelos: ' . $e->getMessage());
        }
    }

    /** El Director General: jefe máximo del equipo, recibe instrucciones y decide. */
    public static function seedDirectorRole(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            "INSERT OR IGNORE INTO roles (id, name, alias, avatar, ai_provider, ai_model, description, specialty, assigned_skills_json)
             VALUES ('director', ?, 'The Chief', '👑', 'anthropic', 'claude-opus-5-5', ?, ?, ?)"
        );
        $skills = ['director-toma-decisiones', 'director-gestion-equipo', 'director-priorizacion-valor', 'agile-project-manager', 'context-sentinel'];
        $name = 'Director General (Jefe Máximo)';
        $description = 'Recibe tus instrucciones, decide prioridades, delega en los agentes especialistas, registra las decisiones y te rinde cuentas.';
        $specialty = 'Dirección estratégica, toma de decisiones, priorización por valor, delegación y control del equipo de agentes';
        // RFC-001: los datos salen del pack core (plugin/agileforge/packs/core/director.md) si está disponible
        try {
            require_once __DIR__ . '/services/RoleRegistry.php';
            [$def] = \AgileForge\Services\RoleRegistry::parseFile(\AgileForge\Services\RoleRegistry::corePackDir() . '/director.md');
            $name = is_string($def['name'] ?? null) && $def['name'] !== '' ? $def['name'] : $name;
            $description = is_string($def['description'] ?? null) ? $def['description'] : $description;
            $specialty = is_string($def['specialty'] ?? null) ? $def['specialty'] : $specialty;
            $skills = is_array($def['skills'] ?? null) && $def['skills'] ? array_values($def['skills']) : $skills;
        } catch (\Throwable $e) {
            // sin pack: valores de siempre
        }
        $stmt->execute([$name, $description, $specialty, json_encode($skills, JSON_UNESCAPED_UNICODE)]);
        $rs = $pdo->prepare("INSERT OR IGNORE INTO role_skills (role_id, skill_id) VALUES ('director', ?)");
        foreach ($skills as $sk) {
            $rs->execute([$sk]);
        }
    }

    /**
     * ADR-005 (skills 34 → 27): ID antiguo o inexistente → ID vigente.
     * Se aplica a roles.assigned_skills_json, role_skills, tasks.skills_json, el setting available_skills_pool y a data/team.json al importarlo.
     */
    public const SKILL_ID_MAP = [
        'impeccable'                => 'ui-polish',
        'taste-skill'               => 'ui-polish',
        'hallmark'                  => 'ui-polish',
        'ui-ux-pro-max'             => 'ui-polish',
        'antislop-ui'               => 'ui-polish',
        'antislop-layoutmobile'     => 'responsive-design',
        'antislop-code'             => 'clean-code',
        'graphify'                  => 'dependency-mapping',
        'wcag-a11y-audit'           => 'accessibility-wcag',
        'a11y-screen-reader'        => 'accessibility-wcag',
        'database-design-acid'      => 'database-optimization',
        'backend-dev-guidelines'    => 'api-design-patterns',
        'rest-api-design'           => 'api-design-patterns',
        'security-owasp'            => 'security-hardening',
        'system-architecture-clean' => 'codebase-design',
        'privacy-gdpr-latam'        => 'legal-frameworks',
        'consumer-protection'       => 'terms-and-disclaimers',
        'authentication-patterns'   => 'security-hardening',
    ];

    /** Reasigna IDs de skills con SKILL_ID_MAP y quita duplicados conservando el orden. Acepta strings u objetos con "id". */
    public static function mapSkillIds(array $skills): array
    {
        $out = [];
        $seen = [];
        foreach ($skills as $item) {
            if (is_array($item)) {
                $id = (string)($item['id'] ?? '');
                if ($id === '') {
                    $out[] = $item;
                    continue;
                }
                $new = self::SKILL_ID_MAP[$id] ?? $id;
                if (isset($seen[$new])) {
                    continue;
                }
                $seen[$new] = true;
                $item['id'] = $new;
                $out[] = $item;
                continue;
            }
            $id = (string)$item;
            if ($id === '') {
                continue;
            }
            $new = self::SKILL_ID_MAP[$id] ?? $id;
            if (isset($seen[$new])) {
                continue;
            }
            $seen[$new] = true;
            $out[] = $new;
        }
        return $out;
    }

    /** Idempotente: aplica el mapa ADR-005 a la base. */
    public static function remapSkillIds(PDO $pdo): void
    {
        $enc = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);

        foreach ([['roles', 'id', 'assigned_skills_json'], ['tasks', 'id', 'skills_json']] as [$table, $pk, $col]) {
            $upd = $pdo->prepare("UPDATE {$table} SET {$col} = ? WHERE {$pk} = ?");
            foreach ($pdo->query("SELECT {$pk} AS pk, {$col} AS js FROM {$table} WHERE {$col} IS NOT NULL AND {$col} != ''")->fetchAll() as $r) {
                $list = json_decode((string)$r['js'], true);
                if (!is_array($list)) {
                    continue;
                }
                $new = self::mapSkillIds($list);
                if ($new !== $list) {
                    $upd->execute([$enc($new), $r['pk']]);
                }
            }
        }

        // Tabla relacional roles ↔ skills
        $rs = $pdo->query("SELECT role_id, skill_id FROM role_skills")->fetchAll();
        $del = $pdo->prepare("DELETE FROM role_skills WHERE role_id = ? AND skill_id = ?");
        $ins = $pdo->prepare("INSERT OR IGNORE INTO role_skills (role_id, skill_id) VALUES (?, ?)");
        foreach ($rs as $r) {
            $to = self::SKILL_ID_MAP[$r['skill_id']] ?? null;
            if ($to !== null) {
                $del->execute([$r['role_id'], $r['skill_id']]);
                $ins->execute([$r['role_id'], $to]);
            }
        }

        // Setting con el pool de skills disponibles
        $row = $pdo->query("SELECT value_json FROM settings WHERE key = 'available_skills_pool'")->fetch();
        if ($row) {
            $pool = json_decode((string)$row['value_json'], true);
            if (is_array($pool)) {
                $new = self::mapSkillIds($pool);
                if ($new !== $pool) {
                    $pdo->prepare("UPDATE settings SET value_json = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'available_skills_pool'")->execute([$enc($new)]);
                }
            }
        }
    }

    /** Modelo del motor global (setting ai_engine): si ya no está en config/models.json se reasigna a uno vigente. */
    public static function normalizeGlobalEngine(PDO $pdo): void
    {
        $row = $pdo->query("SELECT value_json FROM settings WHERE key = 'ai_engine'")->fetch();
        if (!$row) {
            return;
        }
        $ai = json_decode((string)$row['value_json'], true);
        if (!is_array($ai)) {
            return;
        }
        $cfg = json_decode((string)@file_get_contents(dirname(__DIR__) . '/config/models.json'), true) ?: [];
        $providers = $cfg['providers'] ?? [];
        $prov = (string)($ai['active_provider'] ?? '');
        $model = (string)($ai['active_model'] ?? '');
        if ($prov === '' || $prov === 'local' || $prov === 'ollama' || !isset($providers[$prov]) || $model === '') {
            return;
        }
        $valid = self::resolveModelId($providers[$prov], $prov, $model);
        if ($valid !== $model) {
            $ai['active_model'] = $valid;
            $pdo->prepare("UPDATE settings SET value_json = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'ai_engine'")
                ->execute([json_encode($ai, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /** ID vigente para un modelo guardado (por id o nombre); si ya no existe, el equivalente anthropic por familia o el modelo por defecto del proveedor (igual que con los roles). */
    private static function resolveModelId(array $provider, string $providerId, string $model): string
    {
        foreach ($provider['models'] ?? [] as $m) {
            if (strcasecmp((string)$m['id'], $model) === 0 || strcasecmp((string)($m['name'] ?? ''), $model) === 0) {
                return (string)$m['id'];
            }
        }
        if ($providerId === 'anthropic') {
            return stripos($model, 'haiku') !== false ? 'claude-haiku-5-5'
                : (stripos($model, 'opus') !== false ? 'claude-opus-5-5' : 'claude-sonnet-5-5');
        }
        return (string)($provider['default_model'] ?? $model);
    }

    /** Tras importar datos antiguos (bin/migrate.php): modelos de roles + motor global + IDs de skills. */
    public static function normalizeImported(PDO $pdo): void
    {
        self::normalizeRoleEngines($pdo);
        self::normalizeGlobalEngine($pdo);
        self::remapSkillIds($pdo);
        self::syncSkillCatalog($pdo, true);
    }

    /** Convierte nombres visibles guardados ("Claude 3.7 Sonnet", "GPT-4o") en IDs de modelo válidos. */
    private static function normalizeRoleEngines(PDO $pdo): void
    {
        $cfg = json_decode((string)@file_get_contents(dirname(__DIR__) . '/config/models.json'), true) ?: [];
        $providers = $cfg['providers'] ?? [];
        $upd = $pdo->prepare("UPDATE roles SET ai_provider = ?, ai_model = ? WHERE id = ?");
        foreach ($pdo->query("SELECT id, ai_provider, ai_model FROM roles")->fetchAll() as $r) {
            $prov = (string)($r['ai_provider'] ?? '');
            if ($prov === '' || $prov === 'local' || !isset($providers[$prov])) {
                continue;
            }
            $model = (string)($r['ai_model'] ?? '');
            $valid = self::resolveModelId($providers[$prov], $prov, $model);
            if ($valid !== $model) {
                $upd->execute([$prov, $valid, $r['id']]);
            }
        }
    }

    private static function cleanAiEngineSetting(PDO $pdo): void
    {
        $row = $pdo->query("SELECT value_json FROM settings WHERE key = 'ai_engine'")->fetch();
        $ai = $row ? (json_decode($row['value_json'], true) ?: []) : [];
        unset($ai['available_providers'], $ai['api_key_configured']);
        $model = (string)($ai['active_model'] ?? '');
        if ($model === '' || stripos($model, 'claude 3') !== false || stripos($model, 'claude-3') !== false) {
            $ai['active_provider'] = 'anthropic';
            $ai['active_model'] = 'claude-sonnet-5-5';
        }
        if (!array_key_exists('role_routing_enabled', $ai)) {
            $ai['role_routing_enabled'] = true;
        }
        $ai['execution_mode'] = $ai['execution_mode'] ?? 'api';
        $pdo->prepare("INSERT INTO settings (key, value_json, updated_at) VALUES ('ai_engine', ?, CURRENT_TIMESTAMP)
                       ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = CURRENT_TIMESTAMP")
            ->execute([json_encode($ai, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Una migración anterior reinsertó mensajes antiguos con fecha nueva y desordenó el chat.
     * Recupera la fecha real desde el ID (MSG-<epoch> o MSG-<epoch ms>-…).
     */
    private static function repairChatOrder(PDO $pdo): void
    {
        $rows = $pdo->query("SELECT rowid AS rid, id, created_at FROM chat_messages")->fetchAll();
        $upd = $pdo->prepare("UPDATE chat_messages SET created_at = ? WHERE rowid = ?");
        $min = null;
        foreach ($rows as $r) {
            $ts = null;
            if (preg_match('/^MSG-(\d{13})-/', $r['id'], $m)) {
                $ts = intdiv((int)$m[1], 1000);
            } elseif (preg_match('/^MSG-(\d{10})$/', $r['id'], $m)) {
                $ts = (int)$m[1];
            }
            if ($ts !== null) {
                $date = gmdate('Y-m-d H:i:s', $ts);
                $min = $min === null ? $ts : min($min, $ts);
                if ($date !== $r['created_at']) {
                    $upd->execute([$date, $r['rid']]);
                }
            }
        }
        // Mensajes de ejemplo sin fecha en el ID (MSG-001…): antes que todo lo demás
        if ($min !== null) {
            foreach ($rows as $r) {
                if (preg_match('/^MSG-(\d{1,3})$/', $r['id'], $m)) {
                    $upd->execute([gmdate('Y-m-d H:i:s', $min - 3600 + (int)$m[1] * 60), $r['rid']]);
                }
            }
        }
    }

    /**
     * Sprints sin ends_at (creados antes de la columna o importados): cierre = creación + días planificados.
     * Solo escribe si hay alguno pendiente (una lectura barata en el caso normal).
     */
    private static function backfillSprintEnds(PDO $pdo): void
    {
        try {
            $pending = $pdo->query("SELECT 1 FROM sprints WHERE ends_at IS NULL LIMIT 1")->fetchColumn();
            if ($pending) {
                $pdo->exec("UPDATE sprints SET ends_at = datetime(COALESCE(created_at, CURRENT_TIMESTAMP), '+' || MAX(0, COALESCE(days_remaining, 0)) || ' days')
                            WHERE ends_at IS NULL");
            }
        } catch (\Throwable $e) {
            error_log('AgileForge ends_at de sprints: ' . $e->getMessage());
        }
    }

    /** IDs de rol antiguos (datos importados de versiones previas) → rol vigente. */
    private const LEGACY_ROLE_IDS = [
        'qa_security'      => 'qa_security_guard',
        'frontend'         => 'frontend_master',
        'backend'          => 'backend_architect',
        'legal_compliance' => 'legal_counsel',
        'chief_architect'  => 'lead_architect',
    ];

    /**
     * Reparaciones idempotentes del tablero (solo escriben si encuentran algo que arreglar):
     *  - ADR-004: una historia completada no conserva el lock (había completadas con 🔒 de importaciones antiguas).
     *  - Historias asignadas a IDs de rol que ya no existen (se veían como «🤖 Agente»): se pasan al rol vigente.
     *  - Prioridades antiguas en inglés (critical/high/medium/low) se pasan a las vigentes en español:
     *    el tablero las mostraba en inglés, con el color equivocado, y el filtro de prioridad no las encontraba.
     */
    private static function repairBoardData(PDO $pdo): void
    {
        try {
            if ($pdo->query("SELECT 1 FROM tasks WHERE status = 'completada' AND COALESCE(locked_by, '') != '' LIMIT 1")->fetchColumn()) {
                $pdo->exec("UPDATE tasks SET locked_by = NULL, locked_at = NULL WHERE status = 'completada' AND COALESCE(locked_by, '') != ''");
            }
            if ($pdo->query("SELECT 1 FROM tasks WHERE priority IN ('critical','high','medium','low') LIMIT 1")->fetchColumn()) {
                $pdo->exec("UPDATE tasks SET priority = CASE priority WHEN 'critical' THEN 'critica' WHEN 'high' THEN 'alta'
                                WHEN 'medium' THEN 'media' ELSE 'baja' END
                            WHERE priority IN ('critical','high','medium','low')");
            }
            $legacy = array_keys(self::LEGACY_ROLE_IDS);
            $in = implode(',', array_fill(0, count($legacy), '?'));
            $q = $pdo->prepare("SELECT 1 FROM tasks WHERE assigned_to IN ({$in}) OR locked_by IN ({$in}) LIMIT 1");
            $q->execute(array_merge($legacy, $legacy));
            if ($q->fetchColumn()) {
                $exists = $pdo->prepare("SELECT 1 FROM roles WHERE id = ?");
                foreach (self::LEGACY_ROLE_IDS as $old => $new) {
                    $exists->execute([$new]);
                    if (!$exists->fetchColumn()) {
                        continue;
                    }
                    $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE assigned_to = ?")->execute([$new, $old]);
                    $pdo->prepare("UPDATE tasks SET locked_by = ? WHERE locked_by = ?")->execute([$new, $old]);
                }
            }
        } catch (\Throwable $e) {
            error_log('AgileForge reparación del tablero: ' . $e->getMessage());
        }
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $type): void
    {
        $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
        foreach ($cols as $c) {
            if (($c['name'] ?? '') === $column) {
                return;
            }
        }
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
    }
}
