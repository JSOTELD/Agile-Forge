<?php
/**
 * AgileForge - Repositorio de Datos
 * Abstracción sobre SQLite que proporciona métodos de consulta relacionales
 * y mantiene compatibilidad con la estructura completa de datos requerida por el frontend.
 */

declare(strict_types=1);

namespace AgileForge;

require_once __DIR__ . '/services/LlmService.php';
require_once __DIR__ . '/services/EngineService.php';

use AgileForge\Services\EngineService;
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
        $stmt->closeCursor();
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
        $aiEngine['role_routing_enabled'] = !array_key_exists('role_routing_enabled', $aiEngine) || !empty($aiEngine['role_routing_enabled']);
        // Cargar proveedores dinámicos desde config/models.json con estado real de api_key_configured
        $aiEngine['available_providers'] = $this->llm->getProviders();
        $aiEngine['api_key_configured'] = $this->llm->isProviderConfigured((string)$aiEngine['active_provider']);
        $localAi = $this->getLocalAiState($settings);

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
        $sprintStmt->closeCursor();
        // Sin sprint activo en ESTE proyecto no se toma prestado el de otro proyecto: se muestra uno genérico

        if ($sprintRow) {
            $sprint = [
                'id'              => $sprintRow['id'],
                'number'          => (int)$sprintRow['number'],
                'name'            => $sprintRow['name'],
                'goal'            => $sprintRow['goal'],
                'days_remaining'  => self::sprintDaysLeft($sprintRow),
                'velocity_points' => (int)$sprintRow['velocity_points'],
                'budget_usd'      => (float)($sprintRow['budget_usd'] ?? 0.0),
            ];
        } else {
            $sprint = [
                'id'              => 'default',
                'number'          => 1,
                'name'            => 'Sprint 1',
                'goal'            => 'Sin sprint activo: inicia uno desde Claude Code (/sprint) o con el Director.',
                'days_remaining'  => 0,
                'velocity_points' => 20,
                'budget_usd'      => 0.0,
            ];
        }

        // Roles
        $rolesStmt = $this->db->query("SELECT * FROM roles ORDER BY CASE id WHEN 'director' THEN 0 ELSE 1 END, id ASC");
        $roles = [];
        while ($row = $rolesStmt->fetch()) {
            $roles[] = [
                'engine'          => EngineService::describe($row, $aiEngine, $aiEngine['available_providers'], $localAi),
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
        $meetingsStmt = $this->db->prepare("SELECT * FROM meetings WHERE project_id = ? ORDER BY created_at DESC, rowid DESC LIMIT 30");
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
                'ai_provider' => $cRow['ai_provider'] ?? null,
                'meta'        => !empty($cRow['meta_json']) ? json_decode($cRow['meta_json'], true) : null,
            ];
        }

        // Trazabilidad de eventos recientes (Audit Feed para MCP & Web)
        // Solo los eventos de historias del proyecto activo (antes se mezclaban los de todos los proyectos)
        $eventsStmt = $this->db->prepare(
            "SELECT e.*, t.title AS task_title
             FROM task_events e
             JOIN tasks t ON e.task_id = t.id
             WHERE t.project_id = ?" . ($activeProjectId === 'default' ? " OR t.project_id IS NULL OR t.project_id = ''" : '') . "
             ORDER BY e.created_at DESC, e.id DESC LIMIT 30"
        );
        $eventsStmt->execute([$activeProjectId]);
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
            'local_ai'              => $localAi,
            'decisions'             => $this->getRecentDecisions($activeProjectId),
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

    private function getRecentDecisions(string $projectId): array
    {
        $stmt = $this->db->prepare("SELECT id, title, context, decision, consequences, status, created_at FROM decisions WHERE project_id = ? OR project_id = 'default' ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll() ?: [];
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
        $stmt->closeCursor();
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
        $fromFiles = self::skillsFromFiles();
        if ($fromFiles) {
            // + las skills personalizadas creadas desde la interfaz (is_custom = 1), que no tienen SKILL.md:
            // antes se guardaban pero nunca aparecían en el catálogo
            $known = array_flip(array_column($fromFiles, 'id'));
            $customStmt = $this->db->query("SELECT * FROM skills WHERE is_custom = 1 ORDER BY name ASC");
            while ($row = $customStmt->fetch()) {
                if (isset($known[$row['id']])) {
                    continue;
                }
                $fromFiles[] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'level'     => $row['level'] ?? 'Principal',
                    'category'  => $row['category'] ?? 'Custom',
                    'desc'      => $row['description'] ?? '',
                    'tags'      => json_decode($row['tags_json'] ?? '[]', true) ?: [],
                    'status'    => 'active',
                    'is_custom' => true,
                ];
            }
            return $fromFiles;
        }

        $skillsStmt = $this->db->query("SELECT * FROM skills ORDER BY name ASC");
        $skills = [];
        while ($row = $skillsStmt->fetch()) {
            $row['tags'] = json_decode($row['tags_json'] ?? '[]', true) ?: [];
            $skills[] = $row;
        }
        return $skills;
    }

    /**
     * Normaliza una lista de skills: solo IDs con forma de slug que existan en el catálogo (archivos + personalizadas),
     * sin repetidos. Evita IDs inventados (ADR-005) y texto arbitrario que luego se pinta en la interfaz.
     */
    public function cleanSkillIds(array $skills): array
    {
        $valid = array_flip(array_column($this->getSkillsCatalog(), 'id'));
        $out = [];
        foreach ($skills as $s) {
            $s = trim((string)$s);
            if ($s !== '' && preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/i', $s) && isset($valid[$s]) && !in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /**
     * Lee los SKILL.md de plugin/agileforge/skills/*: una entrada por skill (id, name, level, category, desc, tags, status, is_custom).
     * Devuelve [] si no hay ninguno. También lo usa Database::syncSkillCatalog() para sincronizar la tabla skills.
     */
    public static function skillsFromFiles(): array
    {
        $skillsDir = dirname(__DIR__) . '/plugin/agileforge/skills';
        $skillFiles = glob($skillsDir . '/*/SKILL.md') ?: [];
        if (!$skillFiles) {
            return [];
        }
        $catalog = [];
        foreach ($skillFiles as $file) {
            $content = str_replace("\r\n", "\n", (string)file_get_contents($file));
            $id = basename(dirname($file));
            $name = $id;
            $category = 'general';
            $level = 'Principal';
            $tags = [$category];
            $status = 'active';
            $desc = '';

            if (preg_match('/^---[ \t]*\n(.*?)\n---[ \t]*\n(.*)$/s', $content, $matches)) {
                $frontmatter = $matches[1];
                $body = trim($matches[2]);

                // Claves ancladas al inicio de línea: "description: … name: …" no se confunde con la clave name:
                $field = static function (string $key) use ($frontmatter): ?string {
                    if (preg_match('/^' . $key . ':[ \t]*(.+)$/m', $frontmatter, $m)) {
                        $v = trim($m[1], " \t\"'");
                        return $v === '' ? null : $v;
                    }
                    return null;
                };

                $id = $field('name') ?? $id;
                $name = $field('title') ?? $name;
                $category = $field('category') ?? $category;
                $level = $field('level') ?? $level;
                $status = $field('status') ?? $status;
                $desc = $field('description') ?? '';
                if (preg_match('/^tags:[ \t]*(\[[^\]]*\])[ \t]*$/m', $frontmatter, $m)) {
                    $parsedTags = json_decode($m[1], true);
                    if (is_array($parsedTags)) $tags = $parsedTags;
                }

                if ($desc === '' && preg_match('/>[^\n]+\n\n([^\n#]+)/', $body, $m)) {
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
        $stmt->closeCursor();
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

    public function updateTaskStatus(string $id, string $status, ?string $note = null, ?string $roleKey = null, ?string $client = 'api'): bool
    {
        $validStatuses = ['backlog', 'en_progreso', 'revision', 'completada'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        return Database::transaction(function (PDO $pdo) use ($id, $status, $note, $roleKey, $client) {
            // ADR-004: el lock se libera al pasar a completada o backlog
            $release = in_array($status, ['completada', 'backlog'], true) ? ', locked_by = NULL, locked_at = NULL' : '';
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?{$release}, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
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
            $stmt->closeCursor();

            if (!$task) {
                return ['success' => false, 'error' => 'Tarea no encontrada', 'code' => 404];
            }

            // Una historia completada no se reabre tomándola (H6): hace falta que un humano la mueva desde el tablero
            if (($task['status'] ?? '') === 'completada') {
                return ['success' => false, 'error' => "La historia {$id} ya está completada: no se puede tomar (si hay que reabrirla, que un humano la mueva desde el tablero).", 'code' => 409];
            }

            // Verificar lock existente
            if (!empty($task['locked_by']) && $task['locked_by'] !== $roleKey) {
                // locked_at es CURRENT_TIMESTAMP de SQLite (UTC): se interpreta como UTC, no con la zona horaria de PHP
                $lockedAt = self::utcTimestamp($task['locked_at'] ?? null) ?? time();
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

    public function recordEvent(string $taskId, ?string $roleKey, string $eventType, array $payload, string $client = 'api'): void
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

    public function recordDecision(string $id, string $title, string $context, string $decision, string $consequences, string $status = 'accepted', ?string $projectId = null): void
    {
        $projectId = ($projectId !== null && trim($projectId) !== '') ? trim($projectId) : (string)($this->getActiveProject()['id'] ?? 'default');
        $stmt = $this->db->prepare(
            "INSERT INTO decisions (id, project_id, code, title, context, decision, consequences, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET
                title = excluded.title, context = excluded.context, decision = excluded.decision,
                consequences = excluded.consequences, status = excluded.status"
        );
        $stmt->execute([$id, $projectId, $id, $title, $context, $decision, $consequences, $status]);
    }

    public function projectExists(string $id): bool
    {
        if ($id === 'default') {
            return true; // proyecto implícito aunque la tabla esté vacía
        }
        $stmt = $this->db->prepare("SELECT 1 FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $found = (bool)$stmt->fetchColumn();
        $stmt->closeCursor();
        return $found;
    }

    /**
     * Roles que tienen o tuvieron el lock de una historia (lock actual + quienes la tomaron o la entregaron).
     * Sirve para la regla ADR-004 "el revisor no es quien la tomó".
     */
    public function getLockRoles(string $taskId): array
    {
        $roles = [];
        $stmt = $this->db->prepare("SELECT locked_by FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $lock = (string)($stmt->fetchColumn() ?: '');
        $stmt->closeCursor();
        if ($lock !== '') {
            $roles[] = $lock;
        }
        $ev = $this->db->prepare("SELECT DISTINCT role_key FROM task_events WHERE task_id = ? AND event_type IN ('claimed', 'submitted_for_review') AND role_key IS NOT NULL AND role_key != ''");
        $ev->execute([$taskId]);
        foreach ($ev->fetchAll(PDO::FETCH_COLUMN) as $r) {
            $roles[] = (string)$r;
        }
        return array_values(array_unique($roles));
    }

    // ------------------------------------------------------------------
    // Vistas ligeras de solo lectura (overview, eventos, reuniones, decisiones, historias compactas)
    // ------------------------------------------------------------------

    private static function clip(?string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text) ?? '');
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }

    private static function iso(?string $sqlDate): ?string
    {
        if ($sqlDate === null || $sqlDate === '') {
            return null;
        }
        $ts = self::utcTimestamp($sqlDate);
        return $ts === null ? $sqlDate : gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /** Timestamp Unix de una fecha de SQLite (CURRENT_TIMESTAMP guarda UTC sin zona). */
    public static function utcTimestamp(?string $sqlDate): ?int
    {
        if ($sqlDate === null || trim($sqlDate) === '') {
            return null;
        }
        $ts = strtotime($sqlDate . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $sqlDate) ? '' : ' UTC'));
        return $ts === false ? null : $ts;
    }

    /**
     * Días que le quedan a un sprint. Con ends_at (fecha de cierre en UTC) se calculan con el reloj;
     * sin ella (bases antiguas) se usa el número guardado.
     */
    public static function sprintDaysLeft(array $sprintRow): int
    {
        $end = self::utcTimestamp($sprintRow['ends_at'] ?? null);
        if ($end === null) {
            return max(0, (int)($sprintRow['days_remaining'] ?? 0));
        }
        return max(0, (int)ceil(($end - time()) / 86400));
    }

    /** Proyecto por id, o el activo. Lanza InvalidArgumentException si el id no existe. */
    public function resolveProject(?string $projectId): array
    {
        if ($projectId === null || trim($projectId) === '') {
            return $this->getActiveProject();
        }
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([trim($projectId)]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        if (!$row) {
            if (trim($projectId) === 'default') {
                return $this->getActiveProject(); // base vacía: proyecto 'default' implícito
            }
            throw new \InvalidArgumentException("El proyecto '{$projectId}' no existe");
        }
        return $row;
    }

    /** Fragmento SQL + parámetros para filtrar por proyecto (el proyecto 'default' incluye filas sin project_id). */
    private function projectClause(string $col, string $projectId): array
    {
        if ($projectId === 'default') {
            return ["({$col} = ? OR {$col} IS NULL OR {$col} = '')", [$projectId]];
        }
        return ["{$col} = ?", [$projectId]];
    }

    /** Historias en forma compacta (overview.tasks). Filtros opcionales: status, role, priority. */
    public function getCompactTasks(?string $projectId = null, array $filters = [], int $limit = 200, bool $recentFirst = false): array
    {
        $pid = (string)($this->resolveProject($projectId)['id'] ?? 'default');
        [$cl, $params] = $this->projectClause('t.project_id', $pid);
        $sql = "SELECT t.id, t.title, t.status, t.assigned_to, r.name AS role_name, r.avatar, t.story_points, t.priority,
                       t.locked_by, t.locked_at, t.updated_at
                FROM tasks t LEFT JOIN roles r ON r.id = t.assigned_to WHERE {$cl}";
        foreach (['status' => 't.status', 'role' => 't.assigned_to', 'priority' => 't.priority'] as $f => $col) {
            if (!empty($filters[$f]) && is_string($filters[$f])) {
                $sql .= " AND {$col} = ?";
                $params[] = $filters[$f];
            }
        }
        $sql .= $recentFirst ? " ORDER BY t.updated_at DESC, t.rowid DESC LIMIT " . max(1, min(200, $limit)) : " ORDER BY CASE t.status WHEN 'en_progreso' THEN 0 WHEN 'revision' THEN 1 WHEN 'backlog' THEN 2 ELSE 3 END,
                  CASE t.priority WHEN 'critica' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END, t.created_at DESC, t.rowid DESC
                  LIMIT " . max(1, min(200, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'id'           => $r['id'],
                'title'        => self::clip($r['title'], 160),
                'status'       => $r['status'],
                'assigned_to'  => $r['assigned_to'],
                'role_name'    => $r['role_name'] ?? $r['assigned_to'],
                'avatar'       => $r['avatar'] ?? '🤖',
                'story_points' => (int)$r['story_points'],
                'priority'     => $r['priority'],
                'locked_by'    => ($r['locked_by'] ?? '') !== '' ? $r['locked_by'] : null,
                'locked_at'    => self::iso($r['locked_at'] ?? null),
                'updated_at'   => self::iso($r['updated_at'] ?? null),
            ];
        }
        return $out;
    }

    /** Reuniones en forma compacta (overview.meetings): sin turnos completos, con los 3 últimos. */
    public function getCompactMeetings(?string $projectId = null, int $limit = 5, int $lastTurns = 3, int $clip = 280): array
    {
        $pid = (string)($this->resolveProject($projectId)['id'] ?? 'default');
        [$cl, $params] = $this->projectClause('project_id', $pid);
        $stmt = $this->db->prepare(
            "SELECT m.id, m.title, m.type, m.resolution, m.created_at, m.updated_at,
                    (SELECT COUNT(*) FROM meeting_turns mt WHERE mt.meeting_id = m.id) AS turns_count
             FROM meetings m WHERE {$cl} ORDER BY m.created_at DESC, m.rowid DESC LIMIT " . max(1, min(50, $limit))
        );
        $stmt->execute($params);
        $turnStmt = $this->db->prepare(
            "SELECT role_id, name, avatar, statement, timestamp FROM meeting_turns WHERE meeting_id = ? ORDER BY turn_index DESC LIMIT " . max(0, min(10, $lastTurns))
        );
        $out = [];
        foreach ($stmt->fetchAll() as $m) {
            $turnStmt->execute([$m['id']]);
            $turns = [];
            foreach (array_reverse($turnStmt->fetchAll()) as $t) {
                $turns[] = [
                    'role_id'   => $t['role_id'],
                    'name'      => $t['name'] ?: $t['role_id'],
                    'avatar'    => $t['avatar'] ?: '🤖',
                    'statement' => self::clip($t['statement'], $clip),
                    'timestamp' => $t['timestamp'],
                ];
            }
            $out[] = [
                'id'          => $m['id'],
                'title'       => self::clip($m['title'], 160),
                'type'        => $m['type'],
                'resolution'  => self::clip($m['resolution'], $clip),
                'turns_count' => (int)$m['turns_count'],
                'created_at'  => self::iso($m['created_at']),
                'updated_at'  => self::iso($m['updated_at']),
                'last_turns'  => $turns,
            ];
        }
        return $out;
    }

    /** Decisiones (ADR) en forma compacta. Incluye las del proyecto y las globales ('default'). */
    public function getCompactDecisions(?string $projectId = null, int $limit = 10, int $clip = 280): array
    {
        $pid = (string)($this->resolveProject($projectId)['id'] ?? 'default');
        $stmt = $this->db->prepare(
            "SELECT id, title, decision, status, created_at FROM decisions
             WHERE project_id = ? OR project_id = 'default' OR project_id IS NULL ORDER BY created_at DESC, rowid DESC LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute([$pid]);
        $out = [];
        foreach ($stmt->fetchAll() as $d) {
            $out[] = [
                'id'         => $d['id'],
                'title'      => self::clip($d['title'], 160),
                'decision'   => self::clip($d['decision'], $clip),
                'status'     => $d['status'],
                'created_at' => self::iso($d['created_at']),
            ];
        }
        return $out;
    }

    private static function eventMessage(array $e, array $payload): string
    {
        $task = (string)$e['task_id'];
        $title = $e['task_title'] ? ' (' . self::clip($e['task_title'], 70) . ')' : '';
        switch ($e['event_type']) {
            case 'task_created':
                $msg = "{$task} creada{$title}";
                break;
            case 'claimed':
                $msg = "{$task} tomada por " . ($payload['role'] ?? $e['role_key'] ?? 'un rol') . $title;
                break;
            case 'status_change':
                $msg = "{$task} pasó a " . ($payload['status'] ?? '?') . $title;
                break;
            case 'submitted_for_review':
                $msg = "{$task} entregada a revisión{$title}";
                break;
            case 'task_reviewed':
                $msg = "{$task} revisada: " . ($payload['decision'] ?? '?') . $title;
                break;
            case 'comment_added':
                $msg = "{$task}: nuevo comentario{$title}";
                break;
            case 'task_updated':
                $msg = "{$task} actualizada{$title}";
                break;
            default:
                $msg = "{$task}: {$e['event_type']}{$title}";
        }
        return self::clip($msg, 200);
    }

    /** Eventos recientes (misma tabla que alimenta recent_events: task_events) en forma ligera y saneada. */
    public function getEvents(int $limit = 30, ?string $projectId = null): array
    {
        $pid = (string)($this->resolveProject($projectId)['id'] ?? 'default');
        [$cl, $params] = $this->projectClause('t.project_id', $pid);
        $stmt = $this->db->prepare(
            "SELECT e.id, e.task_id, e.role_key, e.client, e.event_type, e.payload_json, e.created_at, t.title AS task_title
             FROM task_events e JOIN tasks t ON t.id = e.task_id
             WHERE {$cl} ORDER BY e.created_at DESC, e.id DESC LIMIT " . max(1, min(100, $limit))
        );
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $e) {
            $payload = json_decode((string)($e['payload_json'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $out[] = [
                'id'         => (int)$e['id'],
                'type'       => $e['event_type'],
                'message'    => self::eventMessage($e, $payload),
                'actor'      => ($e['role_key'] ?? '') !== '' ? $e['role_key'] : ($e['client'] ?: 'system'),
                'task_id'    => $e['task_id'],
                'created_at' => self::iso($e['created_at']),
            ];
        }
        return $out;
    }

    /**
     * Crea un sprint activo nuevo (número = último + 1) y cierra el activo anterior del proyecto.
     * Lanza InvalidArgumentException si el proyecto no existe.
     */
    public function startSprint(?string $projectId, string $name, string $goal = '', ?int $days = null, ?int $velocity = null): array
    {
        $project = $this->resolveProject($projectId);
        $pid = (string)($project['id'] ?? 'default');
        $name = trim($name);
        if (mb_strlen($name) > 200) {
            throw new \InvalidArgumentException('El nombre del sprint no puede superar 200 caracteres');
        }
        $result = [];
        Database::transaction(function (\PDO $pdo) use ($pid, $name, $goal, $days, $velocity, &$result) {
            $q = $pdo->prepare("SELECT COALESCE(MAX(number), 0) FROM sprints WHERE project_id = ?");
            $q->execute([$pid]);
            $number = (int)$q->fetchColumn() + 1;
            $closed = $pdo->prepare("SELECT number FROM sprints WHERE project_id = ? AND status = 'active'");
            $closed->execute([$pid]);
            $closedNumbers = array_map('intval', $closed->fetchAll(\PDO::FETCH_COLUMN));
            $pdo->prepare("UPDATE sprints SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE project_id = ? AND status = 'active'")->execute([$pid]);
            $id = sprintf('SPRINT-%s-%02d', $pid, $number);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM sprints WHERE id = ?");
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                $id .= '-' . bin2hex(random_bytes(2));
            }
            $name = $name !== '' ? $name : "Sprint {$number}";
            $goal = trim($goal);
            $days = max(0, min(60, $days ?? 10));
            $velocity = max(1, min(500, $velocity ?? 20));
            // ends_at: fecha de cierre (UTC) para que los días restantes bajen solos con el calendario
            $pdo->prepare("INSERT INTO sprints (id, project_id, number, name, goal, days_remaining, velocity_points, status, ends_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'active', datetime('now', ?))")
                ->execute([$id, $pid, $number, $name, $goal, $days, $velocity, '+' . $days . ' days']);
            $result = [
                'id' => $id, 'project_id' => $pid, 'number' => $number, 'name' => $name, 'goal' => $goal,
                'days_remaining' => $days, 'velocity_points' => $velocity, 'status' => 'active',
                'closed_sprints' => $closedNumbers,
            ];
        });
        return $result;
    }

    public function getSprint(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM sprints WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getActiveSprint(?string $projectId = null): ?array
    {
        $pid = $projectId ?: ($this->getActiveProject()['id'] ?? 'default');
        $stmt = $this->db->prepare("SELECT * FROM sprints WHERE project_id = ? AND status = 'active' ORDER BY number DESC LIMIT 1");
        $stmt->execute([$pid]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Resumen ligero del tablero para vistas de solo lectura (contrato API ↔ MCP ↔ Vista).
     * Sin claves, rutas de disco, settings ni chat. Lanza InvalidArgumentException si el proyecto no existe.
     */
    public function getOverview(?string $projectId = null): array
    {
        $project = $this->resolveProject($projectId);
        $pid = (string)($project['id'] ?? 'default');

        // Sprint activo del proyecto (o el primero activo, como en getFullState)
        $sp = $this->db->prepare("SELECT * FROM sprints WHERE project_id = ? AND status = 'active' ORDER BY number DESC LIMIT 1");
        $sp->execute([$pid]);
        $sprintRow = $sp->fetch();
        $sp->closeCursor();

        // Columnas (sobre todas las historias del proyecto, no solo las 200 listadas)
        [$cl, $cp] = $this->projectClause('project_id', $pid);
        $cs = $this->db->prepare("SELECT status, COUNT(*) AS n, COALESCE(SUM(story_points), 0) AS pts FROM tasks WHERE {$cl} GROUP BY status");
        $cs->execute($cp);
        $columns = [
            'backlog'     => ['count' => 0, 'points' => 0],
            'en_progreso' => ['count' => 0, 'points' => 0],
            'revision'    => ['count' => 0, 'points' => 0],
            'completada'  => ['count' => 0, 'points' => 0],
        ];
        $total = 0;
        foreach ($cs->fetchAll() as $r) {
            if (isset($columns[$r['status']])) {
                $columns[$r['status']] = ['count' => (int)$r['n'], 'points' => (int)$r['pts']];
            }
            $total += (int)$r['pts'];
        }
        $done = $columns['completada']['points'];

        // Sin sprint activo en el proyecto → null (la Vista muestra «Sin sprint activo»)
        $sprint = $sprintRow ? [
            'number'          => (int)$sprintRow['number'],
            'name'            => $sprintRow['name'],
            'goal'            => self::clip($sprintRow['goal'] ?? '', 280),
            'days_remaining'  => self::sprintDaysLeft($sprintRow),
            'velocity_points' => (int)$sprintRow['velocity_points'],
            'points_done'     => $done,
            'points_total'    => $total,
            'progress_pct'    => $total > 0 ? (int)round($done * 100 / $total) : 0,
        ] : null;

        // Tamaño acotado: todas las no completadas + las 10 completadas más recientes (las columnas cuentan todo)
        $tasks = array_merge(
            $this->getCompactTasks($pid, ['status' => 'en_progreso'], 200),
            $this->getCompactTasks($pid, ['status' => 'revision'], 200),
            $this->getCompactTasks($pid, ['status' => 'backlog'], 200),
            $this->getCompactTasks($pid, ['status' => 'completada'], 10, true)
        );
        // Forma mínima por historia (el lock solo cuando existe; sin updated_at; título ≤ 64; rol abreviado)
        $tasks = array_map(static function (array $t): array {
            $t['title'] = self::clip($t['title'], 64);
            $t['role_name'] = trim(preg_split('/\s*[&,\/]\s*/u', (string)$t['role_name'])[0]);
            unset($t['updated_at']);
            foreach (['locked_by', 'locked_at'] as $k) {
                if ($t[$k] === null) {
                    unset($t[$k]);
                }
            }
            return $t;
        }, $tasks);

        // Quién trabaja ahora: historias en progreso con lock vigente
        $roleStmt = $this->db->query("SELECT id, name, avatar FROM roles");
        $roleMap = [];
        foreach ($roleStmt->fetchAll() as $r) {
            $roleMap[$r['id']] = $r;
        }
        $working = [];
        foreach ($tasks as $t) {
            if ($t['status'] === 'en_progreso' && !empty($t['locked_by'])) {
                $r = $roleMap[$t['locked_by']] ?? null;
                $working[] = [
                    'role_id'    => $t['locked_by'],
                    'name'       => $r['name'] ?? $t['locked_by'],
                    'avatar'     => $r['avatar'] ?? '🤖',
                    'task_id'    => $t['id'],
                    'task_title' => $t['title'],
                    'since'      => $t['locked_at'],
                ];
            }
        }

        // Cuentas de las últimas 24 h
        $since = gmdate('Y-m-d H:i:s', time() - 86400);
        $count = function (string $sql, array $params) {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return (int)$st->fetchColumn();
        };
        [$dcl, $dcp] = $this->projectClause('project_id', $pid);
        $sinceYesterday = [
            'completed'  => $count("SELECT COUNT(*) FROM tasks WHERE {$dcl} AND status = 'completada' AND updated_at >= ?", array_merge($dcp, [$since])),
            'to_review'  => $count("SELECT COUNT(*) FROM tasks WHERE {$dcl} AND status = 'revision' AND updated_at >= ?", array_merge($dcp, [$since])),
            'decisions'  => $count("SELECT COUNT(*) FROM decisions WHERE ({$dcl} OR project_id = 'default') AND created_at >= ?", array_merge($dcp, [$since])),
            'meetings'   => $count("SELECT COUNT(*) FROM meetings WHERE {$dcl} AND created_at >= ?", array_merge($dcp, [$since])),
        ];

        // Motor: modo plugin = ningún rol con motor listo y la IA local apagada
        $settings = $this->getAllSettings();
        $aiEngine = is_array($settings['ai_engine'] ?? null) ? $settings['ai_engine'] : [];
        $localAi = $this->getLocalAiState($settings);
        $providers = $this->llm->getProviders();
        $anyReady = false;
        foreach ($this->db->query("SELECT * FROM roles")->fetchAll() as $row) {
            if (!empty(EngineService::describe($row, $aiEngine, $providers, $localAi)['ready'])) {
                $anyReady = true;
                break;
            }
        }
        $localOn = !empty($localAi['enabled']);

        // Reuniones: forma mínima (sin created_at, título ≤ 70, nombre de rol abreviado, sin hora del turno)
        $meetings = array_map(static function (array $m): array {
            $m['title'] = self::clip($m['title'], 70);
            unset($m['created_at']);
            foreach ($m['last_turns'] as &$t) {
                $t['name'] = trim(preg_split('/\s*[&,\/]\s*/u', (string)$t['name'])[0]);
                unset($t['timestamp']);
            }
            unset($t);
            return $m;
        }, $this->getCompactMeetings($pid, 2, 1, 100));

        // Resumen de memoria nodular del proyecto
        $memStmt = $this->db->prepare("SELECT node_type, COUNT(*) as cnt FROM agent_memories WHERE project_id = ? AND is_active = 1 GROUP BY node_type");
        $memStmt->execute([$pid]);
        $memRows = $memStmt->fetchAll();
        $memSummary = ['total_active' => 0, 'by_type' => []];
        foreach ($memRows as $mr) {
            $memSummary['by_type'][$mr['node_type']] = (int)$mr['cnt'];
            $memSummary['total_active'] += (int)$mr['cnt'];
        }

        return [
            'success'         => true,
            'generated_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            'project'         => ['id' => $pid, 'name' => $project['name'] ?? $pid],
            'projects'        => array_map(
                static fn(array $p): array => ['id' => (string)$p['id'], 'name' => (string)$p['name'], 'active' => (int)($p['active'] ?? 0) === 1],
                $this->getProjects()
            ),
            'sprint'          => $sprint,
            'columns'         => $columns,
            'tasks'           => $tasks,
            'working'         => $working,
            'meetings'        => $meetings,
            'decisions'       => $this->getCompactDecisions($pid, 5, 160),
            'events'          => $this->getEvents(15, $pid),
            'since_yesterday' => $sinceYesterday,
            'engine'          => ['plugin_mode' => !$anyReady && !$localOn, 'local_ai_enabled' => $localOn],
            'memories'        => $memSummary,
        ];
    }

    /**
     * Gestión de Memoria Nodular por Proyecto y Rol
     */
    public function getMemories(?string $projectId = null, ?string $roleId = null, ?string $nodeType = null, bool $activeOnly = false, int $limit = 50): array
    {
        $clauses = [];
        $params = [];

        if ($projectId !== null && $projectId !== '') {
            $clauses[] = 'project_id = ?';
            $params[] = $projectId;
        }
        if ($roleId !== null && $roleId !== '') {
            $clauses[] = '(role_id = ? OR role_id = \'all\')';
            $params[] = $roleId;
        }
        if ($nodeType !== null && $nodeType !== '') {
            $clauses[] = 'node_type = ?';
            $params[] = $nodeType;
        }
        if ($activeOnly) {
            $clauses[] = 'is_active = 1';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        $sql = "SELECT * FROM agent_memories {$where} ORDER BY importance_score DESC, updated_at DESC LIMIT " . max(1, min(200, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(function ($r) {
            $r['importance_score'] = (int)$r['importance_score'];
            $r['is_active'] = (int)$r['is_active'];
            return $r;
        }, $rows ?: []);
    }

    public function getMemory(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM agent_memories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['importance_score'] = (int)$row['importance_score'];
        $row['is_active'] = (int)$row['is_active'];
        return $row;
    }

    public function saveMemory(array $data): ?array
    {
        $id = trim((string)($data['id'] ?? ''));
        if ($id === '') {
            $id = 'MEM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        $projectId = trim((string)($data['project_id'] ?? 'default'));
        if ($projectId === '') $projectId = 'default';

        $roleId = trim((string)($data['role_id'] ?? 'all'));
        if ($roleId === '') $roleId = 'all';

        $validTypes = ['progress', 'decision', 'learning', 'scratchpad', 'domain'];
        $nodeType = trim((string)($data['node_type'] ?? 'progress'));
        if (!in_array($nodeType, $validTypes, true)) {
            $nodeType = 'progress';
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('El título de la memoria es obligatorio.');
        }

        $contentText = trim((string)($data['content_text'] ?? $data['content'] ?? ''));
        if ($contentText === '') {
            throw new \InvalidArgumentException('El contenido de la memoria es obligatorio.');
        }

        $importance = isset($data['importance_score']) ? max(1, min(10, (int)$data['importance_score'])) : (isset($data['importance']) ? max(1, min(10, (int)$data['importance'])) : 5);
        $isActive = array_key_exists('is_active', $data) ? ($data['is_active'] ? 1 : 0) : 1;

        $stmt = $this->db->prepare(
            "INSERT INTO agent_memories (id, project_id, role_id, node_type, title, content_text, importance_score, is_active, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT(id) DO UPDATE SET
                project_id = excluded.project_id,
                role_id = excluded.role_id,
                node_type = excluded.node_type,
                title = excluded.title,
                content_text = excluded.content_text,
                importance_score = excluded.importance_score,
                is_active = excluded.is_active,
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$id, $projectId, $roleId, $nodeType, $title, $contentText, $importance, $isActive]);

        return $this->getMemory($id);
    }

    public function toggleMemory(string $id, ?bool $active = null): ?array
    {
        $existing = $this->getMemory($id);
        if (!$existing) {
            return null;
        }
        $newActive = $active !== null ? ($active ? 1 : 0) : ($existing['is_active'] ? 0 : 1);
        $stmt = $this->db->prepare("UPDATE agent_memories SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newActive, $id]);
        return $this->getMemory($id);
    }

    public function deleteMemory(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM agent_memories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function getCompactMemoriesForPrompt(string $projectId, string $roleId, int $maxChars = 1500): string
    {
        $sql = "SELECT node_type, title, content_text, importance_score FROM agent_memories 
                WHERE project_id = ? AND (role_id = ? OR role_id = 'all') AND is_active = 1 
                ORDER BY importance_score DESC, updated_at DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$projectId, $roleId]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return '';
        }

        $lines = [];
        $totalLen = 0;
        foreach ($rows as $r) {
            $t = self::clip($r['title'], 60);
            $c = self::clip(preg_replace('/\s+/', ' ', $r['content_text']), 180);
            $line = "• [{$r['node_type']}] {$t}: {$c}";
            if ($totalLen + strlen($line) + 2 > $maxChars) {
                break;
            }
            $lines[] = $line;
            $totalLen += strlen($line) + 2;
        }

        if (empty($lines)) {
            return '';
        }
        return "[MEMORIA NODULAR DEL PROYECTO ({$projectId})]\n" . implode("\n", $lines);
    }
}