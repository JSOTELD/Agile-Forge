<?php
/**
 * AgileForge - REST API Router (Punto de Entrada para Servidor MCP & Clientes Externos)
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

Bootstrap::init();
// Este endpoint acepta el token de vista (solo GET); api/tasks.php no (ADR-004)
$authKind = Bootstrap::requireAuth(true);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Normalizar la ruta: la base se deriva de SCRIPT_NAME (…/api/index.php), así funciona en /, en /Open-to-code/
// o en cualquier subcarpeta, con y sin "index.php/" en la URL.
$base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'))), '/');
if ($base !== '' && ($uri === $base || str_starts_with($uri, $base . '/'))) {
    $uri = substr($uri, strlen($base));
}
$path = trim($uri, '/');
$path = preg_replace('#^index\.php(/|$)#', '', $path);
$segments = $path !== '' ? explode('/', $path) : [];

// Token de vista: solo la lista blanca de rutas GET (H2); se comprueba antes de tocar la base de datos
if ($authKind === 'view') {
    Bootstrap::requireViewRoute($segments);
}

Database::init();

$taskCtrl = new TaskController();
$meetingCtrl = new MeetingController();
$roleCtrl = new RoleController();
$skillCtrl = new SkillController();
$settingCtrl = new SettingController();
$chatCtrl = new ChatController();
$decisionCtrl = new DecisionController();
$sprintCtrl = new SprintController();
$repo = new Repository();

// Rutas de la API REST
$res = $segments[0] ?? '';

if ($res === 'state' && $method === 'GET') {
    Bootstrap::jsonResponse($repo->getFullState());
}

// Resumen ligero y saneado para vistas de solo lectura (sin claves, rutas ni settings)
if ($res === 'overview' && $method === 'GET') {
    try {
        Bootstrap::jsonResponse($repo->getOverview(isset($_GET['project']) && $_GET['project'] !== '' ? (string)$_GET['project'] : null), 200, false);
    } catch (\InvalidArgumentException $e) {
        Bootstrap::fail($e->getMessage(), 404);
    }
}

if ($res === 'events' && $method === 'GET') {
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 30;
    try {
        $events = $repo->getEvents($limit, isset($_GET['project']) && $_GET['project'] !== '' ? (string)$_GET['project'] : null);
    } catch (\InvalidArgumentException $e) {
        Bootstrap::fail($e->getMessage(), 404);
    }
    Bootstrap::jsonResponse(['success' => true, 'count' => count($events), 'events' => $events], 200, false);
}

if ($res === 'sprint') {
    if (($segments[1] ?? '') === 'status' && $method === 'GET') {
        $sprintCtrl->getStatus();
    }
    if (($segments[1] ?? '') === 'start' && $method === 'POST') {
        $sprintCtrl->start(Bootstrap::requireJsonBody());
    }
}

if ($res === 'tasks') {
    $taskId = $segments[1] ?? null;
    $subAction = $segments[2] ?? null;

    if ($taskId === null) {
        if ($method === 'GET') {
            $taskCtrl->list($_GET);
        } elseif ($method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $taskCtrl->create($input);
        }
    } else {
        if ($subAction === null) {
            if ($method === 'GET') {
                $taskCtrl->show($taskId);
            }
        } elseif ($subAction === 'claim' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $taskCtrl->claim($taskId, (string)($input['role_key'] ?? $input['role'] ?? ''), (string)($input['client'] ?? 'mcp'));
        } elseif ($subAction === 'status' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $taskCtrl->updateStatus(
                $taskId,
                (string)($input['status'] ?? ''),
                isset($input['note']) ? (string)$input['note'] : null,
                isset($input['role']) ? (string)$input['role'] : null,
                (string)($input['client'] ?? 'api')
            );
        } elseif ($subAction === 'submit-review' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $taskCtrl->submitForReview($taskId, (array)($input['evidence'] ?? []), (string)($input['role_key'] ?? $input['role'] ?? 'developer'));
        } elseif ($subAction === 'review' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $taskCtrl->review(
                $taskId,
                (string)($input['decision'] ?? ''),
                (array)($input['checklist'] ?? []),
                (string)($input['reviewer_role'] ?? $input['reviewer'] ?? '')
            );
        } elseif ($subAction === 'comments' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $taskCtrl->addComment($taskId, $input);
        }
    }
}

if ($res === 'meetings') {
    $meetId = $segments[1] ?? null;
    $subAction = $segments[2] ?? null;

    if ($meetId === null) {
        if ($method === 'GET') {
            $meetingCtrl->list($_GET);
        } elseif ($method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $meetingCtrl->start($input);
        }
    } else {
        if ($subAction === null && $method === 'GET') {
            $meetingCtrl->show($meetId);
        } elseif ($subAction === 'turns' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $meetingCtrl->addTurn($meetId, $input);
        } elseif ($subAction === 'close' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $meetingCtrl->close($meetId, $input);
        } elseif ($subAction === 'convert-to-tasks' && $method === 'POST') {
            $meetingCtrl->convertToTasks($meetId);
        } elseif ($subAction === 'save' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $meetingCtrl->saveToWorkspace($meetId, isset($input['workspace']) ? (string)$input['workspace'] : null);
        }
    }
}

if ($res === 'roles') {
    $roleId = $segments[1] ?? null;
    $subAction = $segments[2] ?? null;

    if ($roleId === null && $method === 'GET') {
        $roleCtrl->list();
    } elseif ($roleId !== null) {
        if ($subAction === 'brief' && $method === 'GET') {
            $roleCtrl->getBrief($roleId);
        } elseif ($subAction === null && $method === 'GET') {
            $roleCtrl->show($roleId);
        } elseif ($subAction === 'skills' && $method === 'POST') {
            $input = Bootstrap::requireJsonBody();
            $roleCtrl->updateSkills($roleId, (array)($input['skills'] ?? []));
        }
    }
}

if ($res === 'skills') {
    if ($method === 'GET') {
        $skillCtrl->list();
    } elseif ($method === 'POST') {
        $input = Bootstrap::requireJsonBody();
        $skillCtrl->create($input);
    }
}

if ($res === 'decisions') {
    if ($method === 'GET') {
        $decisionCtrl->list($_GET);
    } elseif ($method === 'POST') {
        $input = Bootstrap::requireJsonBody();
        $decisionCtrl->record($input);
    }
}

// Si ninguna ruta coincide
Bootstrap::jsonResponse([
    'error'     => 'Ruta no encontrada',
    'path'      => $path,
    'endpoints' => [
        'GET  /api/state',
        'GET  /api/overview[?project=<id>]',
        'POST /api/sprint/start',
        'GET  /api/events?limit=N',
        'GET  /api/sprint/status',
        'GET  /api/tasks[?compact=1]',
        'GET  /api/tasks/{id}',
        'POST /api/tasks/{id}/claim',
        'POST /api/tasks/{id}/status',
        'POST /api/tasks/{id}/submit-review',
        'POST /api/tasks/{id}/review',
        'GET  /api/meetings[?limit=N&compact=1]',
        'GET  /api/meetings/{id}',
        'POST /api/meetings/{id}/turns',
        'POST /api/meetings/{id}/close',
        'GET  /api/roles',
        'GET  /api/roles/{id}/brief',
        'GET  /api/skills',
        'GET  /api/decisions[?limit=N]',
        'POST /api/decisions'
    ]
], 404);
