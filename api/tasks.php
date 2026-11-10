<?php
/**
 * AgileForge - Dispatcher Principal & API Gateway
 * Enruta peticiones hacia los controladores correspondientes con seguridad y persistencia SQLite.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/controllers/TaskController.php';
require_once __DIR__ . '/controllers/MeetingController.php';
require_once __DIR__ . '/controllers/RoleController.php';
require_once __DIR__ . '/controllers/SkillController.php';
require_once __DIR__ . '/controllers/SettingController.php';
require_once __DIR__ . '/controllers/ChatController.php';
require_once __DIR__ . '/controllers/DecisionController.php';
require_once __DIR__ . '/controllers/SprintController.php';
require_once __DIR__ . '/controllers/ProjectController.php';
require_once __DIR__ . '/controllers/LocalAiController.php';
require_once __DIR__ . '/controllers/MemoryController.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Controllers\TaskController;
use AgileForge\Controllers\MeetingController;
use AgileForge\Controllers\RoleController;
use AgileForge\Controllers\SkillController;
use AgileForge\Controllers\SettingController;
use AgileForge\Controllers\ChatController;
use AgileForge\Controllers\DecisionController;
use AgileForge\Controllers\SprintController;
use AgileForge\Controllers\ProjectController;
use AgileForge\Controllers\LocalAiController;
use AgileForge\Controllers\MemoryController;

// 1. Inicializar seguridad, headers y variables de entorno
Bootstrap::init();

// 2. Verificar autenticación obligatoria
Bootstrap::requireAuth();

// 3. Inicializar base de datos SQLite y Repositorio
Database::init();
$repo = new Repository();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Petición GET: Devuelve el estado completo del tablero y del equipo
if ($method === 'GET') {
    $state = $repo->getFullState();
    Bootstrap::jsonResponse($state);
}

// Peticiones POST / Mutaciones: Exigir JSON y despachar por acción
if ($method === 'POST') {
    $input = Bootstrap::requireJsonBody();
    $action = (string)($input['action'] ?? '');

    if ($action === '') {
        Bootstrap::jsonResponse(['error' => 'Acción no especificada en el cuerpo de la petición'], 400);
    }

    $taskCtrl = new TaskController();
    $meetingCtrl = new MeetingController();
    $roleCtrl = new RoleController();
    $skillCtrl = new SkillController();
    $settingCtrl = new SettingController();
    $chatCtrl = new ChatController();
    $decisionCtrl = new DecisionController();
    $sprintCtrl = new SprintController();
    $projCtrl = new ProjectController();
    $localAiCtrl = new LocalAiController();
    $memoryCtrl = new MemoryController();

    switch ($action) {
        // Multi-Proyecto & Selector de Workspaces
        case 'list_projects':
            $projCtrl->list();
            break;

        case 'create_project':
            $projCtrl->create($input);
            break;

        case 'switch_project':
            $projId = (string)($input['project_id'] ?? '');
            $projCtrl->switchProject($projId);
            break;

        case 'import_workspace':
            $projCtrl->importWorkspace($input);
            break;

        // Evaluación Heurística Honesta UX
        case 'save_ux_heuristics':
            $settingCtrl->saveUxHeuristics($input);
            break;

        // Workspace & Configuración
        case 'update_workspace':
            $ws = (string)($input['workspace'] ?? '');
            $settingCtrl->updateWorkspace($ws);
            break;

        case 'update_human_signoff':
        case 'toggle_human_signoff':
            $settingCtrl->updateHumanSignoff($input);
            break;

        case 'update_jurisdiction':
            $settingCtrl->updateJurisdiction($input);
            break;

        case 'save_ai_engine_settings':
        case 'update_ai_engine':
            $settingCtrl->updateAiEngine($input);
            break;

        // Claves de API (se guardan en .env; nunca vuelven al navegador)
        case 'save_api_key':
            $settingCtrl->saveApiKey($input);
            break;

        case 'save_anthropic_workspace':
            $settingCtrl->saveAnthropicWorkspace($input);
            break;

        case 'remove_api_key':
            $settingCtrl->removeApiKey($input);
            break;

        case 'test_provider':
            $settingCtrl->testProvider($input);
            break;

        case 'save_ui_preferences':
            $settingCtrl->saveUiPreferences($input);
            break;

        case 'audit_workspace_legal':
            $settingCtrl->auditWorkspaceLegal();
            break;

        case 'generate_legal_doc':
        case 'generate_legal_document':
            $settingCtrl->generateLegalDoc($input);
            break;

        // Gestión de Tareas e Historias Scrum
        case 'create_task':
            $taskCtrl->create($input);
            break;

        case 'update_task':
            $taskCtrl->update($input);
            break;

        case 'delete_task':
            $taskCtrl->delete($input);
            break;

        case 'update_status':
        case 'update_task_status':
            $taskId = (string)($input['task_id'] ?? '');
            $status = (string)($input['status'] ?? '');
            $note = isset($input['note']) ? (string)$input['note'] : null;
            $roleKey = isset($input['role']) ? (string)$input['role'] : null;
            // Sin client explícito se asume 'api' (nunca 'web'): la aprobación humana exige client: "web" desde la interfaz
            $client = isset($input['client']) && $input['client'] !== '' ? (string)$input['client'] : 'api';
            $taskCtrl->updateStatus($taskId, $status, $note, $roleKey, $client);
            break;

        case 'claim_task':
            $taskId = (string)($input['task_id'] ?? $input['id'] ?? '');
            $roleKey = (string)($input['role_key'] ?? $input['role'] ?? '');
            $client = (string)($input['client'] ?? 'mcp');
            $taskCtrl->claim($taskId, $roleKey, $client);
            break;

        case 'submit_for_review':
            $taskId = (string)($input['task_id'] ?? $input['id'] ?? '');
            $evidence = is_array($input['evidence'] ?? null) ? $input['evidence'] : [];
            $roleKey = (string)($input['role_key'] ?? $input['role'] ?? 'developer');
            $taskCtrl->submitForReview($taskId, $evidence, $roleKey);
            break;

        case 'review_task':
            $taskId = (string)($input['task_id'] ?? $input['id'] ?? '');
            $decision = (string)($input['decision'] ?? '');
            $checklist = is_array($input['checklist'] ?? null) ? $input['checklist'] : [];
            $reviewerRole = (string)($input['reviewer_role'] ?? $input['reviewer'] ?? '');
            $verification = is_array($input['verification'] ?? null) ? $input['verification'] : null;
            $taskCtrl->review($taskId, $decision, $checklist, $reviewerRole, $verification);
            break;

        case 'add_comment':
            $taskId = (string)($input['task_id'] ?? '');
            $taskCtrl->addComment($taskId, $input);
            break;

        // Roles & Skills
        case 'update_agent_skills':
            $roleId = (string)($input['role_id'] ?? '');
            $skills = is_array($input['skills'] ?? null) ? $input['skills'] : [];
            $roleCtrl->updateSkills($roleId, $skills);
            break;

        case 'update_role_engine':
            $roleCtrl->updateEngine($input);
            break;

        case 'get_role_brief':
            $roleId = (string)($input['role_id'] ?? '');
            $roleCtrl->getBrief($roleId);
            break;

        case 'add_custom_skill':
            $skillCtrl->create($input);
            break;

        // IA local (Ollama): detectar, activar/desactivar, elegir modelo y probar
        case 'detect_local_ai':
            $localAiCtrl->detect();
            break;

        case 'toggle_local_ai':
            $localAiCtrl->toggle($input);
            break;

        case 'update_local_ai':
            $localAiCtrl->update($input);
            break;

        case 'update_local_ai_settings':
            $localAiCtrl->settings($input);
            break;

        case 'test_local_ai':
            $localAiCtrl->test();
            break;

        // Chat
        case 'send_chat':
        case 'send_chat_message':
            $chatCtrl->sendMessage($input);
            break;

        // Director General: instrucciones que decide y ejecuta sobre el tablero
        case 'director_instruction':
            $chatCtrl->sendMessage(['target_role' => 'director', 'message' => (string)($input['instruction'] ?? $input['message'] ?? '')]);
            break;

        // Salas de Reunión
        case 'meeting_begin':
            $meetingCtrl->begin($input);
            break;

        case 'meeting_turn':
            $meetingCtrl->turn((string)($input['meeting_id'] ?? ''), (string)($input['role_id'] ?? ''), !empty($input['stream']));
            break;

        case 'meeting_conclude':
            $meetingCtrl->conclude((string)($input['meeting_id'] ?? ''));
            break;

        case 'start_meeting':
            $meetingCtrl->start($input);
            break;

        case 'add_meeting_turn':
            $meetingId = (string)($input['meeting_id'] ?? '');
            $meetingCtrl->addTurn($meetingId, $input);
            break;

        case 'close_meeting':
            $meetingId = (string)($input['meeting_id'] ?? '');
            $meetingCtrl->close($meetingId, $input);
            break;

        case 'run_agent_meeting':
        case 'launch_collaborative_meeting':
            $meetingCtrl->runAgentMeeting($input);
            break;

        case 'convert_meeting_to_tasks':
            $meetingId = (string)($input['meeting_id'] ?? '');
            $meetingCtrl->convertToTasks($meetingId);
            break;

        case 'save_meeting_to_workspace':
            $meetingId = (string)($input['meeting_id'] ?? '');
            $ws = isset($input['workspace']) ? (string)$input['workspace'] : null;
            $meetingCtrl->saveToWorkspace($meetingId, $ws);
            break;

        // Decisiones Arquitectónicas (ADR)
        case 'record_decision':
            $decisionCtrl->record($input);
            break;

        // Sprint Status
        case 'get_sprint_status':
            $sprintCtrl->getStatus();
            break;

        case 'start_sprint':
            $sprintCtrl->start($input);
            break;

        // Presupuesto de Sprint
        case 'update_sprint_budget':
            $sprintId = trim((string)($input['sprint_id'] ?? ''));
            $rawBudget = $input['budget_usd'] ?? 0;
            if (!is_numeric($rawBudget) || (float)$rawBudget < 0 || (float)$rawBudget > 100000) {
                Bootstrap::fail('El presupuesto debe ser un número entre 0 y 100000 USD (0 = sin límite).', 422);
            }
            $budget = round((float)$rawBudget, 2);
            $sprint = $sprintId !== '' ? $repo->getSprint($sprintId) : $repo->getActiveSprint();
            if (!$sprint) {
                Bootstrap::fail('Sprint no encontrado', 404);
            }
            Database::getPdo()->prepare("UPDATE sprints SET budget_usd = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$budget, $sprint['id']]);
            Bootstrap::jsonResponse(['success' => true, 'sprint_id' => $sprint['id'], 'budget_usd' => $budget]);
            break;

        // Memoria Nodular de Agentes
        case 'list_memories':
            $memoryCtrl->list($input);
            break;

        case 'get_memory':
            $memId = (string)($input['memory_id'] ?? $input['id'] ?? '');
            $memoryCtrl->show($memId);
            break;

        case 'save_memory':
            $memoryCtrl->save($input);
            break;

        case 'toggle_memory':
            $memId = (string)($input['memory_id'] ?? $input['id'] ?? '');
            $memoryCtrl->toggle($memId, $input);
            break;

        case 'delete_memory':
            $memId = (string)($input['memory_id'] ?? $input['id'] ?? '');
            $memoryCtrl->delete($memId);
            break;

        // Generar prompt de revisión
        case 'generate_review_prompt':
            $taskId = (string)($input['task_id'] ?? '');
            $task = $repo->getTask($taskId);
            if (!$task) {
                Bootstrap::jsonResponse(['error' => 'Tarea no encontrada'], 404);
            }
            $ws = $repo->getSetting('active_workspace', dirname(__DIR__));
            $juris = $repo->getSetting('jurisdiction', ['country_name' => 'Perú', 'flag' => '🇵🇪']);
            $isLegal = ($task['assigned_to'] ?? '') === 'legal_counsel';

            if ($isLegal) {
                $prompt = sprintf(
                    "Actúa como Abogado & Compliance Officer (The Legal Shield).\n📂 WORKSPACE: %s\n⚖️ JURISDICCIÓN: %s %s\nAuditoría formal de Cumplimiento para [%s]: %s.\nEmite tu veredicto: [CERTIFICACIÓN LEGAL APROBADA] o [OBSERVACIÓN: REQUIERE AJUSTES LEGALES].",
                    $ws, $juris['country_name'], $juris['flag'], $task['id'], $task['title']
                );
            } else {
                $prompt = sprintf(
                    "Actúa como Scrum Master y QA Lead Auditor (The Gatekeeper).\n📂 WORKSPACE: %s\nAuditoría formal de Definition of Done (DoD) para [%s]: %s.\nHistoria: %s\nDoD: %s\nEmite tu veredicto: [DoD CERTIFICADA PARA MERGE] o [REABIERTA CON OBSERVACIONES].",
                    $ws, $task['id'], $task['title'], $task['user_story'] ?? '', $task['dod'] ?? ''
                );
            }
            Bootstrap::jsonResponse(['success' => true, 'review_prompt' => $prompt]);
            break;

        default:
            Bootstrap::jsonResponse(['error' => "Acción desconocida: '{$action}'"], 400);
            break;
    }
}
