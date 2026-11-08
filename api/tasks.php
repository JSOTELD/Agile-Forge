<?php
/**
 * API REST Local para AgileForge · Open-to-code
 * Metodología Ágil (Scrum + PMBOK), Chat de Agentes, Workspace Picker y Multi-Skills
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataFile = dirname(__DIR__) . '/data/team.json';

if (!is_file($dataFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se encontró team.json']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = json_decode((string)file_get_contents($dataFile), true);

if ($method === 'GET') {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode((string)$raw, true);

    if (!is_array($input) || empty($input['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
        exit;
    }

    $action = $input['action'];

    // 1. Cambiar Carpeta de Trabajo (Active Workspace)
    if ($action === 'update_workspace') {
        $ws = trim((string)($input['workspace'] ?? ''));
        if ($ws !== '') {
            $data['active_workspace'] = $ws;
            file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'active_workspace' => $ws]);
            exit;
        }
    }

    // 1.1 Actualizar Jurisdicción y Normativas Legales por País
    if ($action === 'update_jurisdiction') {
        $countryCode = strtoupper(trim((string)($input['country_code'] ?? 'PE')));
        $countryName = trim((string)($input['country_name'] ?? 'Perú'));
        $flag = trim((string)($input['flag'] ?? '🇵🇪'));
        $regulations = is_array($input['regulations'] ?? null) ? $input['regulations'] : [];
        $autoDetected = !empty($input['auto_detected']);

        $cleanRegs = [];
        foreach ($regulations as $r) {
            $clean = trim((string)$r);
            if ($clean !== '') {
                $cleanRegs[] = $clean;
            }
        }

        $data['jurisdiction'] = [
            'country_code'  => $countryCode,
            'country_name'  => $countryName,
            'flag'          => $flag,
            'auto_detected' => $autoDetected,
            'regulations'   => array_values(array_unique($cleanRegs)),
        ];

        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'jurisdiction' => $data['jurisdiction']]);
        exit;
    }

    // 2. Chat: Enviar consulta a un Agente o a Todos
    if ($action === 'send_chat') {
        $targetRole = (string)($input['target_role'] ?? 'project_manager');
        $queryText = trim((string)($input['message'] ?? ''));

        if ($queryText === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Mensaje vacío']);
            exit;
        }

        // Registrar mensaje del usuario
        $userMsg = [
            'id'          => 'MSG-' . time(),
            'sender_role' => 'user',
            'sender_name' => 'Tú (Líder de Producto)',
            'avatar'      => '👤',
            'timestamp'   => date('H:i'),
            'text'        => $queryText,
        ];
        $data['chat_messages'][] = $userMsg;

        // Respuesta simulada inteligente del Agente según su especialidad
        $agent = null;
        foreach ($data['roles'] as $r) {
            if ($r['id'] === $targetRole) {
                $agent = $r;
                break;
            }
        }
        $agentName = $agent ? $agent['name'] : 'Agente IA';
        $agentAvatar = $agent ? $agent['avatar'] : '🤖';

        $juris = $data['jurisdiction'] ?? [
            'country_name' => 'Perú',
            'flag' => '🇵🇪',
            'regulations' => [
                'Ley N° 29733 (Protección de Datos Personales)',
                'Ley N° 29571 (Código de Protección y Defensa del Consumidor)',
                'Libro de Reclamaciones Virtual INDECOPI'
            ]
        ];
        $regsSample = !empty($juris['regulations']) ? implode(' · ', array_slice($juris['regulations'], 0, 3)) : 'Normativas de Comercio Electrónico y PII';

        $responses = [
            'project_manager'   => "Desde la perspectiva Scrum y PMBOK: Analizando el impacto en el Sprint Backlog. Recomiendo dimensionar esto en una Historia de Usuario con su respectiva Definition of Done (DoD) para no comprometer la velocidad del Sprint.",
            'creative_director' => "¡Me encanta el enfoque innovador! Para diferenciarlo en el mercado, sugiero agregar un elemento emocional que conecte con la tranquilidad de los dueños, aprovechando la identidad visual campestre.",
            'lead_architect'    => "Evaluando impacto arquitectónico en " . ($data['active_workspace'] ?? 'el workspace') . ": Mantengamos el principio de bajo acoplamiento y alta cohesión. No mezclemos lógica de negocio en las vistas.",
            'frontend_master'   => "Entendido. Aplicaré las heurísticas de diseño de mi toolkit (impeccable + accessibility-wcag) para asegurar contraste AA, microinteracciones suaves y diseño 100% responsivo mobile-first.",
            'backend_architect' => "Registrado. Diseñaré la lógica con transacciones atómicas, validación estricta y consultas preparadas con PDO para blindar la base de datos.",
            'qa_security_guard' => "Alerta recibida. Prepararé casos de prueba unitarios y de mutación para asegurar que ningún cambio rompa los 290 tests actuales.",
            'legal_counsel'     => "Dictamen Legal para {$juris['country_name']} {$juris['flag']}: Bajo el marco de [{$regsSample}], es imperativo incluir cláusula de consentimiento expreso para tratamiento de datos personales, exención de responsabilidad civil en custodia de mascotas y enlace visible al Libro de Reclamaciones o canal de disputas obligatorio por ley."
        ];

        $replyText = $responses[$targetRole] ?? "Recibido. Analizando requerimiento con mi conjunto de skills asignadas.";

        $agentMsg = [
            'id'          => 'MSG-' . (time() + 1),
            'sender_role' => $targetRole,
            'sender_name' => $agentName,
            'avatar'      => $agentAvatar,
            'timestamp'   => date('H:i'),
            'text'        => $replyText,
        ];
        $data['chat_messages'][] = $agentMsg;

        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'messages' => [$userMsg, $agentMsg]]);
        exit;
    }

    // 3. Crear Historia de Usuario / Tarea Scrum con Multi-Skills y Workspace inyectado
    if ($action === 'create_task') {
        $title = trim((string)($input['title'] ?? ''));
        $roleId = trim((string)($input['assigned_to'] ?? 'backend_architect'));
        $priority = in_array($input['priority'] ?? '', ['baja', 'media', 'alta', 'critica'], true) ? $input['priority'] : 'media';
        $desc = trim((string)($input['description'] ?? ''));
        $userStory = trim((string)($input['user_story'] ?? ''));
        $dod = trim((string)($input['dod'] ?? 'Cero errores de lint y tests en verde.'));
        $points = max(1, min(13, (int)($input['story_points'] ?? 3)));

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['error' => 'El título es obligatorio']);
            exit;
        }

        // Buscar el rol y sus skills
        $roleName = 'Especialista';
        $alias = 'Expert';
        $specialty = 'Desarrollo de Software';
        $skills = [];
        foreach ($data['roles'] as $r) {
            if ($r['id'] === $roleId) {
                $roleName = $r['name'];
                $alias = $r['alias'];
                $specialty = $r['specialty'];
                $skills = $r['assigned_skills'] ?? [];
                break;
            }
        }

        $skillsFormatted = !empty($skills)
            ? implode(', ', array_map(static fn ($s) => "`$s`", $skills))
            : '`context-sentinel`';

        $ws = $data['active_workspace'] ?? 'D:\\xampp\\htdocs\\canil perruno';

        $juris = $data['jurisdiction'] ?? [
            'country_name' => 'Perú',
            'country_code' => 'PE',
            'flag' => '🇵🇪',
            'regulations' => []
        ];
        $regsFormatted = !empty($juris['regulations'])
            ? implode("\n", array_map(static fn ($r) => "  - $r", $juris['regulations']))
            : "  - Normativa estándar de comercio electrónico y protección de datos.";

        // Prompt de Scrum Master con Metodología Ágil, Workspace y Jurisdicción Legal
        $prompt = sprintf(
            "Actúa como %s (%s) especializado en %s.\n\n" .
            "📂 CARPETA DE TRABAJO (WORKSPACE ACTIVO):\n%s\n\n" .
            "⚖️ JURISDICCIÓN Y MARCO LEGAL VIGENTE (%s %s):\n%s\n\n" .
            "⚡ SUITE DE SKILLS OBLIGATORIAS:\n%s\n\n" .
            "🎯 HISTORIA DE USUARIO / MISIÓN (%d Story Points):\n%s\n%s\n\n" .
            "📋 DETALLES TÉCNICOS & ALCANCE:\n%s\n\n" .
            "🏁 DEFINITION OF DONE (DoD - Estándar Scrum):\n- %s\n" .
            "- Cero errores de sintaxis (lint 0 errores).\n" .
            "- Cumplimiento con las normativas legales locales de %s y sin efectos colaterales en la arquitectura.",
            $roleName,
            $alias,
            $specialty,
            $ws,
            $juris['country_name'],
            $juris['flag'],
            $regsFormatted,
            $skillsFormatted,
            $points,
            $title,
            $userStory !== '' ? "Historia: $userStory" : '',
            $desc !== '' ? $desc : 'Implementación según estándares del proyecto.',
            $dod,
            $juris['country_name']
        );

        $newId = 'US-' . str_pad((string)(count($data['tasks']) + 101), 3, '0', STR_PAD_LEFT);
        $newTask = [
            'id'              => $newId,
            'title'           => $title,
            'story_points'    => $points,
            'assigned_to'     => $roleId,
            'priority'        => $priority,
            'status'          => 'backlog',
            'created_at'      => date('Y-m-d H:i:s'),
            'user_story'      => $userStory,
            'dod'             => $dod,
            'description'     => $desc,
            'prompt_template' => $prompt,
        ];

        array_unshift($data['tasks'], $newTask);
        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        echo json_encode(['success' => true, 'task' => $newTask]);
        exit;
    }

    // 4. Cambiar estado en el tablero Kanban
    if ($action === 'update_status') {
        $taskId = (string)($input['task_id'] ?? '');
        $status = (string)($input['status'] ?? '');

        if (!in_array($status, ['backlog', 'en_progreso', 'revision', 'completada'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Estado no válido']);
            exit;
        }

        foreach ($data['tasks'] as $i => $t) {
            if ($t['id'] === $taskId) {
                $data['tasks'][$i]['status'] = $status;
                break;
            }
        }

        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    // 5. Configurar Skills de un Agente
    if ($action === 'update_agent_skills') {
        $roleId = (string)($input['role_id'] ?? '');
        $skills = is_array($input['skills'] ?? null) ? $input['skills'] : [];

        foreach ($data['roles'] as $i => $r) {
            if ($r['id'] === $roleId) {
                $data['roles'][$i]['assigned_skills'] = array_values(array_unique($skills));
                break;
            }
        }

        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    // 6. Generar Prompt de Revisión de DoD (Definition of Done)
    if ($action === 'generate_review_prompt') {
        $taskId = (string)($input['task_id'] ?? '');
        $task = null;
        foreach ($data['tasks'] as $t) {
            if ($t['id'] === $taskId) {
                $task = $t;
                break;
            }
        }

        $ws = $data['active_workspace'] ?? 'D:\\xampp\\htdocs\\canil perruno';
        $juris = $data['jurisdiction'] ?? ['country_name' => 'Perú', 'flag' => '🇵🇪'];
        $isLegal = ($task['assigned_to'] ?? '') === 'legal_counsel';

        if ($isLegal) {
            $reviewPrompt = sprintf(
                "Actúa como Abogado & Compliance Officer (The Legal Shield).\n" .
                "📂 WORKSPACE: %s\n" .
                "⚖️ JURISDICCIÓN AUDITADA: %s %s\n" .
                "Skills activas: `compliance-sentinel`, `legal-frameworks`, `privacy-gdpr-latam`, `consumer-protection`.\n\n" .
                "Auditoría formal de Cumplimiento Normativo y Legal para [%s]: %s.\n\n" .
                "Checklist de Dictamen Jurídico:\n" .
                "1. Protección de Datos Personales (PII): consentimiento expreso y política accesible.\n" .
                "2. Términos de Servicio y Contratación: límites de responsabilidad e indemnidad.\n" .
                "3. Libro de Reclamaciones / Canal de Disputas del Consumidor operativo según normativa de %s.\n" .
                "4. Prevención de cláusulas abusivas o publicidad engañosa.\n\n" .
                "Emite tu veredicto: [CERTIFICACIÓN LEGAL APROBADA] o [OBSERVACIÓN: REQUIERE AJUSTES LEGALES].",
                $ws,
                $juris['country_name'],
                $juris['flag'],
                $task['id'] ?? 'HISTORIA',
                $task['title'] ?? '',
                $juris['country_name']
            );
        } else {
            $reviewPrompt = sprintf(
                "Actúa como Scrum Master y QA Lead Auditor (The Gatekeeper).\n" .
                "📂 WORKSPACE: %s\n" .
                "Skills activas: `agile-project-manager`, `webapp-testing`, `context-sentinel`.\n\n" .
                "Auditoría formal de Definition of Done (DoD) para la Historia [%s]: %s.\n\n" .
                "Checklist de Certificación de Sprint:\n" .
                "1. Revisión de código modificado contra el criterio de aceptación:\n   '%s'\n" .
                "2. Verificar que se cumpla la DoD acordada:\n   '%s'\n" .
                "3. Ejecutar lint y certificar 0 errores.\n" .
                "4. Ejecutar la suite completa de pruebas automatizadas y certificar 0 fallos.\n" .
                "Emite tu veredicto: [DoD CERTIFICADA PARA MERGE] o [REABIERTA CON OBSERVACIONES].",
                $ws,
                $task['id'] ?? 'HISTORIA',
                $task['title'] ?? '',
                $task['user_story'] ?? 'Sin user story especificada',
                $task['dod'] ?? 'Suite 100% verde y código limpio'
            );
        }

        echo json_encode(['success' => true, 'review_prompt' => $reviewPrompt]);
        exit;
    }
}
