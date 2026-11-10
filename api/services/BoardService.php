<?php
/**
 * AgileForge - BoardService
 * Operaciones del tablero compartidas por la web, el MCP y el Director:
 * IDs únicos, historias del proyecto activo, decisiones (ADR) y sprint.
 */

declare(strict_types=1);

namespace AgileForge\Services;

use AgileForge\Database;
use AgileForge\Repository;
use InvalidArgumentException;
use PDO;

class BoardService
{
    public const STATUSES = ['backlog', 'en_progreso', 'revision', 'completada'];
    public const PRIORITIES = ['critica', 'alta', 'media', 'baja'];
    public const FIBONACCI = [1, 2, 3, 5, 8, 13];

    private Repository $repo;
    private PDO $pdo;

    public function __construct(?Repository $repo = null)
    {
        $this->repo = $repo ?? new Repository();
        $this->pdo = Database::getPdo();
    }

    public function activeProjectId(): string
    {
        return (string)($this->repo->getActiveProject()['id'] ?? 'default');
    }

    /**
     * Siguiente ID libre con prefijo (US-, MEET-, ADR-): toma el mayor número existente + 1.
     * Evita las colisiones de clave primaria de los IDs aleatorios.
     * Llamar SIEMPRE dentro de Database::transaction() (BEGIN IMMEDIATE): así la lectura del máximo y el INSERT
     * son atómicos y varios workers concurrentes no obtienen el mismo ID.
     */
    public static function nextId(PDO $pdo, string $table, string $prefix, int $pad = 3): string
    {
        if (!in_array($table, ['tasks', 'meetings', 'decisions'], true)) {
            throw new InvalidArgumentException('Tabla no permitida');
        }
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id LIKE ?");
        $stmt->execute([$prefix . '%']);
        $max = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string)$id, $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        $check = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = ?");
        do {
            $max++;
            $candidate = $prefix . str_pad((string)$max, $pad, '0', STR_PAD_LEFT);
            $check->execute([$candidate]);
        } while ($check->fetchColumn());
        return $candidate;
    }

    /** Story points: el valor Fibonacci más cercano (4 → 3, 6 → 5, 10 → 8, 20 → 13); vacío o inválido → 3. */
    public static function fibonacci($value): int
    {
        if (!is_numeric($value)) {
            return 3;
        }
        $v = (float)$value;
        if ($v <= 0) {
            return 3;
        }
        $best = 3;
        $bestDiff = PHP_FLOAT_MAX;
        foreach (self::FIBONACCI as $f) {
            $d = abs($f - $v);
            if ($d < $bestDiff) {
                $best = $f;
                $bestDiff = $d;
            }
        }
        return $best;
    }

    public function roleIds(): array
    {
        return $this->pdo->query("SELECT id FROM roles ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function role(string $roleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row ?: null;
    }

    /** Busca un rol por id exacto o por nombre/alias aproximado (útil para lo que escribe una IA). */
    public function matchRoleId(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $roles = $this->pdo->query("SELECT id, name, alias FROM roles")->fetchAll();
        foreach ($roles as $r) {
            if (strcasecmp($r['id'], $text) === 0) {
                return $r['id'];
            }
        }
        $needle = mb_strtolower($text);
        foreach ($roles as $r) {
            if (str_contains(mb_strtolower($r['name'] . ' ' . $r['alias'] . ' ' . $r['id']), $needle)) {
                return $r['id'];
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Historias
    // ------------------------------------------------------------------

    public function listTasks(array $filters = [], int $limit = 80): array
    {
        $sql = "SELECT id, title, status, assigned_to, priority, story_points, dod, locked_by, updated_at FROM tasks WHERE project_id = ?";
        $params = [$this->activeProjectId()];
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['role'])) {
            $sql .= " AND assigned_to = ?";
            $params[] = $filters['role'];
        }
        $sql .= " ORDER BY CASE status WHEN 'en_progreso' THEN 0 WHEN 'revision' THEN 1 WHEN 'backlog' THEN 2 ELSE 3 END,
                  CASE priority WHEN 'critica' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END, created_at DESC LIMIT " . (int)$limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createTask(array $in, string $client = 'api', ?string $actor = null): array
    {
        $title = trim((string)($in['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('El título de la historia es obligatorio');
        }
        $assigned = trim((string)($in['assigned_to'] ?? 'project_manager'));
        $matched = $this->matchRoleId($assigned);
        if ($matched === null) {
            throw new InvalidArgumentException("El rol '{$assigned}' no existe");
        }
        $points = self::fibonacci($in['story_points'] ?? 3);
        $priority = in_array($in['priority'] ?? '', self::PRIORITIES, true) ? $in['priority'] : 'media';
        $status = in_array($in['status'] ?? '', ['backlog', 'en_progreso', 'revision'], true) ? $in['status'] : 'backlog';
        $userStory = trim((string)($in['user_story'] ?? ''));
        $dod = trim((string)($in['dod'] ?? ''));
        if ($dod === '') {
            $dod = 'Criterios de aceptación verificados, sin errores y revisado por QA.';
        }
        $description = trim((string)($in['description'] ?? ''));
        $skills = $this->repo->cleanSkillIds(is_array($in['skills'] ?? null) ? $in['skills'] : []);
        if (!$skills) {
            $role = $this->role($matched);
            $skills = array_slice(json_decode((string)($role['assigned_skills_json'] ?? '[]'), true) ?: [], 0, 3);
        }
        $prompt = trim((string)($in['prompt_template'] ?? ''));
        $projectId = !empty($in['project_id']) ? (string)$in['project_id'] : $this->activeProjectId();
        if (!$this->repo->projectExists($projectId)) {
            throw new InvalidArgumentException("El proyecto '{$projectId}' no existe");
        }

        $givenId = !empty($in['id']) && preg_match('/^[A-Za-z0-9_-]{2,40}$/', (string)$in['id']) ? (string)$in['id'] : null;
        $id = '';

        // El ID se calcula DENTRO de la transacción BEGIN IMMEDIATE (H4): lectura del máximo + INSERT atómicos
        Database::transaction(function (PDO $pdo) use (&$id, $givenId, &$prompt, $projectId, $title, $points, $matched, $priority, $status, $userStory, $dod, $description, $skills, $client, $actor) {
            if ($givenId !== null) {
                $check = $pdo->prepare("SELECT 1 FROM tasks WHERE id = ?");
                $check->execute([$givenId]);
                if ($check->fetch()) {
                    throw new RuntimeException("La historia '{$givenId}' ya existe", 409);
                }
            }
            $id = $givenId ?? self::nextId($pdo, 'tasks', 'US-');
            if ($prompt === '') {
                $prompt = $this->missionPrompt($id, $title, $matched, $userStory, $dod, $description, $skills);
            }
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO tasks (id, project_id, title, story_points, assigned_to, priority, status, user_story, dod, description, skills_json, prompt_template)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$id, $projectId, $title, $points, $matched, $priority, $status, $userStory, $dod, $description,
                    json_encode($skills, JSON_UNESCAPED_UNICODE), $prompt]);
            } catch (\PDOException $pe) {
                if ($pe->getCode() === '23000' || str_contains($pe->getMessage(), 'UNIQUE')) {
                    throw new RuntimeException("La historia '{$id}' ya existe", 409);
                }
                throw $pe;
            }

            $e = $pdo->prepare("INSERT INTO task_events (task_id, role_key, client, event_type, payload_json) VALUES (?, ?, ?, 'task_created', ?)");
            $e->execute([$id, $actor ?? $matched, $client, json_encode(['title' => $title, 'status' => $status, 'by' => $actor], JSON_UNESCAPED_UNICODE)]);
        });

        return $this->repo->getTask($id) ?? ['id' => $id];
    }

    /**
     * Edita campos de una historia. "completada" solo se permite si $allowComplete (aprobación humana desde la web).
     */
    public function updateTask(string $id, array $fields, string $client = 'api', ?string $actor = null, bool $allowComplete = false): array
    {
        $task = $this->repo->getTask($id);
        if (!$task) {
            throw new InvalidArgumentException("La historia {$id} no existe");
        }

        $set = [];
        $params = [];
        $changes = [];

        if (isset($fields['title']) && trim((string)$fields['title']) !== '') {
            $set[] = 'title = ?';
            $params[] = trim((string)$fields['title']);
            $changes['title'] = $params[count($params) - 1];
        }
        if (isset($fields['assigned_to']) && trim((string)$fields['assigned_to']) !== '') {
            $rid = $this->matchRoleId((string)$fields['assigned_to']);
            if ($rid === null) {
                throw new InvalidArgumentException("El rol '{$fields['assigned_to']}' no existe");
            }
            $set[] = 'assigned_to = ?';
            $params[] = $rid;
            $changes['assigned_to'] = $rid;
        }
        if (isset($fields['priority']) && in_array($fields['priority'], self::PRIORITIES, true)) {
            $set[] = 'priority = ?';
            $params[] = $fields['priority'];
            $changes['priority'] = $fields['priority'];
        }
        if (isset($fields['story_points']) && is_numeric($fields['story_points']) && (float)$fields['story_points'] > 0) {
            $set[] = 'story_points = ?';
            $params[] = self::fibonacci($fields['story_points']);
            $changes['story_points'] = $params[count($params) - 1];
        }
        foreach (['user_story', 'dod', 'description'] as $f) {
            if (isset($fields[$f]) && is_string($fields[$f])) {
                $set[] = "{$f} = ?";
                $params[] = trim($fields[$f]);
                $changes[$f] = true;
            }
        }
        if (isset($fields['status']) && $fields['status'] !== '' && $fields['status'] !== $task['status']) {
            $st = (string)$fields['status'];
            if (!in_array($st, self::STATUSES, true)) {
                throw new InvalidArgumentException("Estado inválido: {$st}");
            }
            if ($st === 'completada' && !$allowComplete) {
                throw new InvalidArgumentException('Marcar una historia como completada requiere tu aprobación desde el tablero.');
            }
            $set[] = 'status = ?';
            $params[] = $st;
            $changes['status'] = $st;
            if (in_array($st, ['completada', 'backlog'], true)) {
                // ADR-004: el lock se libera al pasar a completada o backlog
                $set[] = 'locked_by = NULL';
                $set[] = 'locked_at = NULL';
            }
        }

        if (!$set) {
            return $task;
        }

        Database::transaction(function (PDO $pdo) use ($id, $set, $params, $changes, $client, $actor) {
            $params[] = $id;
            $pdo->prepare("UPDATE tasks SET " . implode(', ', $set) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute($params);
            $type = isset($changes['status']) ? 'status_change' : 'task_updated';
            $payload = $changes + ['by' => $actor];
            $pdo->prepare("INSERT INTO task_events (task_id, role_key, client, event_type, payload_json) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, $actor, $client, $type, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        });

        return $this->repo->getTask($id) ?? $task;
    }

    /** Elimina una historia con sus comentarios y eventos (solo desde la web: decisión humana). */
    public function deleteTask(string $id): void
    {
        if (!$this->repo->getTask($id)) {
            throw new InvalidArgumentException("La historia {$id} no existe");
        }
        Database::transaction(function (PDO $pdo) use ($id) {
            $pdo->prepare("DELETE FROM comments WHERE task_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM task_events WHERE task_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);
        });
    }

    public function missionPrompt(string $id, string $title, string $roleId, string $story, string $dod, string $desc, array $skills): string
    {
        $role = $this->role($roleId);
        $ws = (string)($this->repo->getActiveProject()['path'] ?? '');
        $lines = [
            'Actúa como ' . ($role['name'] ?? $roleId) . ' (' . ($role['alias'] ?? $roleId) . ') del equipo AgileForge.',
            "📂 WORKSPACE: {$ws}",
            "🎯 MISIÓN [{$id}]: {$title}",
        ];
        if ($story !== '') {
            $lines[] = "📖 HISTORIA: {$story}";
        }
        if ($desc !== '') {
            $lines[] = "🛠️ DETALLES: {$desc}";
        }
        if ($skills) {
            $lines[] = '⚡ SKILLS: ' . implode(', ', $skills);
        }
        $lines[] = "✅ DEFINITION OF DONE: {$dod}";
        $lines[] = "Flujo: toma la tarea (claim_task \"{$id}\"), registra avances (add_comment) y entrégala con evidencia (submit_for_review).";
        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------
    // Decisiones y sprint
    // ------------------------------------------------------------------

    public function recordDecision(string $title, string $context, string $decision, string $consequences, string $status = 'accepted', ?string $projectId = null): string
    {
        if (trim($title) === '' || trim($decision) === '') {
            throw new InvalidArgumentException('La decisión necesita título y contenido');
        }
        if ($projectId !== null && trim($projectId) !== '') {
            $projectId = trim($projectId);
            if (!$this->repo->projectExists($projectId)) {
                throw new InvalidArgumentException("El proyecto '{$projectId}' no existe");
            }
        } else {
            $projectId = $this->activeProjectId();
        }
        $id = '';
        // ID dentro de la transacción BEGIN IMMEDIATE (H4)
        Database::transaction(function (PDO $pdo) use (&$id, $projectId, $title, $context, $decision, $consequences, $status) {
            $id = self::nextId($pdo, 'decisions', 'ADR-');
            $pdo->prepare(
                "INSERT INTO decisions (id, project_id, code, title, context, decision, consequences, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$id, $projectId, $id, trim($title), trim($context), trim($decision), trim($consequences), $status]);
        });
        return $id;
    }

    public function recentDecisions(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("SELECT id, title, decision, created_at FROM decisions WHERE project_id = ? OR project_id = 'default' ORDER BY created_at DESC LIMIT " . (int)$limit);
        $stmt->execute([$this->activeProjectId()]);
        return $stmt->fetchAll();
    }

    public function activeSprint(): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sprints WHERE project_id = ? AND status = 'active' ORDER BY number DESC LIMIT 1");
        $stmt->execute([$this->activeProjectId()]);
        return $stmt->fetch() ?: null;
    }

    public function updateSprint(array $fields): array
    {
        $sprint = $this->activeSprint();
        if (!$sprint) {
            throw new InvalidArgumentException('El proyecto activo no tiene un sprint activo');
        }
        $set = [];
        $params = [];
        if (!empty($fields['name'])) {
            $set[] = 'name = ?';
            $params[] = trim((string)$fields['name']);
        }
        if (!empty($fields['goal'])) {
            $set[] = 'goal = ?';
            $params[] = trim((string)$fields['goal']);
        }
        if (isset($fields['days_remaining']) && is_numeric($fields['days_remaining'])) {
            $days = max(0, min(60, (int)$fields['days_remaining']));
            $set[] = 'days_remaining = ?';
            $params[] = $days;
            // La fecha de cierre se recalcula desde hoy: así los días siguen bajando solos
            $set[] = "ends_at = datetime('now', ?)";
            $params[] = '+' . $days . ' days';
        }
        if (isset($fields['velocity_points']) && is_numeric($fields['velocity_points'])) {
            $set[] = 'velocity_points = ?';
            $params[] = max(1, min(500, (int)$fields['velocity_points']));
        }
        if ($set) {
            $params[] = $sprint['id'];
            $this->pdo->prepare("UPDATE sprints SET " . implode(', ', $set) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute($params);
        }
        return $this->activeSprint() ?? $sprint;
    }
}
