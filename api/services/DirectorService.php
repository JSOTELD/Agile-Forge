<?php
/**
 * AgileForge - DirectorService
 * El Director General (jefe máximo) recibe instrucciones del usuario, decide y las ejecuta sobre el tablero
 * con herramientas: crear/editar historias, consultar a los agentes, registrar decisiones y ajustar el sprint.
 *
 * - Con Claude (Anthropic) usa "tool use" nativo de la Messages API.
 * - Con cualquier otro motor (IA local, OpenAI, Gemini, DeepSeek) usa un protocolo JSON equivalente.
 */

declare(strict_types=1);

namespace AgileForge\Services;

require_once __DIR__ . '/EngineService.php';
require_once __DIR__ . '/BoardService.php';

use AgileForge\Database;
use AgileForge\Repository;
use RuntimeException;

class DirectorService
{
    public const ROLE_ID = 'director';
    private const MAX_ROUNDS = 8;
    private const TIME_BUDGET = 240; // segundos por instrucción

    private Repository $repo;
    private EngineService $engines;
    private BoardService $board;
    private array $actions = [];
    private int $consultations = 0;
    private float $startedAt = 0.0;

    public function __construct(?Repository $repo = null)
    {
        $this->repo = $repo ?? new Repository();
        $this->engines = new EngineService($this->repo);
        $this->board = new BoardService($this->repo);
    }

    /**
     * Ejecuta una instrucción del usuario.
     * Devuelve: reply, actions[], engine{provider, model, kind}, tokens, cost_usd
     */
    public function run(string $instruction): array
    {
        @set_time_limit(self::TIME_BUDGET + 120);
        $this->startedAt = microtime(true);
        $this->actions = [];
        $this->consultations = 0;

        $role = $this->board->role(self::ROLE_ID);
        if (!$role) {
            throw new RuntimeException('No existe el rol Director en este proyecto.');
        }
        $engine = $this->engines->resolveForRole($role);
        if (!$engine['ready']) {
            throw new RuntimeException($engine['reason']);
        }

        // Con IA local el contexto se resume: el procesamiento del prompt es lo más lento en la PC
        $system = $this->systemPrompt($role, $engine['kind'] === 'local');
        $history = $this->conversationHistory();
        $last = $history ? $history[count($history) - 1] : null;
        if ($last && $last['role'] === 'user' && $last['content'] === mb_substr($instruction, 0, 1500)) {
            array_pop($history); // ya se guardó en el chat: se reemplaza por el texto completo
        }
        $history[] = ['role' => 'user', 'content' => $instruction];

        if ($engine['kind'] === 'api' && $engine['provider'] === 'anthropic') {
            $res = $this->runWithClaudeTools($engine, $system, $history);
        } else {
            $res = $this->runWithJsonProtocol($engine, $system, $history);
        }

        return $res + [
            'actions' => $this->actions,
            'engine'  => ['provider' => $engine['provider'], 'model' => $engine['model'], 'kind' => $engine['kind'], 'source' => $engine['source']],
        ];
    }

    // ------------------------------------------------------------------
    // Claude con herramientas nativas
    // ------------------------------------------------------------------

    private function runWithClaudeTools(array $engine, string $system, array $history): array
    {
        $llm = $this->engines->llm();
        $messages = $this->alternate($history);
        $tools = $this->toolDefinitions();
        $inTok = 0;
        $outTok = 0;
        $reply = '';

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $res = $llm->anthropicRaw([
                'model'      => $engine['model'],
                'max_tokens' => 4096,
                'system'     => $system,
                'tools'      => $tools,
                'messages'   => $messages,
            ], 150);

            $inTok += (int)($res['usage']['input_tokens'] ?? 0);
            $outTok += (int)($res['usage']['output_tokens'] ?? 0);
            $content = $res['content'] ?? [];
            $messages[] = ['role' => 'assistant', 'content' => $content];

            if (($res['stop_reason'] ?? '') !== 'tool_use') {
                $reply = $llm->anthropicText($res);
                break;
            }

            $results = [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') !== 'tool_use') {
                    continue;
                }
                $out = $this->executeTool((string)$block['name'], is_array($block['input'] ?? null) ? $block['input'] : []);
                $results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'is_error'    => !$out['ok'],
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $results];

            if ($this->outOfTime()) {
                $messages[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => '(Se agotó el tiempo de esta instrucción.)']]];
                $reply = $this->summaryFallback('Se agotó el tiempo antes de terminar.');
                break;
            }
        }

        if ($reply === '') {
            $reply = $this->summaryFallback('Alcancé el máximo de pasos para una sola instrucción.');
        }

        $cost = $llm->calculateCost('anthropic', $engine['model'], $inTok, $outTok);
        $llm->recordUsage(self::ROLE_ID, null, 'default', 'anthropic', $engine['model'], $inTok, $outTok, $inTok + $outTok, $cost, 'director');

        return ['reply' => $reply, 'tokens' => $inTok + $outTok, 'cost_usd' => $cost];
    }

    // ------------------------------------------------------------------
    // Otros motores: protocolo JSON
    // ------------------------------------------------------------------

    private function runWithJsonProtocol(array $engine, string $system, array $history): array
    {
        $system .= "\n\n" . $this->jsonProtocolDoc();
        $messages = $history;
        $tokens = 0;
        $reply = '';

        for ($round = 0; $round < 4; $round++) {
            $res = $this->engines->ask($engine, $system, $messages, ['agent_role' => self::ROLE_ID, 'context_source' => 'director'], 1800);
            $tokens += (int)($res['total_tokens'] ?? 0);
            $text = (string)($res['text'] ?? '');
            $plan = $this->parseJson($text);

            if ($plan === null) {
                $reply = $text; // respondió en texto libre: se muestra tal cual
                break;
            }

            $acciones = is_array($plan['acciones'] ?? null) ? array_slice($plan['acciones'], 0, 6) : [];
            $reply = trim((string)($plan['respuesta'] ?? ''));
            if (!$acciones) {
                break;
            }

            $resultLines = [];
            $needsAnotherRound = false;
            foreach ($acciones as $a) {
                $tool = (string)($a['herramienta'] ?? $a['tool'] ?? '');
                $params = is_array($a['parametros'] ?? null) ? $a['parametros'] : (is_array($a['input'] ?? null) ? $a['input'] : []);
                $out = $this->executeTool($tool, $params);
                $resultLines[] = "- {$tool}: " . json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if (in_array($tool, ['ver_tablero', 'ver_historia', 'consultar_agente'], true) || !$out['ok']) {
                    $needsAnotherRound = true;
                }
            }

            if (!$needsAnotherRound || $this->outOfTime()) {
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $text];
            $messages[] = ['role' => 'user', 'content' => "RESULTADOS DE TUS ACCIONES:\n" . implode("\n", $resultLines) .
                "\n\nContinúa. Si ya terminaste, responde el JSON con \"acciones\": [] y tu respuesta final para el usuario."];
        }

        if ($reply === '') {
            $reply = $this->summaryFallback('Listo.');
        }
        return ['reply' => $reply, 'tokens' => $tokens, 'cost_usd' => 0.0];
    }

    private function jsonProtocolDoc(): string
    {
        $lines = [];
        foreach ($this->toolDefinitions() as $t) {
            $props = [];
            foreach ($t['input_schema']['properties'] ?? [] as $k => $v) {
                $req = in_array($k, $t['input_schema']['required'] ?? [], true) ? '*' : '';
                $props[] = $k . $req;
            }
            $lines[] = "- {$t['name']}(" . implode(', ', $props) . "): {$t['description']}";
        }
        return "HERRAMIENTAS DISPONIBLES (* = obligatorio):\n" . implode("\n", $lines) . "\n\n" .
            "FORMATO DE RESPUESTA OBLIGATORIO: responde SOLO con un objeto JSON válido, sin texto antes ni después:\n" .
            '{"respuesta": "mensaje para el usuario, en su idioma", "acciones": [{"herramienta": "crear_historia", "parametros": {"titulo": "...", "asignado_a": "frontend_master"}}]}' . "\n" .
            'Si no necesitas ejecutar nada, usa "acciones": [].';
    }

    private function parseJson(string $text): ?array
    {
        $text = trim((string)preg_replace('/^```(?:json)?|```$/m', '', $text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) && (isset($data['respuesta']) || isset($data['acciones'])) ? $data : null;
    }

    // ------------------------------------------------------------------
    // Herramientas
    // ------------------------------------------------------------------

    public function toolDefinitions(): array
    {
        $roles = array_values(array_filter($this->board->roleIds(), fn($r) => $r !== self::ROLE_ID));
        $str = ['type' => 'string'];

        return [
            [
                'name' => 'ver_tablero',
                'description' => 'Lista las historias del proyecto activo (id, título, estado, rol, prioridad, puntos). Úsalo antes de decidir cambios.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'estado' => ['type' => 'string', 'enum' => BoardService::STATUSES],
                    'rol'    => ['type' => 'string', 'enum' => $roles],
                ]],
            ],
            [
                'name' => 'ver_historia',
                'description' => 'Detalle de una historia: historia de usuario, DoD, descripción y últimos comentarios.',
                'input_schema' => ['type' => 'object', 'properties' => ['task_id' => $str], 'required' => ['task_id']],
            ],
            [
                'name' => 'crear_historia',
                'description' => 'Crea una historia de usuario en el backlog del proyecto activo y la asigna a un agente especialista.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'titulo'           => $str,
                    'asignado_a'       => ['type' => 'string', 'enum' => $roles],
                    'prioridad'        => ['type' => 'string', 'enum' => BoardService::PRIORITIES],
                    'puntos'           => ['type' => 'integer', 'enum' => BoardService::FIBONACCI],
                    'historia_usuario' => ['type' => 'string', 'description' => 'Formato: Como <usuario> quiero <acción> para <beneficio>'],
                    'dod'              => ['type' => 'string', 'description' => 'Criterios de aceptación verificables'],
                    'descripcion'      => $str,
                ], 'required' => ['titulo', 'asignado_a']],
            ],
            [
                'name' => 'actualizar_historia',
                'description' => 'Reasigna, reprioriza, re-estima o mueve una historia (backlog, en_progreso, revision). No puede marcarla como completada: eso lo aprueba el usuario.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'task_id'     => $str,
                    'titulo'      => $str,
                    'asignado_a'  => ['type' => 'string', 'enum' => $roles],
                    'prioridad'   => ['type' => 'string', 'enum' => BoardService::PRIORITIES],
                    'puntos'      => ['type' => 'integer', 'enum' => BoardService::FIBONACCI],
                    'estado'      => ['type' => 'string', 'enum' => ['backlog', 'en_progreso', 'revision']],
                    'dod'         => $str,
                    'descripcion' => $str,
                ], 'required' => ['task_id']],
            ],
            [
                'name' => 'comentar_historia',
                'description' => 'Deja una instrucción o nota del Director en una historia.',
                'input_schema' => ['type' => 'object', 'properties' => ['task_id' => $str, 'comentario' => $str], 'required' => ['task_id', 'comentario']],
            ],
            [
                'name' => 'consultar_agente',
                'description' => 'Pide la opinión técnica de un agente del equipo (responde con su propio motor de IA). Úsalo con moderación: máximo 3 consultas por instrucción.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'rol'      => ['type' => 'string', 'enum' => $roles],
                    'pregunta' => $str,
                ], 'required' => ['rol', 'pregunta']],
            ],
            [
                'name' => 'registrar_decision',
                'description' => 'Registra una decisión importante como ADR (Architecture/Business Decision Record).',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'titulo' => $str, 'contexto' => $str, 'decision' => $str, 'consecuencias' => $str,
                ], 'required' => ['titulo', 'decision']],
            ],
            [
                'name' => 'ajustar_sprint',
                'description' => 'Cambia el nombre, objetivo, días restantes o velocidad del sprint activo.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'nombre' => $str, 'objetivo' => $str,
                    'dias_restantes' => ['type' => 'integer'], 'velocidad' => ['type' => 'integer'],
                ]],
            ],
        ];
    }

    /** Ejecuta una herramienta y registra la acción para mostrarla al usuario. */
    public function executeTool(string $name, array $in): array
    {
        try {
            switch ($name) {
                case 'ver_tablero':
                    $tasks = $this->board->listTasks(['status' => $in['estado'] ?? '', 'role' => $in['rol'] ?? '']);
                    return $this->ok($name, 'Revisó el tablero (' . count($tasks) . ' historias)', ['historias' => $tasks], false);

                case 'ver_historia':
                    $t = $this->repo->getTask((string)($in['task_id'] ?? ''));
                    if (!$t) {
                        return $this->fail($name, 'No existe la historia ' . ($in['task_id'] ?? ''), false);
                    }
                    $data = array_intersect_key($t, array_flip(['id', 'title', 'status', 'assigned_to', 'priority', 'story_points', 'user_story', 'dod', 'description', 'locked_by']));
                    $data['comentarios'] = array_map(fn($c) => ($c['author_name'] ?? '') . ': ' . mb_substr((string)$c['content'], 0, 300), array_slice($t['comments'] ?? [], -5));
                    return $this->ok($name, "Leyó {$t['id']}", $data, false);

                case 'crear_historia':
                    $task = $this->board->createTask([
                        'title'        => $in['titulo'] ?? '',
                        'assigned_to'  => $in['asignado_a'] ?? '',
                        'priority'     => $in['prioridad'] ?? 'media',
                        'story_points' => $in['puntos'] ?? 3,
                        'user_story'   => $in['historia_usuario'] ?? '',
                        'dod'          => $in['dod'] ?? '',
                        'description'  => $in['descripcion'] ?? '',
                    ], 'director', self::ROLE_ID);
                    return $this->ok($name, "Creó {$task['id']}: {$task['title']} → {$task['assigned_to']}", ['id' => $task['id']]);

                case 'actualizar_historia':
                    $id = (string)($in['task_id'] ?? '');
                    $task = $this->board->updateTask($id, [
                        'title'        => $in['titulo'] ?? null,
                        'assigned_to'  => $in['asignado_a'] ?? null,
                        'priority'     => $in['prioridad'] ?? null,
                        'story_points' => $in['puntos'] ?? null,
                        'status'       => $in['estado'] ?? null,
                        'dod'          => $in['dod'] ?? null,
                        'description'  => $in['descripcion'] ?? null,
                    ], 'director', self::ROLE_ID, false);
                    $what = array_keys(array_filter($in, fn($v, $k) => $k !== 'task_id' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH));
                    return $this->ok($name, "Actualizó {$task['id']} (" . implode(', ', $what) . ')', ['id' => $task['id'], 'estado' => $task['status'], 'asignado_a' => $task['assigned_to']]);

                case 'comentar_historia':
                    $id = (string)($in['task_id'] ?? '');
                    if (!$this->repo->getTask($id)) {
                        return $this->fail($name, "No existe la historia {$id}");
                    }
                    $text = trim((string)($in['comentario'] ?? ''));
                    if ($text === '') {
                        return $this->fail($name, 'Comentario vacío');
                    }
                    $this->repo->addComment($id, self::ROLE_ID, 'Director General', '👑', $text);
                    $this->repo->recordEvent($id, self::ROLE_ID, 'comment_added', ['by' => 'director'], 'director');
                    return $this->ok($name, "Comentó en {$id}");

                case 'consultar_agente':
                    return $this->consultAgent((string)($in['rol'] ?? ''), (string)($in['pregunta'] ?? ''));

                case 'registrar_decision':
                    $adr = $this->board->recordDecision(
                        (string)($in['titulo'] ?? ''),
                        (string)($in['contexto'] ?? ''),
                        (string)($in['decision'] ?? ''),
                        (string)($in['consecuencias'] ?? '')
                    );
                    return $this->ok($name, "Registró {$adr}: " . ($in['titulo'] ?? ''), ['id' => $adr]);

                case 'ajustar_sprint':
                    $sp = $this->board->updateSprint([
                        'name'            => $in['nombre'] ?? null,
                        'goal'            => $in['objetivo'] ?? null,
                        'days_remaining'  => $in['dias_restantes'] ?? null,
                        'velocity_points' => $in['velocidad'] ?? null,
                    ]);
                    return $this->ok($name, 'Ajustó el sprint: ' . $sp['name'], ['sprint' => $sp['name'], 'objetivo' => $sp['goal']]);

                default:
                    return $this->fail($name, "Herramienta desconocida: {$name}", false);
            }
        } catch (\Throwable $e) {
            return $this->fail($name, $e->getMessage());
        }
    }

    private function consultAgent(string $roleId, string $question): array
    {
        $name = 'consultar_agente';
        if ($roleId === self::ROLE_ID) {
            return $this->fail($name, 'El Director no puede consultarse a sí mismo', false);
        }
        if ($this->consultations >= 3) {
            return $this->fail($name, 'Límite de 3 consultas por instrucción alcanzado; decide con lo que ya tienes.', false);
        }
        $role = $this->board->role($this->board->matchRoleId($roleId) ?? '');
        if (!$role || trim($question) === '') {
            return $this->fail($name, "No existe el rol '{$roleId}' o la pregunta está vacía", false);
        }
        $this->consultations++;

        $engines = $this->engines;
        $engine = $engines->resolveForRole($role);
        if (!$engine['ready']) {
            return $this->fail($name, "{$role['name']} no tiene motor de IA disponible: {$engine['reason']}");
        }
        $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú']);
        $system = EngineService::agentSystemPrompt($role, is_array($juris) ? $juris : [],
            'El Director General te hace una consulta: responde con tu recomendación concreta en máximo 6 líneas.');
        $res = $engines->ask($engine, $system, [['role' => 'user', 'content' => $question]],
            ['agent_role' => $role['id'], 'context_source' => 'director_consult'], 700);
        $answer = trim((string)$res['text']);

        $this->storeChat($role['id'], $role['name'], $role['avatar'] ?? '🤖', $answer, (string)($res['model'] ?? $engine['model']),
            (string)$engine['provider'], ['consulted_by' => self::ROLE_ID, 'question' => $question]);

        return $this->ok($name, "Consultó a {$role['name']}", ['rol' => $role['id'], 'respuesta' => mb_substr($answer, 0, 2500)]);
    }

    private function ok(string $tool, string $summary, array $data = [], bool $visible = true): array
    {
        $this->actions[] = ['tool' => $tool, 'ok' => true, 'summary' => $summary, 'visible' => $visible];
        return ['ok' => true, 'resultado' => $summary] + $data;
    }

    private function fail(string $tool, string $error, bool $visible = true): array
    {
        $this->actions[] = ['tool' => $tool, 'ok' => false, 'summary' => $error, 'visible' => $visible];
        return ['ok' => false, 'error' => $error];
    }

    private function outOfTime(): bool
    {
        return (microtime(true) - $this->startedAt) > self::TIME_BUDGET;
    }

    private function summaryFallback(string $prefix): string
    {
        $done = array_filter($this->actions, fn($a) => $a['ok'] && $a['visible']);
        if (!$done) {
            return $prefix;
        }
        return $prefix . "\n\nAcciones realizadas:\n- " . implode("\n- ", array_column($done, 'summary'));
    }

    // ------------------------------------------------------------------
    // Contexto
    // ------------------------------------------------------------------

    public function systemPrompt(array $role, bool $compact = false): string
    {
        $project = $this->repo->getActiveProject();
        $sprint = $this->board->activeSprint();
        $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú']);

        $counts = ['backlog' => 0, 'en_progreso' => 0, 'revision' => 0, 'completada' => 0];
        foreach ($this->board->listTasks([], 500) as $t) {
            $counts[$t['status']] = ($counts[$t['status']] ?? 0) + 1;
        }
        $open = array_filter($this->board->listTasks([], 40), fn($t) => $t['status'] !== 'completada');
        $openLines = array_map(fn($t) => "  {$t['id']} [{$t['status']}|{$t['priority']}|{$t['story_points']}pts] {$t['title']} → {$t['assigned_to']}", array_slice($open, 0, $compact ? 12 : 25));

        $team = [];
        foreach (Database::getPdo()->query("SELECT * FROM roles ORDER BY id")->fetchAll() as $r) {
            if ($r['id'] === self::ROLE_ID) {
                continue;
            }
            $eng = $this->engines->resolveForRole($r);
            $team[] = "  {$r['id']}: {$r['name']}" . ($compact ? '' : ' — ' . mb_substr((string)$r['specialty'], 0, 110)) . ' [motor: ' . ($eng['ready'] ? $eng['label'] : 'sin motor') . ']';
        }

        $decisions = array_map(fn($d) => "  {$d['id']}: {$d['title']}", $this->board->recentDecisions(5));

        $prompt = "Eres {$role['name']} ({$role['alias']}), el jefe máximo del equipo de agentes de IA de AgileForge. " .
            "El usuario es el Líder de Producto y dueño del proyecto: te da instrucciones y tú decides, delegas y ejecutas sobre el tablero con tus herramientas.\n\n" .
            "PRINCIPIOS DE DIRECCIÓN\n" .
            "1. Entiende el objetivo de negocio detrás de cada instrucción. Si es ambigua y actuar sería costoso de deshacer, pregunta antes de tocar el tablero.\n" .
            "2. Decide con criterio explícito: valor para el usuario final, riesgo, esfuerzo, dependencias y capacidad del sprint.\n" .
            "3. Delega cada historia al especialista correcto, con historia de usuario (Como… quiero… para…), DoD verificable y estimación Fibonacci. Divide lo que pase de 8 puntos.\n" .
            "4. Consulta a los agentes solo cuando su criterio técnico cambie tu decisión (máximo 3 consultas por instrucción).\n" .
            "5. Nunca marques historias como completadas: eso lo aprueba el usuario. No inventes datos del proyecto.\n" .
            "6. Registra como ADR las decisiones que comprometan arquitectura, costos, legal o alcance.\n" .
            "7. Responde en el idioma del usuario (español por defecto; si te escribe en inglés, responde en inglés), breve y ejecutivo, con este formato: **Decisión**, **Qué hice** (acciones concretas con IDs), **Siguientes pasos** y, si aplica, **Riesgos o preguntas** (en inglés: **Decision**, **What I did**, **Next steps**, **Risks or questions**).\n\n" .
            "CONTEXTO ACTUAL\n" .
            'Proyecto: ' . ($project['name'] ?? 'default') . ' — carpeta: ' . ($project['path'] ?? '') . "\n" .
            'Jurisdicción: ' . ($juris['country_name'] ?? 'Perú') . "\n" .
            ($sprint ? "Sprint activo: {$sprint['name']} — objetivo: {$sprint['goal']} — quedan {$sprint['days_remaining']} días — velocidad {$sprint['velocity_points']} pts\n" : "Sin sprint activo.\n") .
            "Historias: backlog {$counts['backlog']}, en progreso {$counts['en_progreso']}, en revisión {$counts['revision']}, completadas {$counts['completada']}\n" .
            ($openLines ? "Historias abiertas:\n" . implode("\n", $openLines) . "\n" : '') .
            "Equipo (id: nombre — especialidad [motor]):\n" . implode("\n", $team) . "\n" .
            ($decisions ? "Decisiones recientes:\n" . implode("\n", $decisions) . "\n" : '');

        $skills = $compact ? '' : $this->skillGuides($role);
        if ($skills !== '') {
            $prompt .= "\nGUÍAS DE TUS SKILLS\n" . $skills;
        }
        return $prompt;
    }

    /** Contenido de las skills del Director (plugin/agileforge/skills/director-*). */
    private function skillGuides(array $role): string
    {
        $ids = json_decode((string)($role['assigned_skills_json'] ?? '[]'), true) ?: [];
        $base = dirname(__DIR__, 2) . '/plugin/agileforge/skills/';
        $out = '';
        foreach ($ids as $id) {
            if (!preg_match('/^director-[a-z0-9-]+$/', (string)$id)) {
                continue;
            }
            $file = $base . $id . '/SKILL.md';
            if (!is_file($file)) {
                continue;
            }
            $body = (string)preg_replace('/^---.*?---\s*/s', '', (string)file_get_contents($file));
            $out .= "\n[{$id}]\n" . mb_substr(trim($body), 0, 2200) . "\n";
        }
        return $out;
    }

    /** Últimos intercambios del usuario con el Director, para dar continuidad. */
    private function conversationHistory(): array
    {
        $rows = Database::getPdo()->query(
            "SELECT sender_role, text, meta_json FROM (
                SELECT rowid AS _rid, * FROM chat_messages
                WHERE sender_role = 'director' OR (sender_role = 'user' AND meta_json LIKE '%\"target\":\"director\"%')
                ORDER BY created_at DESC, rowid DESC LIMIT 6
             ) ORDER BY _rid ASC"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $text = trim((string)$r['text']);
            if ($text === '' || str_starts_with($text, '⚠️')) {
                continue;
            }
            $out[] = ['role' => $r['sender_role'] === 'user' ? 'user' : 'assistant', 'content' => mb_substr($text, 0, 1500)];
        }
        return $out;
    }

    private function alternate(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($out && $out[count($out) - 1]['role'] === $m['role']) {
                $out[count($out) - 1]['content'] .= "\n\n" . $m['content'];
            } else {
                $out[] = $m;
            }
        }
        while ($out && $out[0]['role'] !== 'user') {
            array_shift($out);
        }
        return $out;
    }

    public function storeChat(string $roleId, string $name, string $avatar, string $text, ?string $model, ?string $provider, array $meta = []): void
    {
        $id = 'MSG-' . (int)(microtime(true) * 1000) . '-A-' . bin2hex(random_bytes(3));
        Database::getPdo()->prepare(
            "INSERT INTO chat_messages (id, sender_role, sender_name, avatar, timestamp, text, ai_model, ai_provider, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$id, $roleId, $name, $avatar, date('H:i'), $text, $model, $provider, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    }
}
