<?php
/**
 * AgileForge - MeetingController
 * Controlador de Salas de Reuniones: Brainstorming, ADR, Compliance, Plenarias y Actas.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/LlmService.php';
require_once dirname(__DIR__) . '/services/LocalAiService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Services\LlmService;
use AgileForge\Services\LocalAiService;
use PDO;

class MeetingController
{
    private Repository $repo;
    private LlmService $llm;
    private LocalAiService $local;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->llm = new LlmService();
        $this->local = new LocalAiService($this->repo);
    }

    public function list(): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM meetings ORDER BY created_at DESC");
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

        $id = !empty($input['id']) ? trim((string)$input['id']) : ('MEET-' . str_pad((string)rand(10, 999), 3, '0', STR_PAD_LEFT));
        $title = trim((string)($input['title'] ?? "Reunión: {$topic}"));
        $participants = is_array($input['participants'] ?? null) ? $input['participants'] : ['project_manager', 'lead_architect'];

        Database::transaction(function (PDO $pdo) use ($id, $title, $type, $topic, $participants) {
            $stmt = $pdo->prepare(
                "INSERT INTO meetings (id, project_id, title, type, topic, participants_json, summary, resolution, action_items_json, converted)
                 VALUES (?, 'default', ?, ?, ?, ?, '', '', '[]', 0)"
            );
            $stmt->execute([$id, $title, $type, $topic, json_encode($participants, JSON_UNESCAPED_UNICODE)]);
        });

        $this->show($id);
    }

    public function addTurn(string $meetingId, array $input): void
    {
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

        $pdo = Database::getPdo();
        $idxStmt = $pdo->prepare("SELECT COUNT(*) FROM meeting_turns WHERE meeting_id = ?");
        $idxStmt->execute([$meetingId]);
        $nextIndex = (int)$idxStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "INSERT INTO meeting_turns (meeting_id, turn_index, role_id, name, alias, avatar, ai_model, statement, timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$meetingId, $nextIndex, $roleId, $name, $alias, $avatar, $model, $statement, $timestamp]);

        Bootstrap::jsonResponse(['success' => true, 'turn_index' => $nextIndex]);
    }

    public function close(string $meetingId, array $input): void
    {
        $summary = trim((string)($input['summary'] ?? ''));
        $resolution = trim((string)($input['resolution'] ?? ''));
        $actionItems = is_array($input['action_items'] ?? null) ? $input['action_items'] : [];

        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("UPDATE meetings SET summary = ?, resolution = ?, action_items_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$summary, $resolution, json_encode($actionItems, JSON_UNESCAPED_UNICODE), $meetingId]);

        Bootstrap::jsonResponse(['success' => true, 'meeting_id' => $meetingId, 'closed' => true]);
    }

    public function runAgentMeeting(array $input): void
    {
        $meetingType = in_array($input['meeting_type'] ?? '', ['brainstorming', 'decision', 'compliance', 'daily'], true) ? $input['meeting_type'] : 'brainstorming';
        $topic = trim((string)($input['topic'] ?? ''));
        $participants = is_array($input['participants'] ?? null) ? $input['participants'] : [];

        if ($topic === '') {
            $topic = ($meetingType === 'brainstorming')
                ? 'Innovación y diferenciación en la experiencia de usuario y captura de valor'
                : 'Definición de arquitectura de persistencia y mitigación de riesgos técnicos';
        }

        if (empty($participants)) {
            $participants = ($meetingType === 'brainstorming')
                ? ['creative_director', 'frontend_master', 'project_manager']
                : ['lead_architect', 'backend_architect', 'qa_security_guard', 'legal_counsel'];
        }

        $pdo = Database::getPdo();
        $rStmt = $pdo->query("SELECT * FROM roles");
        $rolesMap = [];
        while ($r = $rStmt->fetch()) {
            $rolesMap[$r['id']] = $r;
        }

        $activeParticipants = [];
        foreach ($participants as $pId) {
            if (isset($rolesMap[$pId])) {
                $activeParticipants[] = $rolesMap[$pId];
            }
        }

        if (count($activeParticipants) < 2) {
            Bootstrap::jsonResponse(['error' => 'Se requieren al menos 2 agentes participantes para convocar una reunión.'], 422);
        }

        $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú', 'flag' => '🇵🇪']);
        $aiEngine = $this->repo->getSetting('ai_engine', []);
        $roleRouting = !empty($aiEngine['role_routing_enabled']);
        $isApiMode = ($aiEngine['execution_mode'] ?? 'cli') === 'api';

        // IA local activada: todos los turnos los responde el modelo de Ollama en esta PC
        $localState = $this->local->getState();
        $useLocal = !empty($localState['enabled']) && !empty($localState['model']);
        if ($useLocal) {
            $isApiMode = true;
            @set_time_limit(900);
        }

        $turns = [];
        $actionItems = [];
        $summary = '';
        $resolution = '';

        // Verificar si hay modelos configurados en modo API para llamadas reales server-side
        $canRunRealApi = $useLocal;
        if ($isApiMode && !$useLocal) {
            foreach ($activeParticipants as $agent) {
                $p = ($roleRouting && !empty($agent['ai_provider'])) ? $agent['ai_provider'] : ($aiEngine['active_provider'] ?? 'anthropic');
                if ($this->llm->isProviderConfigured($p)) {
                    $canRunRealApi = true;
                    break;
                }
            }
        }

        if ($isApiMode && $canRunRealApi) {
            // MODO API: DELIBERACIÓN REAL TURNO POR TURNO CON CONTEXTO ACUMULADO
            $accumulatedDiscussion = "Mesa de deliberación oficial: " . strtoupper($meetingType) . "\nTema: {$topic}\nJurisdicción: {$juris['country_name']} {$juris['flag']}\n\n";

            foreach ($activeParticipants as $agent) {
                $aId = $agent['id'];
                $prov = ($roleRouting && !empty($agent['ai_provider'])) ? $agent['ai_provider'] : ($aiEngine['active_provider'] ?? 'anthropic');
                $model = ($roleRouting && !empty($agent['ai_model'])) ? $agent['ai_model'] : ($aiEngine['active_model'] ?? 'claude-sonnet-5-5');
                if ($useLocal) {
                    $prov = (string)($localState['runtime'] ?? 'ollama');
                    $model = (string)$localState['model'];
                }

                $statementText = '';
                if ($useLocal || $this->llm->isProviderConfigured($prov)) {
                    $systemPrompt = "Eres {$agent['name']} ({$agent['alias']}) en AgileForge. Especialidad: {$agent['specialty']}. " .
                        "Participas en una mesa formal de debate técnico ({$meetingType}). " .
                        "Aporta tu perspectiva profesional, argumentos y propuestas concretas en 2-3 párrafos técnicos sin rodeos ni saludos innecesarios.";

                    $turnPrompt = "{$accumulatedDiscussion}\nEs tu turno de intervenir como {$agent['name']}. ¿Cuál es tu postura, propuesta o recomendación técnica respecto a '{$topic}'?";

                    try {
                        if ($useLocal) {
                            $call = $this->local->chat(
                                $systemPrompt . ' Responde en español.',
                                [['role' => 'user', 'content' => $turnPrompt]],
                                $model
                            );
                            $this->llm->recordUsage($aId, null, 'default', $prov, $model,
                                $call['input_tokens'], $call['output_tokens'], $call['total_tokens'], 0.0, 'meeting_local_' . $meetingType);
                        } else {
                            $call = $this->llm->complete($prov, $model, $turnPrompt, $systemPrompt, [
                                'agent_role'     => $aId,
                                'context_source' => 'meeting_' . $meetingType
                            ]);
                        }
                        $statementText = $call['text'];
                    } catch (\Throwable $e) {
                        $statementText = "⚠️ Error al conectar con {$prov} ({$model}): " . $e->getMessage();
                    }
                } else {
                    $statementText = "⚠️ [Sin motor configurado para {$prov}]: Agrega tu clave en el archivo .env para respuestas automáticas, o ejecuta este turno mediante el plugin en Claude Code.";
                }

                $turns[] = [
                    'role_id'   => $agent['id'],
                    'name'      => $agent['name'],
                    'alias'     => $agent['alias'] ?? '',
                    'avatar'    => $agent['avatar'] ?? '🤖',
                    'ai_model'  => $model,
                    'statement' => $statementText,
                    'timestamp' => date('H:i')
                ];

                $accumulatedDiscussion .= "- Intervención de {$agent['name']} ({$agent['alias']}):\n\"{$statementText}\"\n\n";
            }

            $summary = "Deliberación real en vivo efectuada por los agentes convocados sobre '{$topic}'. Cada participante evaluó los trade-offs desde su especialidad técnica conforme a la normativa de {$juris['country_name']}.";
            $resolution = "ACUERDO ADOPTADO EN MESA: Integrar las recomendaciones de arquitectura, experiencia de usuario y cumplimiento normativo evaluadas en la sesión.";
            $actionItems = [
                [
                    'title'        => "Implementar acuerdos técnicos de la reunión '{$topic}'",
                    'assigned_to'  => $activeParticipants[0]['id'],
                    'story_points' => 3,
                    'priority'     => "alta",
                    'user_story'   => "Como equipo Scrum necesitamos materializar los acuerdos de la reunión '{$topic}'.",
                    'dod'          => "100% de criterios técnicos implementados y verificados con tests."
                ]
            ];
        } elseif ($meetingType === 'brainstorming') {
            // MODO PLUGIN / SIMULACIÓN ESTRUCTURADA
            foreach ($activeParticipants as $agent) {
                $aId = $agent['id'];
                $model = ($roleRouting && !empty($agent['ai_model'])) ? $agent['ai_model'] : ($aiEngine['active_model'] ?? 'Claude 3.7');
                $text = '';

                switch ($aId) {
                    case 'creative_director':
                        $text = "Propongo un enfoque disruptivo para '{$topic}': en lugar de un flujo convencional, activemos un micro-momento memorable (Regla del Pico-Fin) con feedback visual inmediato.";
                        break;
                    case 'frontend_master':
                        $text = "Apoyo la propuesta de The Visionary. Desde la perspectiva UX/UI, podemos resolverlo con un componente mobile-first en Tailwind con microinteracciones fluidas (<200ms) y contraste WCAG AAA.";
                        break;
                    case 'project_manager':
                        $text = "Excelente sinergia. Para mantener el alcance del Sprint controlado, dimensionemos esta innovación en historias de usuario bien acotadas con Definition of Done estricta.";
                        break;
                    case 'backend_architect':
                        $text = "Desde el backend, expondré un endpoint REST ultrarrápido y seguro con transacciones atómicas PDO y validación estricta de inputs.";
                        break;
                    case 'lead_architect':
                        $text = "Arquitectónicamente, mantendremos este módulo desacoplado mediante Clean Architecture y el patrón Repositorio para permitir evolucionar el motor de persistencia.";
                        break;
                    case 'qa_security_guard':
                        $text = "Aseguraré casos de prueba unitarios para los escenarios de borde y pruebas de regresión automáticas para blindar la estabilidad.";
                        break;
                    case 'legal_counsel':
                        $text = "Para cumplir plenamente con la normativa de {$juris['country_name']} {$juris['flag']}, es indispensable incluir cláusulas de consentimiento expreso y libro de reclamaciones.";
                        break;
                    default:
                        $text = "De acuerdo con el enfoque propuesto. Aportaré mis capacidades técnicas para garantizar agilidad y alto estándar de ingeniería.";
                        break;
                }

                $turns[] = [
                    'role_id'   => $agent['id'],
                    'name'      => $agent['name'],
                    'alias'     => $agent['alias'] ?? '',
                    'avatar'    => $agent['avatar'] ?? '🤖',
                    'ai_model'  => $model,
                    'statement' => $text,
                    'timestamp' => date('H:i')
                ];
            }

            $summary = "La sesión de Brainstorming sobre '{$topic}' resolvió fusionar micro-momentos emocionales visuales (The Visionary) con componentes reactivos mobile-first (Pixel Master), dimensionados en el backlog (The Orchestrator) con disclaimers legales transparentes (The Legal Shield).";
            $resolution = "APROBADO POR CONSENSO: Implementar innovación interactiva con cálculo en tiempo real sub-50ms en el Sprint actual.";
            $actionItems = [
                [
                    'title'        => "Diseñar componente interactivo para '{$topic}'",
                    'assigned_to'  => "frontend_master",
                    'story_points' => 3,
                    'priority'     => "alta",
                    'user_story'   => "Como usuario quiero una experiencia visual responsiva para '{$topic}'.",
                    'dod'          => "Contraste WCAG AAA y microinteracciones fluidas <200ms."
                ],
                [
                    'title'        => "Construir endpoint REST optimizado y seguro para '{$topic}'",
                    'assigned_to'  => "backend_architect",
                    'story_points' => 3,
                    'priority'     => "alta",
                    'user_story'   => "Como sistema necesito procesar los datos de '{$topic}' con transacciones atómicas.",
                    'dod'          => "100% transaccional con PDO, consultas preparadas y tests en verde."
                ]
            ];
        } else {
            foreach ($activeParticipants as $agent) {
                $aId = $agent['id'];
                $model = ($roleRouting && !empty($agent['ai_model'])) ? $agent['ai_model'] : ($aiEngine['active_model'] ?? 'Claude 3.7');
                $text = '';

                switch ($aId) {
                    case 'lead_architect':
                        $text = "Abro la sesión de decisión técnica sobre: '{$topic}'. Evaluando el trade-off arquitectónico: Mi recomendación técnica es usar un patrón Repositorio desacoplado con transacciones ACID PDO, preservando Clean Architecture.";
                        break;
                    case 'backend_architect':
                        $text = "Concuerdo plenamente con el Arquitecto. El patrón Repositorio nos permite trabajar con SQLite en desarrollo local y migrar a MySQL en producción sin tocar una sola línea de lógica de negocio.";
                        break;
                    case 'qa_security_guard':
                        $text = "Mi voto a favor está condicionado a: 1) Pruebas de mutación que verifiquen el rollback en fallos de transacción; 2) Cero concatenaciones de SQL crudo (100% sentencias preparadas).";
                        break;
                    case 'legal_counsel':
                        $text = "Desde el marco normativo de {$juris['country_name']} {$juris['flag']}: La persistencia de datos debe contemplar el principio de minimización de datos (LPDP / RGPD) y disociar PII en logs.";
                        break;
                    case 'project_manager':
                        $text = "Analizando el impacto en la velocidad: Esta decisión representa una inversión que previene deuda técnica acumulada en los próximos sprints. Autorizo el ADR.";
                        break;
                    default:
                        $text = "Revisados los antecedentes y consideraciones técnicas. Voto a favor de la decisión adoptada.";
                        break;
                }

                $turns[] = [
                    'role_id'   => $agent['id'],
                    'name'      => $agent['name'],
                    'alias'     => $agent['alias'] ?? '',
                    'avatar'    => $agent['avatar'] ?? '🤖',
                    'ai_model'  => $model,
                    'statement' => $text,
                    'timestamp' => date('H:i')
                ];
            }

            $summary = "El Consejo Técnico debatió y resolvió de forma unánime el tratamiento de '{$topic}'. Se adoptó formalmente la arquitectura de persistencia mediante Patrón Repositorio con transacciones ACID PDO y minimización de datos conforme a ley.";
            $resolution = "ADR APROBADO: Adoptar Patrón Repositorio desacoplado con transacciones atómicas PDO.";
            $actionItems = [
                [
                    'title'        => "Implementar Repositorio transaccional desacoplado con PDO",
                    'assigned_to'  => "backend_architect",
                    'story_points' => 5,
                    'priority'     => "alta",
                    'user_story'   => "Como desarrollador quiero un repositorio con transacciones atómicas para blindar la consistencia de datos.",
                    'dod'          => "100% consultas preparadas, soporte SQLite/MySQL y transacciones atómicas."
                ]
            ];
        }

        $cntStmt = $pdo->query("SELECT COUNT(*) FROM meetings");
        $meetId = 'MEET-' . str_pad((string)((int)$cntStmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
        $title = ($meetingType === 'brainstorming' ? '💡 Brainstorming: ' : '🏛️ Consejo ADR: ') . $topic;

        Database::transaction(function (PDO $pdo) use ($meetId, $title, $meetingType, $topic, $participants, $summary, $resolution, $actionItems, $turns) {
            $stmt = $pdo->prepare(
                "INSERT INTO meetings (id, project_id, title, type, topic, participants_json, summary, resolution, action_items_json, converted)
                 VALUES (?, 'default', ?, ?, ?, ?, ?, ?, ?, 0)"
            );
            $stmt->execute([
                $meetId, $title, $meetingType, $topic, json_encode($participants, JSON_UNESCAPED_UNICODE),
                $summary, $resolution, json_encode($actionItems, JSON_UNESCAPED_UNICODE)
            ]);

            $turnStmt = $pdo->prepare(
                "INSERT INTO meeting_turns (meeting_id, turn_index, role_id, name, alias, avatar, ai_model, statement, timestamp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($turns as $idx => $t) {
                $turnStmt->execute([
                    $meetId, $idx, $t['role_id'], $t['name'], $t['alias'], $t['avatar'], $t['ai_model'], $t['statement'], $t['timestamp']
                ]);
            }
        });

        $createdMeeting = [
            'id'           => $meetId,
            'title'        => $title,
            'type'         => $meetingType,
            'topic'        => $topic,
            'created_at'   => date('Y-m-d H:i'),
            'participants' => $participants,
            'turns'        => $turns,
            'summary'      => $summary,
            'resolution'   => $resolution,
            'action_items' => $actionItems,
            'converted'    => false
        ];

        Bootstrap::jsonResponse(['success' => true, 'meeting' => $createdMeeting]);
    }

    public function convertToTasks(string $meetingId): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch();

        if (!$meeting) {
            Bootstrap::jsonResponse(['error' => 'Reunión no encontrada'], 404);
        }

        $actionItems = json_decode($meeting['action_items_json'] ?? '[]', true) ?: [];
        if (empty($actionItems)) {
            Bootstrap::jsonResponse(['error' => 'Esta reunión no contiene acuerdos o tareas pendientes por convertir'], 422);
        }

        $createdTasks = [];
        Database::transaction(function (PDO $pdo) use ($meeting, $actionItems, &$createdTasks) {
            $tStmt = $pdo->prepare(
                "INSERT INTO tasks (id, project_id, title, story_points, assigned_to, priority, status, user_story, dod, description, skills_json, prompt_template)
                 VALUES (?, 'default', ?, ?, ?, ?, 'backlog', ?, ?, ?, ?, ?)"
            );

            foreach ($actionItems as $idx => $item) {
                $taskId = 'US-' . (100 + rand(10, 999));
                $title = $item['title'] ?? "Acción #{$idx} derivada de {$meeting['id']}";
                $points = max(1, (int)($item['story_points'] ?? 2));
                $role = $item['assigned_to'] ?? 'project_manager';
                $priority = $item['priority'] ?? 'media';
                $userStory = $item['user_story'] ?? "Como equipo requiero implementar el acuerdo de la reunión {$meeting['id']}";
                $dod = $item['dod'] ?? 'Implementación validada y probada.';
                $desc = "Derivado del acta: {$meeting['title']}";
                $prompt = "Actúa como {$role}. Ejecuta la tarea: {$title}. DoD: {$dod}";

                $tStmt->execute([
                    $taskId, $title, $points, $role, $priority, $userStory, $dod, $desc,
                    json_encode(['lean-compact'], JSON_UNESCAPED_UNICODE), $prompt
                ]);

                $createdTasks[] = ['id' => $taskId, 'title' => $title, 'assigned_to' => $role];
            }

            $uStmt = $pdo->prepare("UPDATE meetings SET converted = 1 WHERE id = ?");
            $uStmt->execute([$meeting['id']]);
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
