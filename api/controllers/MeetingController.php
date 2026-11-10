<?php
/**
 * AgileForge - MeetingController
 * Controlador de Salas de Reuniones: Brainstorming, ADR, Compliance, Plenarias y Actas.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/EngineService.php';
require_once dirname(__DIR__) . '/services/BoardService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Services\BoardService;
use AgileForge\Services\EngineService;
use PDO;

class MeetingController
{
    private Repository $repo;
    private EngineService $engines;
    private BoardService $board;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->engines = new EngineService($this->repo);
        $this->board = new BoardService($this->repo);
    }

    public function list(array $query = []): void
    {
        $limit = isset($query['limit']) && is_numeric($query['limit']) ? max(1, min(100, (int)$query['limit'])) : null;
        $compact = !empty($query['compact']) && !in_array($query['compact'], ['0', 'false'], true);

        if ($compact) {
            try {
                $meetings = $this->repo->getCompactMeetings(isset($query['project']) ? (string)$query['project'] : null, $limit ?? 5, 3);
            } catch (\InvalidArgumentException $e) {
                Bootstrap::fail($e->getMessage(), 404);
            }
            Bootstrap::jsonResponse(['success' => true, 'count' => count($meetings), 'meetings' => $meetings], 200, false);
        }

        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM meetings ORDER BY created_at DESC, rowid DESC" . ($limit ? " LIMIT {$limit}" : ''));
        $meetings = [];
        while ($row = $stmt->fetch()) {
            $row['participants'] = json_decode($row['participants_json'] ?? '[]', true);
            $row['action_items'] = json_decode($row['action_items_json'] ?? '[]', true);
            $meetings[] = $row;
        }
        Bootstrap::jsonResponse(['success' => true, 'count' => count($meetings), 'meetings' => $meetings]);
    }

    public function show(string $id): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$id]);
        $meeting = $stmt->fetch();
        $stmt->closeCursor();

        if (!$meeting) {
            Bootstrap::jsonResponse(['error' => 'Reunión no encontrada'], 404);
        }

        $meeting['participants'] = json_decode($meeting['participants_json'] ?? '[]', true);
        $meeting['action_items'] = json_decode($meeting['action_items_json'] ?? '[]', true);

        $tStmt = $pdo->prepare("SELECT * FROM meeting_turns WHERE meeting_id = ? ORDER BY turn_index ASC");
        $tStmt->execute([$id]);
        $meeting['turns'] = $tStmt->fetchAll();

        Bootstrap::jsonResponse(['success' => true, 'meeting' => $meeting]);
    }

    public function start(array $input): void
    {
        $type = trim((string)($input['meeting_type'] ?? $input['type'] ?? 'brainstorming'));
        $topic = trim((string)($input['topic'] ?? ''));

        if ($topic === '') {
            Bootstrap::jsonResponse(['error' => 'El tema o tópico de la reunión es obligatorio'], 422);
        }

        $givenId = !empty($input['id']) && preg_match('/^[A-Za-z0-9_-]{2,40}$/', (string)$input['id']) ? trim((string)$input['id']) : null;
        $title = trim((string)($input['title'] ?? "Reunión: {$topic}"));
        $participants = is_array($input['participants'] ?? null) ? $input['participants'] : ['project_manager', 'lead_architect'];

        $projectId = trim((string)($input['project_id'] ?? $this->repo->getActiveProject()['id'] ?? 'default'));

        // ID calculado dentro de la transacción BEGIN IMMEDIATE (H4)
        $id = '';
        Database::transaction(function (PDO $pdo) use (&$id, $givenId, $projectId, $title, $type, $topic, $participants) {
            $id = $givenId ?? BoardService::nextId($pdo, 'meetings', 'MEET-');
            $stmt = $pdo->prepare(
                "INSERT INTO meetings (id, project_id, title, type, topic, participants_json, summary, resolution, action_items_json, converted)
                 VALUES (?, ?, ?, ?, ?, ?, '', '', '[]', 0)"
            );
            $stmt->execute([$id, $projectId, $title, $type, $topic, json_encode($participants, JSON_UNESCAPED_UNICODE)]);
        });

        $this->show($id);
    }

    private function requireMeeting(string $meetingId): void
    {
        $stmt = Database::getPdo()->prepare("SELECT 1 FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $found = (bool)$stmt->fetchColumn();
        $stmt->closeCursor();
        if (!$found) {
            Bootstrap::fail('Reunión no encontrada', 404);
        }
    }

    /** IDs de rol de los participantes de una reunión (acepta strings u objetos con id/role_id). */
    public static function participantIds($json): array
    {
        $list = is_array($json) ? $json : (json_decode((string)$json, true) ?: []);
        $ids = [];
        foreach ($list as $p) {
            $id = is_array($p) ? (string)($p['id'] ?? $p['role_id'] ?? '') : (string)$p;
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    public function addTurn(string $meetingId, array $input): void
    {
        $this->requireMeeting($meetingId);
        $roleId = trim((string)($input['role_id'] ?? 'unknown'));
        $statement = trim((string)($input['statement'] ?? $input['message'] ?? ''));

        if ($statement === '') {
            Bootstrap::jsonResponse(['error' => 'La intervención o declaración está vacía'], 422);
        }

        $name = trim((string)($input['name'] ?? $roleId));
        $alias = trim((string)($input['alias'] ?? ''));
        $avatar = trim((string)($input['avatar'] ?? '🤖'));
        $model = trim((string)($input['ai_model'] ?? ''));
        $timestamp = trim((string)($input['timestamp'] ?? date('H:i')));

        // Estado de la reunión, participantes e índice del turno se leen y escriben en la misma transacción (sin carreras con close)
        $result = Database::transaction(function (PDO $pdo) use ($meetingId, $roleId, $name, $alias, $avatar, $model, $statement, $timestamp) {
            $m = $pdo->prepare("SELECT closed_at, participants_json FROM meetings WHERE id = ?");
            $m->execute([$meetingId]);
            $meeting = $m->fetch();
            $m->closeCursor();
            if (!$meeting) {
                return ['error' => 'Reunión no encontrada', 'code' => 404];
            }
            if (!empty($meeting['closed_at'])) {
                return ['error' => "La reunión {$meetingId} ya está cerrada: no admite más turnos.", 'code' => 409];
            }
            $participants = self::participantIds($meeting['participants_json']);
            if ($participants && !in_array($roleId, $participants, true)) {
                return ['error' => "El rol '{$roleId}' no participa en la reunión {$meetingId} (participantes: " . implode(', ', $participants) . ').', 'code' => 422];
            }

            $idx = $pdo->prepare("SELECT COALESCE(MAX(turn_index), -1) + 1 FROM meeting_turns WHERE meeting_id = ?");
            $idx->execute([$meetingId]);
            $nextIndex = (int)$idx->fetchColumn();
            $pdo->prepare(
                "INSERT INTO meeting_turns (meeting_id, turn_index, role_id, name, alias, avatar, ai_model, statement, timestamp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$meetingId, $nextIndex, $roleId, $name, $alias, $avatar, $model, $statement, $timestamp]);
            $pdo->prepare("UPDATE meetings SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$meetingId]);
            return ['turn_index' => $nextIndex];
        });

        if (isset($result['error'])) {
            Bootstrap::fail($result['error'], (int)$result['code']);
        }
        Bootstrap::jsonResponse(['success' => true, 'turn_index' => $result['turn_index']]);
    }

    public function close(string $meetingId, array $input): void
    {
        $this->requireMeeting($meetingId);
        $summary = trim((string)($input['summary'] ?? ''));
        $resolution = trim((string)($input['resolution'] ?? ''));
        $actionItems = is_array($input['action_items'] ?? null) ? $input['action_items'] : [];

        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("UPDATE meetings SET summary = ?, resolution = ?, action_items_json = ?, closed_at = COALESCE(closed_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$summary, $resolution, json_encode($actionItems, JSON_UNESCAPED_UNICODE), $meetingId]);

        Bootstrap::jsonResponse(['success' => true, 'meeting_id' => $meetingId, 'closed' => true]);
    }

    // ------------------------------------------------------------------
    // Reuniones con IA real, turno por turno (cada petición es corta: no se corta por tiempo)
    // ------------------------------------------------------------------

    /** Crea la reunión y devuelve el orden de intervención. */
    public function begin(array $input): void
    {
        [$type, $topic, $participants] = $this->meetingParams($input);
        $engines = $this->requireSomeEngine($participants);
        $meeting = $this->createMeeting($type, $topic, $participants);

        $plan = [];
        foreach ($participants as $i => $role) {
            $eng = $engines[$i];
            $plan[] = ['role_id' => $role['id'], 'name' => $role['name'], 'avatar' => $role['avatar'], 'engine' => $eng['ready'] ? $eng['label'] : 'sin motor'];
        }
        Bootstrap::jsonResponse(['success' => true, 'meeting' => $meeting, 'plan' => $plan]);
    }

    /**
     * Una reunión con IA en la web necesita que al menos un participante tenga motor. Si ninguno lo tiene
     * (p. ej. modo plugin, donde el cerebro es Claude Code) no se crea una reunión vacía llena de avisos:
     * se responde 409 con lo que hay que hacer. Devuelve el motor resuelto de cada participante.
     */
    private function requireSomeEngine(array $participants): array
    {
        $engines = [];
        foreach ($participants as $role) {
            $engines[] = $this->engines->resolveForRole($role);
        }
        if (!array_filter($engines, static fn(array $e) => !empty($e['ready']))) {
            Bootstrap::fail(
                'Ningún participante tiene motor de IA en la web, así que la reunión no se creó. '
                . 'Convócala desde Claude Code con /reunion (plugin AgileForge Control), o agrega una clave en Motor IA / activa la IA local.',
                409
            );
        }
        return $engines;
    }

    /** Genera la intervención de un participante con su propio motor de IA. */
    public function turn(string $meetingId, string $roleId, bool $stream = false): void
    {
        $meeting = $this->loadMeeting($meetingId);
        $role = $this->board->role($roleId);
        if (!$role) {
            Bootstrap::jsonResponse(['success' => false, 'error' => "El agente '{$roleId}' no existe"], 404);
        }
        if (!empty($meeting['closed_at'])) {
            Bootstrap::fail("La reunión {$meetingId} ya está cerrada: no admite más turnos.", 409);
        }
        $participants = self::participantIds($meeting['participants_json'] ?? '[]');
        if ($participants && !in_array($roleId, $participants, true)) {
            Bootstrap::fail("El rol '{$roleId}' no participa en la reunión {$meetingId}.", 422);
        }
        @set_time_limit(400);
        $turn = $this->generateTurn($meeting, $role);

        if ($stream) {
            header('Content-Type: text/event-stream; charset=UTF-8');
            header('Cache-Control: no-cache, no-transform');
            header('X-Accel-Buffering: no');
            header('Connection: keep-alive');
            while (@ob_end_flush());
            @ob_implicit_flush(1);

            $send = static function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            };

            $send('start', ['role_id' => $roleId, 'name' => $role['name']]);
            $chunks = preg_split('/(?<=[ \n\.\,\!\?])/u', (string)$turn['statement'], -1, PREG_SPLIT_NO_EMPTY) ?: [$turn['statement']];
            foreach ($chunks as $chunk) {
                $send('delta', ['text' => $chunk]);
                usleep(15000);
            }
            $send('done', ['success' => true, 'turn' => $turn]);
            exit;
        }

        Bootstrap::jsonResponse(['success' => true, 'turn' => $turn]);
    }

    /** El Director (o el Scrum Master si el Director no tiene motor) sintetiza la resolución y los acuerdos. */
    public function conclude(string $meetingId): void
    {
        $meeting = $this->loadMeeting($meetingId);
        @set_time_limit(400);
        $result = $this->synthesize($meeting);
        $this->showMeeting($meetingId, ['synthesis' => $result]);
    }

    /** Compatibilidad (MCP / clientes antiguos): ejecuta la reunión completa en una sola petición. */
    public function runAgentMeeting(array $input): void
    {
        [$type, $topic, $participants] = $this->meetingParams($input);
        $this->requireSomeEngine($participants);
        @set_time_limit(1200);
        $meeting = $this->createMeeting($type, $topic, $participants);
        foreach ($participants as $role) {
            $this->generateTurn($meeting, $role);
        }
        $this->synthesize($this->loadMeeting($meeting['id']));
        $this->showMeeting($meeting['id']);
    }

    private function meetingParams(array $input): array
    {
        $type = in_array($input['meeting_type'] ?? '', ['brainstorming', 'decision', 'compliance', 'daily'], true) ? $input['meeting_type'] : 'brainstorming';
        $topic = trim((string)($input['topic'] ?? ''));
        if ($topic === '') {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Escribe el tema u objetivo de la reunión.'], 422);
        }
        $ids = is_array($input['participants'] ?? null) ? $input['participants'] : [];
        $participants = [];
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $r = $this->board->role($id);
            if ($r) {
                $participants[] = $r;
            }
        }
        if (count($participants) < 2) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Se requieren al menos 2 agentes participantes para convocar una reunión.'], 422);
        }
        if (count($participants) > 8) {
            $participants = array_slice($participants, 0, 8);
        }
        return [$type, mb_substr($topic, 0, 500), $participants];
    }

    private function createMeeting(string $type, string $topic, array $participants): array
    {
        $pdo = Database::getPdo();
        $titles = ['brainstorming' => '💡 Brainstorming: ', 'decision' => '🏛️ Consejo de decisión: ', 'compliance' => '⚖️ Compliance: ', 'daily' => '☀️ Daily: '];
        $title = ($titles[$type] ?? '') . $topic;
        $ids = array_column($participants, 'id');
        $projectId = $this->board->activeProjectId();
        // ID calculado dentro de la transacción BEGIN IMMEDIATE (H4)
        $id = Database::transaction(function (PDO $pdo) use ($type, $topic, $title, $ids, $projectId) {
            $id = BoardService::nextId($pdo, 'meetings', 'MEET-');
            $pdo->prepare(
                "INSERT INTO meetings (id, project_id, title, type, topic, participants_json, summary, resolution, action_items_json, converted)
                 VALUES (?, ?, ?, ?, ?, ?, '', '', '[]', 0)"
            )->execute([$id, $projectId, $title, $type, $topic, json_encode($ids, JSON_UNESCAPED_UNICODE)]);
            return $id;
        });
        return ['id' => $id, 'title' => $title, 'type' => $type, 'topic' => $topic, 'participants' => $ids, 'turns' => [],
            'summary' => '', 'resolution' => '', 'action_items' => [], 'converted' => false, 'created_at' => date('Y-m-d H:i')];
    }

    private function loadMeeting(string $id): array
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$id]);
        $m = $stmt->fetch();
        $stmt->closeCursor();
        if (!$m) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Reunión no encontrada'], 404);
        }
        $t = $pdo->prepare("SELECT * FROM meeting_turns WHERE meeting_id = ? ORDER BY turn_index ASC");
        $t->execute([$id]);
        $m['turns'] = $t->fetchAll();
        $m['participants'] = json_decode($m['participants_json'] ?? '[]', true) ?: [];
        return $m;
    }

    private function generateTurn(array $meeting, array $role): array
    {
        $engine = $this->engines->resolveForRole($role);
        $model = $engine['ready'] ? $engine['label'] : 'sin motor';

        if (!$engine['ready']) {
            $statement = "⚠️ {$role['name']} no tiene motor de IA disponible: {$engine['reason']}";
        } else {
            $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú']);
            $pid = (string)($meeting['project_id'] ?? $this->repo->getActiveProject()['id'] ?? 'default');
            $memSnippet = $this->repo->getCompactMemoriesForPrompt($pid, (string)($role['id'] ?? ''), 1200);
            $types = ['brainstorming' => 'lluvia de ideas', 'decision' => 'consejo de decisión técnica', 'compliance' => 'revisión de cumplimiento legal', 'daily' => 'daily standup'];
            $system = EngineService::agentSystemPrompt($role, is_array($juris) ? $juris : [],
                'Participas en una reunión de tipo ' . ($types[$meeting['type']] ?? $meeting['type']) . '. ' .
                'Aporta tu postura desde tu especialidad en 2 o 3 párrafos cortos: argumentos, riesgos y una propuesta concreta. ' .
                'Responde a lo que dijeron los demás cuando sea relevante. Sin saludos.',
                $memSnippet);

            $discussion = "TEMA: {$meeting['topic']}\n\n";
            foreach ($meeting['turns'] as $t) {
                $discussion .= "— {$t['name']}: " . mb_substr((string)$t['statement'], 0, 1200) . "\n\n";
            }
            $discussion .= $meeting['turns'] ? "Es tu turno, {$role['name']}." : "Abres la reunión, {$role['name']}.";

            try {
                $res = $this->engines->ask($engine, $system, [['role' => 'user', 'content' => $discussion]],
                    ['agent_role' => $role['id'], 'context_source' => 'meeting_' . $meeting['type']], 900);
                $statement = trim((string)$res['text']) ?: '(sin respuesta)';
                $model = (string)($res['model'] ?? $model);
            } catch (\Throwable $e) {
                Bootstrap::log("Reunión {$meeting['id']} turno {$role['id']}: " . $e->getMessage());
                $statement = "⚠️ {$role['name']} no pudo intervenir ({$engine['label']}): " . $e->getMessage();
            }
        }

        $turn = [
            'role_id'   => $role['id'],
            'name'      => $role['name'],
            'alias'     => $role['alias'] ?? '',
            'avatar'    => $role['avatar'] ?? '🤖',
            'ai_model'  => $model,
            'statement' => $statement,
            'timestamp' => date('H:i'),
        ];

        Database::transaction(function (PDO $pdo) use ($meeting, $turn) {
            $idx = $pdo->prepare("SELECT COALESCE(MAX(turn_index), -1) + 1 FROM meeting_turns WHERE meeting_id = ?");
            $idx->execute([$meeting['id']]);
            $pdo->prepare(
                "INSERT INTO meeting_turns (meeting_id, turn_index, role_id, name, alias, avatar, ai_model, statement, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$meeting['id'], (int)$idx->fetchColumn(), $turn['role_id'], $turn['name'], $turn['alias'], $turn['avatar'], $turn['ai_model'], $turn['statement'], $turn['timestamp']]);
        });

        return $turn;
    }

    private function synthesize(array $meeting): array
    {
        $moderator = null;
        $engine = null;
        foreach (['director', 'project_manager'] as $rid) {
            $r = $this->board->role($rid);
            if (!$r) {
                continue;
            }
            $e = $this->engines->resolveForRole($r);
            if ($e['ready']) {
                $moderator = $r;
                $engine = $e;
                break;
            }
        }

        $valid = array_filter($meeting['turns'], fn($t) => !str_starts_with((string)$t['statement'], '⚠️'));
        $summary = '';
        $resolution = '';
        $items = [];

        if (!$moderator || !$valid) {
            $summary = !$valid
                ? 'Ningún agente pudo intervenir con IA, así que no hay acuerdos que sintetizar.'
                : 'No hay un motor de IA disponible para que el Director sintetice la reunión.';
            $resolution = 'Sin resolución: configura una clave de API en Motor IA o activa la IA local y vuelve a convocarla.';
        } else {
            $roles = array_values(array_filter($this->board->roleIds(), fn($r) => $r !== 'director'));
            $discussion = "TEMA: {$meeting['topic']}\n\n";
            foreach ($valid as $t) {
                $discussion .= "— {$t['name']} ({$t['role_id']}): " . mb_substr((string)$t['statement'], 0, 1500) . "\n\n";
            }
            $system = "Eres {$moderator['name']}, moderador de la reunión. Sintetiza en español lo que el equipo dijo, sin inventar nada que no se haya dicho. " .
                "Responde SOLO con un JSON válido, sin texto adicional, con esta forma:\n" .
                '{"resumen": "3-4 frases", "resolucion": "la decisión adoptada en una frase", "acuerdos": [{"title": "...", "assigned_to": "id_de_rol", "story_points": 3, "priority": "alta", "user_story": "Como... quiero... para...", "dod": "criterios verificables"}]}' .
                "\nRoles válidos para assigned_to: " . implode(', ', $roles) . '. Máximo 5 acuerdos; story_points en Fibonacci (1,2,3,5,8); priority: critica, alta, media o baja.';
            try {
                $res = $this->engines->ask($engine, $system, [['role' => 'user', 'content' => $discussion]],
                    ['agent_role' => $moderator['id'], 'context_source' => 'meeting_synthesis'], 1600);
                $data = $this->parseJson((string)$res['text']);
                if ($data) {
                    $summary = trim((string)($data['resumen'] ?? ''));
                    $resolution = trim((string)($data['resolucion'] ?? ''));
                    foreach (array_slice(is_array($data['acuerdos'] ?? null) ? $data['acuerdos'] : [], 0, 5) as $a) {
                        $title = trim((string)($a['title'] ?? ''));
                        if ($title === '') {
                            continue;
                        }
                        $items[] = [
                            'title'        => $title,
                            'assigned_to'  => $this->board->matchRoleId((string)($a['assigned_to'] ?? '')) ?? 'project_manager',
                            'story_points' => in_array((int)($a['story_points'] ?? 3), BoardService::FIBONACCI, true) ? (int)$a['story_points'] : 3,
                            'priority'     => in_array($a['priority'] ?? '', BoardService::PRIORITIES, true) ? $a['priority'] : 'media',
                            'user_story'   => trim((string)($a['user_story'] ?? '')),
                            'dod'          => trim((string)($a['dod'] ?? '')),
                        ];
                    }
                } else {
                    $summary = trim((string)$res['text']);
                }
            } catch (\Throwable $e) {
                Bootstrap::log("Síntesis {$meeting['id']}: " . $e->getMessage());
                $summary = '⚠️ No se pudo sintetizar la reunión: ' . $e->getMessage();
            }
        }

        Database::getPdo()->prepare("UPDATE meetings SET summary = ?, resolution = ?, action_items_json = ?, closed_at = COALESCE(closed_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$summary, $resolution, json_encode($items, JSON_UNESCAPED_UNICODE), $meeting['id']]);

        return ['moderator' => $moderator['id'] ?? null, 'items' => count($items)];
    }

    private function parseJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }

    /** Devuelve la reunión en el mismo formato que usa el frontend. */
    private function showMeeting(string $id, array $extra = []): void
    {
        $m = $this->loadMeeting($id);
        Bootstrap::jsonResponse(['success' => true, 'meeting' => [
            'id'           => $m['id'],
            'title'        => $m['title'],
            'type'         => $m['type'],
            'topic'        => $m['topic'],
            'created_at'   => $m['created_at'],
            'participants' => $m['participants'],
            'turns'        => array_map(fn($t) => [
                'role_id' => $t['role_id'], 'name' => $t['name'], 'alias' => $t['alias'], 'avatar' => $t['avatar'],
                'ai_model' => $t['ai_model'], 'statement' => $t['statement'], 'timestamp' => $t['timestamp'],
            ], $m['turns']),
            'summary'      => $m['summary'],
            'resolution'   => $m['resolution'],
            'action_items' => json_decode($m['action_items_json'] ?? '[]', true) ?: [],
            'converted'    => (bool)$m['converted'],
        ]] + $extra);
    }

    public function convertToTasks(string $meetingId): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch();
        $stmt->closeCursor();

        if (!$meeting) {
            Bootstrap::jsonResponse(['error' => 'Reunión no encontrada'], 404);
        }

        if (!empty($meeting['converted'])) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Los acuerdos de esta reunión ya se convirtieron en historias.'], 409);
        }
        $actionItems = json_decode($meeting['action_items_json'] ?? '[]', true) ?: [];
        if (empty($actionItems)) {
            Bootstrap::jsonResponse(['error' => 'Esta reunión no contiene acuerdos o tareas pendientes por convertir'], 422);
        }

        $createdTasks = [];
        $projectId = !empty($meeting['project_id']) ? $meeting['project_id'] : $this->board->activeProjectId();

        Database::transaction(function (PDO $pdo) use ($meeting, $projectId, $actionItems, &$createdTasks) {
            foreach ($actionItems as $idx => $item) {
                $task = $this->board->createTask([
                    'project_id'   => $projectId,
                    'title'        => $item['title'] ?? "Acción #{$idx} derivada de {$meeting['id']}",
                    'assigned_to'  => $item['assigned_to'] ?? 'project_manager',
                    'story_points' => $item['story_points'] ?? 3,
                    'priority'     => $item['priority'] ?? 'media',
                    'user_story'   => $item['user_story'] ?? '',
                    'dod'          => $item['dod'] ?? '',
                    'description'  => "Derivado del acta {$meeting['id']}: {$meeting['title']}",
                ], 'web', 'meeting');
                $createdTasks[] = ['id' => $task['id'], 'title' => $task['title'], 'assigned_to' => $task['assigned_to']];
            }
            $pdo->prepare("UPDATE meetings SET converted = 1 WHERE id = ?")->execute([$meeting['id']]);
        });

        Bootstrap::jsonResponse([
            'success'       => true,
            'message'       => count($createdTasks) . " historias creadas en el Backlog a partir de los acuerdos",
            'created_tasks' => $createdTasks,
            'tasks'         => $createdTasks
        ]);
    }

    public function saveToWorkspace(string $meetingId, ?string $targetDir = null): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch();
        $stmt->closeCursor();

        if (!$meeting) {
            Bootstrap::jsonResponse(['error' => 'Reunión no encontrada'], 404);
        }

        $tStmt = $pdo->prepare("SELECT * FROM meeting_turns WHERE meeting_id = ? ORDER BY turn_index ASC");
        $tStmt->execute([$meetingId]);
        $turns = $tStmt->fetchAll();

        // Validar ruta del workspace
        $targetDir = $targetDir ?? dirname(__DIR__, 2);
        $cleanDir = Bootstrap::validateWorkspacePath($targetDir);

        $dateStr = date('Ymd_His');
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $meeting['id']);
        $filename = "acta_{$safeId}_{$dateStr}.md";
        $fullPath = $cleanDir . DIRECTORY_SEPARATOR . $filename;

        // Armar contenido en Markdown estructurado
        $md = "# Acta de Reunión AgileForge · {$meeting['title']}\n\n";
        $md .= "- **ID:** {$meeting['id']}\n";
        $md .= "- **Tipo:** " . strtoupper($meeting['type']) . "\n";
        $md .= "- **Tópico:** {$meeting['topic']}\n";
        $md .= "- **Fecha de Registro:** {$meeting['created_at']}\n\n";

        $md .= "## 🎙️ Minutas de Intervenciones\n\n";
        foreach ($turns as $t) {
            $md .= "### {$t['avatar']} {$t['name']} ({$t['alias']}) — {$t['timestamp']}\n";
            $md .= "> {$t['statement']}\n\n";
        }

        $md .= "## 📋 Resumen Ejecutivo\n\n";
        $md .= ($meeting['summary'] ?: 'Sin resumen registrado.') . "\n\n";

        $md .= "## ⚖️ Resolución / Decisión Final\n\n";
        $md .= ($meeting['resolution'] ?: 'Sin resolución final.') . "\n\n";

        $actionItems = json_decode($meeting['action_items_json'] ?? '[]', true) ?: [];
        if (!empty($actionItems)) {
            $md .= "## 🚀 Compromisos y Tareas (Action Items)\n\n";
            foreach ($actionItems as $i => $item) {
                $md .= ($i + 1) . ". **{$item['title']}** (Asignado a: `{$item['assigned_to']}`, {$item['story_points']} pts)\n";
                if (!empty($item['dod'])) {
                    $md .= "   - *DoD:* {$item['dod']}\n";
                }
            }
        }

        file_put_contents($fullPath, $md);

        Bootstrap::jsonResponse([
            'success'   => true,
            'file_name' => $filename,
            'file_path' => $fullPath
        ]);
    }
}
