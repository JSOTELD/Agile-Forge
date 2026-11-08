<?php
/**
 * AgileForge - TaskController
 * Controlador para la gestión integral de historias, estados, bloqueos (locks) y revisiones.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use PDO;

class TaskController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function list(array $filters = []): void
    {
        $tasks = $this->repo->getTasks($filters);
        Bootstrap::jsonResponse(['success' => true, 'count' => count($tasks), 'tasks' => $tasks]);
    }

    public function show(string $id): void
    {
        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::jsonResponse(['error' => 'Historia de usuario no encontrada'], 404);
        }
        Bootstrap::jsonResponse(['success' => true, 'task' => $task]);
    }

    public function create(array $input): void
    {
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            Bootstrap::jsonResponse(['error' => 'El título de la tarea es obligatorio'], 422);
        }

        $id = !empty($input['id']) ? trim((string)$input['id']) : ('US-' . (100 + rand(10, 999)));
        $storyPoints = max(1, (int)($input['story_points'] ?? 2));
        $assignedTo = trim((string)($input['assigned_to'] ?? 'project_manager'));
        $priority = in_array($input['priority'] ?? '', ['alta', 'media', 'baja'], true) ? $input['priority'] : 'media';
        $status = in_array($input['status'] ?? '', ['backlog', 'en_progreso', 'revision', 'completada'], true) ? $input['status'] : 'backlog';
        $userStory = trim((string)($input['user_story'] ?? ''));
        $dod = trim((string)($input['dod'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $skills = is_array($input['skills'] ?? null) ? $input['skills'] : ['lean-compact'];
        $prompt = trim((string)($input['prompt_template'] ?? ''));

        Database::transaction(function (PDO $pdo) use ($id, $title, $storyPoints, $assignedTo, $priority, $status, $userStory, $dod, $description, $skills, $prompt) {
            $stmt = $pdo->prepare(
                "INSERT INTO tasks (id, project_id, title, story_points, assigned_to, priority, status, user_story, dod, description, skills_json, prompt_template)
                 VALUES (?, 'default', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $id, $title, $storyPoints, $assignedTo, $priority, $status, $userStory, $dod, $description,
                json_encode($skills, JSON_UNESCAPED_UNICODE), $prompt
            ]);

            $eStmt = $pdo->prepare("INSERT INTO task_events (task_id, role_key, client, event_type, payload_json) VALUES (?, ?, 'api', 'task_created', ?)");
            $eStmt->execute([$id, $assignedTo, json_encode(['title' => $title, 'status' => $status], JSON_UNESCAPED_UNICODE)]);
        });

        $created = $this->repo->getTask($id);
        Bootstrap::jsonResponse(['success' => true, 'task' => $created], 201);
    }

    public function updateStatus(string $id, string $status, ?string $note = null, ?string $roleKey = null, ?string $client = 'web'): void
    {
        $validStatuses = ['backlog', 'en_progreso', 'revision', 'completada'];
        if (!in_array($status, $validStatuses, true)) {
            Bootstrap::jsonResponse([
                'error'          => "Estado inválido: '{$status}'.",
                'valid_statuses' => $validStatuses
            ], 422);
        }

        // Regla: Pasar a completada solo desde la web o con flag explícito de aprobación humana
        if ($status === 'completada' && $client !== 'web' && empty($_REQUEST['human_approved'])) {
            Bootstrap::jsonResponse([
                'error' => 'Transición rechazada: Pasar a "completada" requiere aprobación humana desde la web.'
            ], 403);
        }

        $ok = $this->repo->updateTaskStatus($id, $status, $note, $roleKey, $client);
        if (!$ok) {
            Bootstrap::jsonResponse(['error' => 'No se pudo actualizar el estado de la tarea'], 500);
        }

        Bootstrap::jsonResponse(['success' => true, 'id' => $id, 'status' => $status]);
    }

    public function claim(string $id, string $roleKey, string $client = 'mcp'): void
    {
        if (trim($roleKey) === '') {
            Bootstrap::jsonResponse(['error' => 'roleKey es obligatorio para tomar una tarea'], 422);
        }

        $res = $this->repo->claimTask($id, $roleKey, $client);
        if (!$res['success']) {
            Bootstrap::jsonResponse(['error' => $res['error']], $res['code'] ?? 400);
        }

        Bootstrap::jsonResponse($res);
    }

    public function submitForReview(string $id, array $evidence, string $roleKey): void
    {
        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::jsonResponse(['error' => 'Tarea no encontrada'], 404);
        }

        $filesChanged = $evidence['files_changed'] ?? [];
        $testsRun = $evidence['tests_run'] ?? [];
        $summary = trim((string)($evidence['summary'] ?? ''));

        if (empty($filesChanged) && empty($summary)) {
            Bootstrap::jsonResponse(['error' => 'Se requiere evidencia de cambios (archivos modificados o resumen de entrega)'], 422);
        }

        Database::transaction(function (PDO $pdo) use ($id, $evidence, $roleKey, $summary) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'revision', review_evidence_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([json_encode($evidence, JSON_UNESCAPED_UNICODE), $id]);

            $this->repo->recordEvent($id, $roleKey, 'submitted_for_review', $evidence, 'mcp');
            $this->repo->addComment($id, $roleKey, $roleKey, '📤', "Entregado a Revisión: {$summary}");
        });

        Bootstrap::jsonResponse(['success' => true, 'task_id' => $id, 'status' => 'revision']);
    }

    public function review(string $id, string $decision, array $checklist, string $reviewerRole): void
    {
        $validDecisions = ['approve', 'request_changes'];
        if (!in_array($decision, $validDecisions, true)) {
            Bootstrap::jsonResponse(['error' => 'Decisión inválida. Debe ser "approve" o "request_changes"'], 422);
        }

        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::jsonResponse(['error' => 'Tarea no encontrada'], 404);
        }

        if ($decision === 'approve' && empty($task['review_evidence'])) {
            Bootstrap::jsonResponse(['error' => 'No se puede aprobar sin evidencia registrada previa de entrega'], 422);
        }

        $newStatus = ($decision === 'approve') ? 'completada' : 'en_progreso';

        Database::transaction(function (PDO $pdo) use ($id, $newStatus, $decision, $checklist, $reviewerRole) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $payload = ['decision' => $decision, 'checklist' => $checklist, 'reviewer' => $reviewerRole];
            $this->repo->recordEvent($id, $reviewerRole, 'task_reviewed', $payload, 'mcp');

            $comment = ($decision === 'approve')
                ? "✅ Tarea aprobada por {$reviewerRole}. Cumplimiento DoD verificado."
                : "⚠️ Cambios solicitados por {$reviewerRole}. Se regresa a desarrollo.";
            $this->repo->addComment($id, $reviewerRole, $reviewerRole, '🔍', $comment);
        });

        Bootstrap::jsonResponse(['success' => true, 'task_id' => $id, 'status' => $newStatus, 'decision' => $decision]);
    }

    public function addComment(string $taskId, array $input): void
    {
        $task = $this->repo->getTask($taskId);
        if (!$task) {
            Bootstrap::jsonResponse(['error' => 'Tarea no encontrada'], 404);
        }

        $content = trim((string)($input['content'] ?? $input['message'] ?? ''));
        if ($content === '') {
            Bootstrap::jsonResponse(['error' => 'Contenido del comentario vacío'], 422);
        }

        $role = trim((string)($input['role'] ?? 'project_manager'));
        $name = trim((string)($input['name'] ?? $role));
        $avatar = trim((string)($input['avatar'] ?? '💬'));

        $commentId = $this->repo->addComment($taskId, $role, $name, $avatar, $content);
        $this->repo->recordEvent($taskId, $role, 'comment_added', ['comment_id' => $commentId], 'api');

        Bootstrap::jsonResponse(['success' => true, 'comment_id' => $commentId]);
    }
}

