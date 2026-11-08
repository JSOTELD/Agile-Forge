<?php
/**
 * AgileForge - Repositorio de Datos
 * Abstracción sobre SQLite que proporciona métodos de consulta relacionales
 * y mantiene compatibilidad con la estructura completa de datos requerida por el frontend.
 */

declare(strict_types=1);

namespace AgileForge;

require_once __DIR__ . '/services/LlmService.php';

use AgileForge\Services\LlmService;
use PDO;

class Repository
{
    private PDO $db;
    private LlmService $llm;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getPdo();
        $this->llm = new LlmService();
    }

    /**
     * Devuelve el estado completo del equipo y tablero (equivalente al antiguo team.json)
     */
    public function getProjects(): array
    {
        $stmt = $this->db->query("SELECT * FROM projects ORDER BY created_at ASC");
        return $stmt->fetchAll() ?: [];
    }

    public function getActiveProject(): array
    {
        $stmt = $this->db->query("SELECT * FROM projects WHERE active = 1 LIMIT 1");
        $proj = $stmt->fetch();
        if ($proj) {
            return $proj;
        }
        $all = $this->getProjects();
        if (!empty($all)) {
            return $all[0];
        }
        return [
            'id'     => 'default',
            'name'   => 'AgileForge Default',
            'path'   => dirname(__DIR__),
            'active' => 1
        ];
    }

    public function getFullState(): array
    {
        $settings = $this->getAllSettings();

        $projects = $this->getProjects();
        $currentProject = $this->getActiveProject();
        $activeProjectId = $currentProject['id'] ?? 'default';

        $activeWorkspace = $currentProject['path'] ?? ($settings['active_workspace'] ?? dirname(__DIR__));
        $orgName = $settings['org_name'] ?? 'AgileForge Ecosystem';
        $jurisdiction = $settings['jurisdiction'] ?? [
            'country_code'  => 'PE',
            'country_name'  => 'Perú',
            'flag'          => '🇵🇪',
            'auto_detected' => false,
            'regulations'   => ['Ley 29733 (Protección de Datos)', 'Código de Protección al Consumidor']
        ];
        $aiEngine = $settings['ai_engine'] ?? [];
        if (empty($aiEngine['active_provider'])) $aiEngine['active_provider'] = 'anthropic';
        if (empty($aiEngine['active_model']) || $aiEngine['active_model'] === 'Claude 3.7 Sonnet') {
            $aiEngine['active_model'] = 'claude-sonnet-5-5';
        }
        $aiEngine['provider_name'] = $aiEngine['provider_name'] ?? 'Anthropic Claude';
        $aiEngine['icon'] = $aiEngine['icon'] ?? '🟣';
        $aiEngine['execution_mode'] = $aiEngine['execution_mode'] ?? 'cli';
        $aiEngine['role_routing_enabled'] = !empty($aiEngine['role_routing_enabled']);
        // Cargar proveedores dinámicos desde config/models.json con estado real de api_key_configured
        $aiEngine['available_providers'] = $this->llm->getProviders();

        // Resumen contable de costos y tokens
        $costSummary = $this->llm->getUsageSummary();
        $uiPreferences = $settings['ui_preferences'] ?? [
            'theme'      => 'obsidian',
            'density'    => 'compact',
            'ux_score'   => 94
        ];
        $skillsPool = $settings['available_skills_pool'] ?? [];

        // Sprint activo del proyecto
        $sprintStmt = $this->db->prepare("SELECT * FROM sprints WHERE project_id = ? AND status = 'active' ORDER BY number DESC LIMIT 1");
        $sprintStmt->execute([$activeProjectId]);
        $sprintRow = $sprintStmt->fetch();

        if (!$sprintRow) {
            $stmt = $this->db->query("SELECT * FROM sprints WHERE status = 'active' ORDER BY number DESC LIMIT 1");
            $sprintRow = $stmt->fetch();
        }

        if ($sprintRow) {
            $sprint = [
                'number'          => (int)$sprintRow['number'],
                'name'            => $sprintRow['name'],
                'goal'            => $sprintRow['goal'],
                'days_remaining'  => (int)$sprintRow['days_remaining'],
                'velocity_points' => (int)$sprintRow['velocity_points'],
            ];
        } else {
            $sprint = [
                'number'          => 1,
                'name'            => 'Sprint 1',
                'goal'            => 'Objetivo inicial del sprint',
                'days_remaining'  => 5,
                'velocity_points' => 20,
            ];
        }

        // Roles
        $rolesStmt = $this->db->query("SELECT * FROM roles ORDER BY id ASC");
        $roles = [];
        while ($row = $rolesStmt->fetch()) {
            $roles[] = [
                'id'              => $row['id'],
                'name'            => $row['name'],
                'alias'           => $row['alias'],
                'avatar'          => $row['avatar'],
                'ai_provider'     => $row['ai_provider'],
                'ai_model'        => $row['ai_model'],
                'description'     => $row['description'],
                'specialty'       => $row['specialty'],
                'assigned_skills' => json_decode($row['assigned_skills_json'] ?? '[]', true) ?: []
            ];
        }

        // Skills catalog (Única Fuente de Verdad: plugin/agileforge/skills/*/SKILL.md)
        $skillsCatalog = $this->getSkillsCatalog();

        // Tasks del proyecto activo
        $tasksStmt = $this->db->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY created_at DESC");
        $tasksStmt->execute([$activeProjectId]);
        $tasks = [];
        while ($row = $tasksStmt->fetch()) {
            $tasks[] = [
                'id'              => $row['id'],
                'title'           => $row['title'],
                'story_points'    => (int)$row['story_points'],
                'assigned_to'     => $row['assigned_to'],
                'ai_provider'     => $row['ai_provider'],
                'ai_model'        => $row['ai_model'],
                'priority'        => $row['priority'],
                'status'          => $row['status'],
                'created_at'      => $row['created_at'],
                'user_story'      => $row['user_story'],
                'dod'             => $row['dod'],
                'description'     => $row['description'],
                'skills'          => json_decode($row['skills_json'] ?? '[]', true) ?: [],
                'prompt_template' => $row['prompt_template'],
                'locked_by'       => $row['locked_by'] ?? null,
                'locked_at'       => $row['locked_at'] ?? null,
            ];
        }

        // Si el proyecto activo es 'default' y no hay tareas con ese project_id exacto, cargar las que no tienen project_id
        if (empty($tasks) && $activeProjectId === 'default') {
            $defTasksStmt = $this->db->query("SELECT * FROM tasks WHERE project_id IS NULL OR project_id = '' OR project_id = 'default' ORDER BY created_at DESC");
            while ($row = $defTasksStmt->fetch()) {
                $tasks[] = [
                    'id'              => $row['id'],
                    'title'           => $row['title'],
                    'story_points'    => (int)$row['story_points'],
                    'assigned_to'     => $row['assigned_to'],
                    'ai_provider'     => $row['ai_provider'],
                    'ai_model'        => $row['ai_model'],
                    'priority'        => $row['priority'],
                    'status'          => $row['status'],
                    'created_at'      => $row['created_at'],
                    'user_story'      => $row['user_story'],
                    'dod'             => $row['dod'],
                    'description'     => $row['description'],
                    'skills'          => json_decode($row['skills_json'] ?? '[]', true) ?: [],
                    'prompt_template' => $row['prompt_template'],
                    'locked_by'       => $row['locked_by'] ?? null,
                    'locked_at'       => $row['locked_at'] ?? null,
                ];
            }
        }

        // Meetings del proyecto activo
        $meetingsStmt = $this->db->prepare("SELECT * FROM meetings WHERE project_id = ? OR project_id = 'default' ORDER BY created_at DESC");
        $meetingsStmt->execute([$activeProjectId]);
        $meetings = [];
        while ($mRow = $meetingsStmt->fetch()) {
            $turnsStmt = $this->db->prepare("SELECT * FROM meeting_turns WHERE meeting_id = ? ORDER BY turn_index ASC");
            $turnsStmt->execute([$mRow['id']]);
            $turns = [];
            while ($tRow = $turnsStmt->fetch()) {
                $turns[] = [
                    'role_id'   => $tRow['role_id'],
                    'name'      => $tRow['name'],
                    'alias'     => $tRow['alias'],
                    'avatar'    => $tRow['avatar'],
                    'ai_model'  => $tRow['ai_model'],
                    'statement' => $tRow['statement'],
                    'timestamp' => $tRow['timestamp'],
                ];
            }

            $meetings[] = [
                'id'           => $mRow['id'],
                'title'        => $mRow['title'],
                'type'         => $mRow['type'],
                'topic'        => $mRow['topic'],
                'created_at'   => $mRow['created_at'],
                'participants' => json_decode($mRow['participants_json'] ?? '[]', true) ?: [],
                'turns'        => $turns,
                'summary'      => $mRow['summary'],
                'resolution'   => $mRow['resolution'],
                'action_items' => json_decode($mRow['action_items_json'] ?? '[]', true) ?: [],
                'converted'    => (bool)$mRow['converted']
            ];
        }

        // Chat messages (últimos 50)
        $chatStmt = $this->db->query(
            "SELECT * FROM (SELECT rowid AS _rid, * FROM chat_messages ORDER BY created_at DESC, rowid DESC LIMIT 50)
             ORDER BY created_at ASC, _rid ASC"
        );
        $chatMessages = [];
        while ($cRow = $chatStmt->fetch()) {
            $chatMessages[] = [
                'id'          => $cRow['id'],
                'sender_role' => $cRow['sender_role'],
                'sender_name' => $cRow['sender_name'],
                'avatar'      => $cRow['avatar'],
                'timestamp'   => $cRow['timestamp'],
                'text'        => $cRow['text'],
                'ai_model'    => $cRow['ai_model'] ?? null,
                'ai_provider' => $cRow['ai_provider'] ?? null
            ];
        }

        // Trazabilidad de eventos recientes (Audit Feed para MCP & Web)
        $eventsStmt = $this->db->query(
            "SELECT e.*, t.title AS task_title 
             FROM task_events e 
             LEFT JOIN tasks t ON e.task_id = t.id 
             ORDER BY e.created_at DESC LIMIT 30"
        );
        $recentEvents = [];
        while ($eRow = $eventsStmt->fetch()) {
            $eRow['payload'] = json_decode($eRow['payload_json'] ?? '{}', true);
            unset($eRow['payload_json']);
            $recentEvents[] = $eRow;
        }

        $heuristics = $this->getSetting('ux_heuristics_evaluation', null);

        return [
            'org_name'              => $orgName,
            'active_workspace'      => $activeWorkspace,
            'projects'              => $projects,
            'current_project'       => $currentProject,
            'jurisdiction'          => $jurisdiction,
            'ai_engine'             => $aiEngine,
            'local_ai'              => $this->getLocalAiState($settings),
            'sprint'                => $sprint,
            'available_skills_pool' => $skillsPool,
            'skills_catalog'        => $skillsCatalog,
            'roles'                 => $roles,
            'tasks'                 => $tasks,
            'chat_messages'         => $chatMessages,
            'ui_preferences'        => $uiPreferences,
            'ux_heuristics'         => $heuristics,
            'meetings'              => $meetings,
            'recent_events'         => $recentEvents,
            'costs_summary'         => $costSummary,
        ];
    }

    /** Estado guardado de la IA local (sin consultar Ollama: la web lo refresca con detect_local_ai). */
    private function getLocalAiState(array $settings): array
    {
        $state = is_array($settings['local_ai'] ?? null) ? $settings['local_ai'] : [];
        return array_merge([
            'enabled'      => false,
            'runtime'      => 'ollama',
            'model'        => null,
            'num_ctx'      => 8192,
            'detected'     => false,
            'available'    => false,
            'runtimes'     => [],
            'last_checked' => null,
        ], $state);
    }

    public function getSetting(string $key, $default = null)
    {
        $stmt = $this->db->prepare("SELECT value_json FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }
        return json_decode($row['value_json'], true) ?? $default;
    }

    public function setSetting(string $key, $value): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO settings (key, value_json, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$key, json_encode($value, JSON_UNESCAPED_UNICODE)]);
    }

    public function getAllSettings(): array
    {
        $stmt = $this->db->query("SELECT key, value_json FROM settings");
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[$row['key']] = json_decode($row['value_json'], true);
        }
        return $out;
    }

    /**
     * Catálogo de Skills: Lee directamente de plugin/agileforge/skills/ (Única fuente de verdad)
     */
    public function getSkillsCatalog(): array
    {
        $skillsDir = dirname(__DIR__) . '/plugin/agileforge/skills';
        $skillFiles = glob($skillsDir . '/*/SKILL.md');

        if (!empty($skillFiles)) {
            $catalog = [];
            foreach ($skillFiles as $file) {
                $content = (string)file_get_contents($file);
                $id = basename(dirname($file));
                $name = $id;
                $category = 'general';
                $level = 'Principal';
                $tags = [$category];
                $status = 'active';
                $desc = '';

                if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                    $frontmatter = $matches[1];
                    $body = trim($matches[2]);

                    if (preg_match('/name:\s*([^\n]+)/', $frontmatter, $m)) $id = trim($m[1], " \"'");
                    if (preg_match('/title:\s*([^\n]+)/', $frontmatter, $m)) $name = trim($m[1], " \"'");
                    if (preg_match('/category:\s*([^\n]+)/', $frontmatter, $m)) $category = trim($m[1], " \"'");
                    if (preg_match('/level:\s*([^\n]+)/', $frontmatter, $m)) $level = trim($m[1], " \"'");
                    if (preg_match('/status:\s*([^\n]+)/', $frontmatter, $m)) $status = trim($m[1], " \"'");
                    if (preg_match('/tags:\s*(\[[^\]]+\])/', $frontmatter, $m)) {
                        $parsedTags = json_decode($m[1], true);
                        if (is_array($parsedTags)) $tags = $parsedTags;
                    }

                    if (preg_match('/>[^\n]+\n\n([^\n#]+)/', $body, $m)) {
                        $desc = trim($m[1]);
                    }
                }

                $catalog[] = [
                    'id'        => $id,
                    'name'      => $name,
                    'level'     => $level,
                    'category'  => $category,
                    'desc'      => $desc ?: "Skill {$name}",
                    'tags'      => $tags,
                    'status'    => $status,
                    'is_custom' => ($status === 'draft')
                ];
            }

            usort($catalog, fn($a, $b) => strcmp($a['name'], $b['name']));
            return $catalog;
        }

        $skillsStmt = $this->db->query("SELECT * FROM skills ORDER BY name ASC");
        $skills = [];
        while ($row = $skillsStmt->fetch()) {
            $row['tags'] = json_decode($row['tags_json'] ?? '[]', true) ?: [];
            $skills[] = $row;
        }
        return $skills;
    }

    // --- Métodos de Tareas ---

    public function getTasks(array $filters = []): array
    {
        $sql = "SELECT * FROM tasks WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['role'])) {
            $sql .= " AND assigned_to = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $tasks = [];
        while ($row = $stmt->fetch()) {
            $row['skills'] = json_decode($row['skills_json'] ?? '[]', true) ?: [];
            unset($row['skills_json']);
            $tasks[] = $row;
        }
        return $tasks;
    }

    public function getTask(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['skills'] = json_decode($row['skills_json'] ?? '[]', true) ?: [];
        $row['review_evidence'] = json_decode($row['review_evidence_json'] ?? 'null', true);
        unset($row['skills_json'], $row['review_evidence_json']);

        // Agregar comentarios y eventos
        $cStmt = $this->db->prepare("SELECT * FROM comments WHERE task_id = ? ORDER BY created_at ASC");
        $cStmt->execute([$id]);
        $row['comments'] = $cStmt->fetchAll();

        $eStmt = $this->db->prepare("SELECT * FROM task_events WHERE task_id = ? ORDER BY created_at ASC");
        $eStmt->execute([$id]);
        $row['events'] = $eStmt->fetchAll();

        return $row;
    }

    public function updateTaskStatus(string $id, string $status, ?string $note = null, ?string $roleKey = null, ?string $client = 'web'): bool
    {
        $validStatuses = ['backlog', 'en_progreso', 'revision', 'completada'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        return Database::transaction(function (PDO $pdo) use ($id, $status, $note, $roleKey, $client) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, $id]);

            // Registrar evento en historial
            $eStmt = $pdo->prepare("INSERT INTO task_events (task_id, role_key, client, event_type, payload_json) VALUES (?, ?, ?, ?, ?)");
            $payload = json_encode(['status' => $status, 'note' => $note], JSON_UNESCAPED_UNICODE);
            $eStmt->execute([$id, $roleKey, $client, 'status_change', $payload]);

            if ($note) {
                $cStmt = $pdo->prepare("INSERT INTO comments (id, task_id, author_role, author_name, avatar, content) VALUES (?, ?, ?, ?, ?, ?)");
                $cStmt->execute([
                    'COM-' . uniqid(),
                    $id,
                    $roleKey ?? 'system',
                    $roleKey ?? 'AgileForge System',
                    '🤖',
                    $note
                ]);
            }

            return true;
        });
    }

    public function claimTask(string $id, string $roleKey, string $client = 'mcp'): array
    {
        return Database::transaction(function (PDO $pdo) use ($id, $roleKey, $client) {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();

            if (!$task) {
                return ['success' => false, 'error' => 'Tarea no encontrada', 'code' => 404];
            }

            // Verificar lock existente
            if (!empty($task['locked_by']) && $task['locked_by'] !== $roleKey) {
                $lockedAt = strtotime($task['locked_at'] ?? 'now');
                // Lock expira en 30 minutos si no se renueva
                if ((time() - $lockedAt) < 1800) {
                    return [
                        'success' => false,
                        'error'   => "La tarea ya está bloqueada por el rol: {$task['locked_by']}",
                        'code'    => 409
                    ];
                }
            }

            $uStmt = $pdo->prepare("UPDATE tasks SET locked_by = ?, locked_at = CURRENT_TIMESTAMP, status = 'en_progreso', assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $uStmt->execute([$roleKey, $roleKey, $id]);

            $eStmt = $pdo->prepare("INSERT INTO task_events (task_id, role_key, client, event_type, payload_json) VALUES (?, ?, ?, ?, ?)");
            $payload = json_encode(['action' => 'claim_task', 'role' => $roleKey], JSON_UNESCAPED_UNICODE);
            $eStmt->execute([$id, $roleKey, $client, 'claimed', $payload]);

            return ['success' => true, 'task_id' => $id, 'locked_by' => $roleKey];
        });
    }

    public function recordEvent(string $taskId, ?string $roleKey, string $eventType, array $payload, string $client = 'web'): void
    {
        $stmt = $this->db->prepare("INSERT INTO task_events (task_id, role_key, client, event_type, payload_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$taskId, $roleKey, $client, $eventType, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    public function addComment(string $taskId, string $authorRole, string $authorName, string $avatar, string $content): string
    {
        $id = 'COM-' . uniqid();
        $stmt = $this->db->prepare("INSERT INTO comments (id, task_id, author_role, author_name, avatar, content) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $taskId, $authorRole, $authorName, $avatar, $content]);
        return $id;
    }

    public function recordDecision(string $id, string $title, string $context, string $decision, string $consequences, string $status = 'accepted'): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO decisions (id, code, title, context, decision, consequences, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET
                title = excluded.title, context = excluded.context, decision = excluded.decision,
                consequences = excluded.consequences, status = excluded.status"
        );
        $stmt->execute([$id, $id, $title, $context, $decision, $consequences, $status]);
    }
}

