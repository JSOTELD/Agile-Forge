<?php
/**
 * API REST Local para AgileForge · Open-to-code
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

    // 1. Crear nueva tarea y autogenerar prompt
    if ($action === 'create_task') {
        $title = trim((string)($input['title'] ?? ''));
        $roleId = trim((string)($input['assigned_to'] ?? 'backend_engineer'));
        $priority = in_array($input['priority'] ?? '', ['baja', 'media', 'alta', 'critica'], true) ? $input['priority'] : 'media';
        $desc = trim((string)($input['description'] ?? ''));

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['error' => 'El título es obligatorio']);
            exit;
        }

        // Buscar el rol
        $roleName = 'Especialista';
        $specialty = 'Desarrollo de Software';
        foreach ($data['roles'] as $r) {
            if ($r['id'] === $roleId) {
                $roleName = $r['name'];
                $specialty = $r['specialty'];
                break;
            }
        }

        // Autogenerador de Prompt de Alta Precisión
        $prompt = sprintf(
            "Actúa como %s de clase mundial especializado en %s.\n" .
            "Tu misión es: %s.\n\n" .
            "Contexto y Detalles:\n%s\n\n" .
            "Criterios de Éxito y Calidad:\n" .
            "- Aplica estándares estrictos de Clean Code, SOLID y modularidad.\n" .
            "- No dejes errores de sintaxis ni archivos rotos a medio terminar.\n" .
            "- Al finalizar, resume los cambios exactos y entrega la validación.",
            $roleName,
            $specialty,
            $title,
            $desc !== '' ? $desc : 'Implementación requerida según las especificaciones del proyecto.'
        );

        $newId = 'TASK-' . str_pad((string)(count($data['tasks']) + 1), 3, '0', STR_PAD_LEFT);
        $newTask = [
            'id'              => $newId,
            'title'           => $title,
            'assigned_to'     => $roleId,
            'priority'        => $priority,
            'status'          => 'pendiente',
            'created_at'      => date('Y-m-d H:i:s'),
            'description'     => $desc,
            'prompt_template' => $prompt,
        ];

        array_unshift($data['tasks'], $newTask);
        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        echo json_encode(['success' => true, 'task' => $newTask]);
        exit;
    }

    // 2. Cambiar estado de tarea
    if ($action === 'update_status') {
        $taskId = (string)($input['task_id'] ?? '');
        $status = (string)($input['status'] ?? '');

        if (!in_array($status, ['pendiente', 'en_progreso', 'revision', 'completada'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Estado no válido']);
            exit;
        }

        $found = false;
        foreach ($data['tasks'] as $i => $t) {
            if ($t['id'] === $taskId) {
                $data['tasks'][$i]['status'] = $status;
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Tarea no encontrada']);
            exit;
        }

        file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. Generar Prompt de Revisión / Auditoría
    if ($action === 'generate_review_prompt') {
        $taskId = (string)($input['task_id'] ?? '');
        $task = null;
        foreach ($data['tasks'] as $t) {
            if ($t['id'] === $taskId) {
                $task = $t;
                break;
            }
        }

        if ($task === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Tarea no encontrada']);
            exit;
        }

        $reviewPrompt = sprintf(
            "Actúa como Auditor de Calidad y Director de Software Senior.\n" .
            "Revisa exhaustivamente la solución entregada para la tarea [%s]: %s.\n\n" .
            "Checklist de Auditoría:\n" .
            "1. Inspecciona los archivos modificados con git diff.\n" .
            "2. Verifica que no existan errores de lint ni advertencias.\n" .
            "3. Ejecuta la suite de pruebas automatizadas y certifica 0 fallos.\n" .
            "4. Evalúa seguridad (OWASP, escape de salidas, inyecciones, rate limiting).\n" .
            "Entrega tu dictamen formal: [APROBADO PARA PRODUCCIÓN] o [RECHAZADO CON OBSERVACIONES].",
            $task['id'],
            $task['title']
        );

        echo json_encode(['success' => true, 'review_prompt' => $reviewPrompt]);
        exit;
    }
}
