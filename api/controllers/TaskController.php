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
use AgileForge\Services\BoardService;
use PDO;

require_once dirname(__DIR__) . '/services/BoardService.php';

class TaskController
{
    private Repository $repo;
    private BoardService $board;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->board = new BoardService($this->repo);
    }

    public function list(array $filters = []): void
    {
        if (!empty($filters['compact']) && $filters['compact'] !== '0' && $filters['compact'] !== 'false') {
            try {
                $tasks = $this->repo->getCompactTasks(isset($filters['project']) ? (string)$filters['project'] : null, $filters, 200);
            } catch (\InvalidArgumentException $e) {
                Bootstrap::fail($e->getMessage(), 404);
            }
            Bootstrap::jsonResponse(['success' => true, 'count' => count($tasks), 'tasks' => $tasks], 200, false);
        }
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
        try {
            $client = (string)($input['client'] ?? '') !== '' ? (string)$input['client'] : 'api';
            $task = $this->board->createTask($input, $client, isset($input['created_by']) ? (string)$input['created_by'] : null);
        } catch (\InvalidArgumentException $e) {
            Bootstrap::fail($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 409;
            Bootstrap::fail($e->getMessage(), $code);
        }
        Bootstrap::jsonResponse(['success' => true, 'task' => $task], 201);
    }

    /** Edita una historia (título, agente, prioridad, puntos, textos o estado). */
    public function update(array $input): void
    {
        $id = (string)($input['task_id'] ?? $input['id'] ?? '');
        $fields = is_array($input['fields'] ?? null) ? $input['fields'] : $input;
        $client = (string)($input['client'] ?? '') !== '' ? (string)$input['client'] : 'api';
        try {
            $task = $this->board->updateTask($id, $fields, $client, (string)($input['role'] ?? 'user'), $client === 'web');
        } catch (\InvalidArgumentException $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        }
        Bootstrap::jsonResponse(['success' => true, 'task' => $task]);
    }

    public function delete(array $input): void
    {
        $id = (string)($input['task_id'] ?? '');
        if (($input['client'] ?? 'api') !== 'web' || empty($input['confirm'])) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Eliminar una historia requiere confirmación desde el tablero.'], 403);
        }
        try {
            $this->board->deleteTask($id);
        } catch (\InvalidArgumentException $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        }
        Bootstrap::jsonResponse(['success' => true, 'deleted' => $id]);
    }

    public function updateStatus(string $id, string $status, ?string $note = null, ?string $roleKey = null, ?string $client = 'api'): void
    {
        $validStatuses = ['backlog', 'en_progreso', 'revision', 'completada'];
        if (!in_array($status, $validStatuses, true)) {
            Bootstrap::jsonResponse([
                'error'          => "Estado inválido: '{$status}'.",
                'valid_statuses' => $validStatuses
            ], 422);
        }

        // ADR-004: "completada" solo la fija un humano desde la web (client: "web") o review_task approve de QA.
        // human_approved ya no se lee de la petición: no es una credencial.
        if ($status === 'completada' && $client !== 'web') {
            Bootstrap::fail(
                'Transición rechazada: pasar a "completada" requiere aprobación humana desde la web (client: "web") '
                . 'o review_task con decision "approve" del rol qa_security_guard.',
                403
            );
        }

        if (!$this->repo->getTask($id)) {
            Bootstrap::fail('Historia de usuario no encontrada', 404);
        }

        $ok = $this->repo->updateTaskStatus($id, $status, $note, $roleKey, $client);
        if (!$ok) {
            Bootstrap::fail('No se pudo actualizar el estado de la tarea', 500);
        }

        if ($status === 'completada') {
            $completedTask = $this->repo->getTask($id);
            if ($completedTask) {
                $this->consolidateTaskCompletionMemory($completedTask);
            }
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

    /**
     * Entrega a revisión (H6). Exige: historia en "en_progreso" (409 si no) y que quien entrega sea el rol que tiene
     * el lock (403 si no). Todo se comprueba dentro de la transacción para que no haya carreras con claim/review.
     */
    public function submitForReview(string $id, array $evidence, string $roleKey): void
    {
        $roleKey = trim($roleKey);
        if (!$this->repo->getTask($id)) {
            Bootstrap::jsonResponse(['error' => 'Tarea no encontrada'], 404);
        }

        $filesChanged = $evidence['files_changed'] ?? [];
        $summary = trim((string)($evidence['summary'] ?? ''));

        if (empty($filesChanged) && empty($summary)) {
            Bootstrap::jsonResponse(['error' => 'Se requiere evidencia de cambios (archivos modificados o resumen de entrega)'], 422);
        }

        // Generar sello de procedencia de autoría (AI Attribution & IP Provenance)
        $provHash = hash('sha256', "{$id}|{$roleKey}|" . microtime(true) . '|' . json_encode($evidence));
        $evidence['provenance'] = [
            'watermark'         => 'AF-PROV-' . strtoupper(substr($provHash, 0, 12)),
            'provenance_hash'   => $provHash,
            'attributable_role' => $roleKey,
            'certified_at'      => date('c')
        ];

        $result = Database::transaction(function (PDO $pdo) use ($id, $evidence, $roleKey, $summary) {
            $stmt = $pdo->prepare("SELECT status, locked_by FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $stmt->closeCursor();
            if (!$row) {
                return ['error' => 'Tarea no encontrada', 'code' => 404];
            }
            if (($row['status'] ?? '') !== 'en_progreso') {
                return ['error' => "La historia está en '{$row['status']}': solo se entrega a revisión una historia en 'en_progreso' (tómala antes con claim_task).", 'code' => 409];
            }
            $locker = (string)($row['locked_by'] ?? '');
            if ($locker === '' || $locker !== $roleKey) {
                return ['error' => $locker === ''
                    ? "Nadie tiene el lock de esta historia: tómala con claim_task antes de entregarla (rol indicado: '{$roleKey}')."
                    : "Solo el rol que tiene el lock ('{$locker}') puede entregar esta historia (rol indicado: '{$roleKey}').", 'code' => 403];
            }

            $pdo->prepare("UPDATE tasks SET status = 'revision', review_evidence_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([json_encode($evidence, JSON_UNESCAPED_UNICODE), $id]);

            $this->repo->recordEvent($id, $roleKey, 'submitted_for_review', $evidence, 'mcp');
            $this->repo->addComment($id, $roleKey, $roleKey, '📤', "Entregado a Revisión (Sello {$evidence['provenance']['watermark']}): {$summary}");
            return ['ok' => true];
        });

        if (isset($result['error'])) {
            Bootstrap::fail($result['error'], (int)$result['code']);
        }
        Bootstrap::jsonResponse(['success' => true, 'task_id' => $id, 'status' => 'revision']);
    }

    /**
     * Revisión de una historia (ADR-004).
     * approve exige: estado "revision", revisor qa_security_guard, revisor distinto de quien tiene/tuvo el lock y evidencia previa.
     * request_changes devuelve a "en_progreso" manteniendo el lock del desarrollador.
     */
    public function review(string $id, string $decision, array $checklist, string $reviewerRole): void
    {
        $reviewerRole = trim($reviewerRole);
        if (!in_array($decision, ['approve', 'request_changes'], true)) {
            Bootstrap::fail('Decisión inválida. Debe ser "approve" o "request_changes"', 422);
        }
        if ($reviewerRole === '') {
            Bootstrap::fail('reviewer_role es obligatorio (para aprobar: qa_security_guard).', 422);
        }

        $newStatus = ($decision === 'approve') ? 'completada' : 'en_progreso';

        $result = Database::transaction(function (PDO $pdo) use ($id, $decision, $checklist, $reviewerRole, $newStatus) {
            $task = $this->repo->getTask($id);
            if (!$task) {
                return ['error' => 'Tarea no encontrada', 'code' => 404];
            }
            if (($task['status'] ?? '') !== 'revision') {
                return ['error' => "La historia está en '{$task['status']}': solo se revisa una historia en estado 'revision' (entrégala antes con submit_for_review).", 'code' => 409];
            }
            if ($decision === 'approve') {
                if ($reviewerRole !== 'qa_security_guard') {
                    return ['error' => "Solo el rol qa_security_guard puede aprobar una historia (revisor indicado: '{$reviewerRole}').", 'code' => 403];
                }
                if (in_array($reviewerRole, $this->repo->getLockRoles($id), true)) {
                    return ['error' => "El revisor '{$reviewerRole}' tomó o entregó esta historia: no puede aprobar su propio trabajo.", 'code' => 403];
                }
                if (empty($task['review_evidence'])) {
                    return ['error' => 'No se puede aprobar sin evidencia registrada previa de entrega (submit_for_review).', 'code' => 409];
                }

                // Validación de la DoD con Git / archivos del workspace
                $filesChanged = (array)($task['review_evidence']['files_changed'] ?? []);
                if (!empty($filesChanged)) {
                    // Repository no tiene getProject(): resolveProject() devuelve el proyecto de la historia (o el activo)
                    try {
                        $proj = $this->repo->resolveProject($task['project_id'] ?? null);
                    } catch (\InvalidArgumentException $e) {
                        $proj = $this->repo->getActiveProject();
                    }
                    $ws = (string)($proj['path'] ?? dirname(__DIR__, 2));
                    $gitCheck = $this->verifyDoDGitFiles($ws, $filesChanged);
                    if (!$gitCheck['ok']) {
                        return ['error' => "Rechazo de DoD: " . $gitCheck['message'], 'code' => 422];
                    }
                }

                // Aprobada: se libera el lock
                $pdo->prepare("UPDATE tasks SET status = 'completada', locked_by = NULL, locked_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            } else {
                // Cambios solicitados: vuelve a desarrollo y el lock del desarrollador se mantiene (se renueva)
                $pdo->prepare("UPDATE tasks SET status = 'en_progreso', locked_at = CASE WHEN locked_by IS NOT NULL AND locked_by != '' THEN CURRENT_TIMESTAMP ELSE locked_at END, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            }

            $payload = ['decision' => $decision, 'checklist' => $checklist, 'reviewer' => $reviewerRole, 'status' => $newStatus];
            $this->repo->recordEvent($id, $reviewerRole, 'task_reviewed', $payload, 'mcp');

            $comment = ($decision === 'approve')
                ? "✅ Tarea aprobada por {$reviewerRole}. Cumplimiento DoD verificado."
                : "⚠️ Cambios solicitados por {$reviewerRole}. Se regresa a desarrollo.";
            $this->repo->addComment($id, $reviewerRole, $reviewerRole, '🔍', $comment);
            return ['ok' => true];
        });

        if (isset($result['error'])) {
            Bootstrap::fail($result['error'], (int)$result['code']);
        }

        if ($decision === 'approve') {
            $approvedTask = $this->repo->getTask($id);
            if ($approvedTask) {
                $this->consolidateTaskCompletionMemory($approvedTask);
            }
        }

        Bootstrap::jsonResponse(['success' => true, 'task_id' => $id, 'status' => $newStatus, 'decision' => $decision]);
    }

    /**
     * Valida que los archivos de la evidencia de entrega existan en disco o en el historial/status de Git.
     */
    private function verifyDoDGitFiles(string $workspacePath, array $files): array
    {
        if (empty($files)) {
            return ['ok' => true];
        }

        $cleanWs = rtrim(str_replace('\\', '/', $workspacePath), '/');
        $gitDir = $cleanWs . '/.git';
        $isGit = is_dir($gitDir);

        $gitChangedFiles = [];
        if ($isGit) {
            $cmd = 'git -C ' . escapeshellarg($cleanWs) . ' diff --name-only HEAD 2>&1';
            $out = [];
            @exec($cmd, $out);
            foreach ($out as $line) {
                $trimmed = trim(str_replace('\\', '/', $line));
                if ($trimmed !== '') {
                    $gitChangedFiles[$trimmed] = true;
                }
            }

            $cmdSt = 'git -C ' . escapeshellarg($cleanWs) . ' status --porcelain 2>&1';
            $outSt = [];
            @exec($cmdSt, $outSt);
            foreach ($outSt as $line) {
                $filePart = trim(substr($line, 3));
                if ($filePart !== '') {
                    $gitChangedFiles[str_replace('\\', '/', $filePart)] = true;
                }
            }

            $cmdLog = 'git -C ' . escapeshellarg($cleanWs) . ' log -n 5 --name-only --pretty=format:"" 2>&1';
            $outLog = [];
            @exec($cmdLog, $outLog);
            foreach ($outLog as $line) {
                $trimmed = trim(str_replace('\\', '/', $line));
                if ($trimmed !== '') {
                    $gitChangedFiles[$trimmed] = true;
                }
            }
        }

        $missing = [];
        foreach ($files as $file) {
            $norm = str_replace('\\', '/', trim((string)$file));
            // Los agentes suelen reportar rutas absolutas: si está dentro del workspace se pasa a relativa;
            // si es absoluta y existe en disco, también vale como evidencia
            if ($norm !== '' && (preg_match('#^[A-Za-z]:/#', $norm) || str_starts_with($norm, '/'))) {
                if (strcasecmp(substr($norm, 0, strlen($cleanWs) + 1), $cleanWs . '/') === 0) {
                    $norm = substr($norm, strlen($cleanWs) + 1);
                } elseif (file_exists($norm)) {
                    continue;
                }
            }
            $norm = ltrim(preg_replace('#^\./#', '', $norm) ?? $norm, '/');
            if ($norm === '') continue;

            $fullPath = $cleanWs . '/' . $norm;
            $existsOnDisk = file_exists($fullPath);
            $existsInGit = isset($gitChangedFiles[$norm]);

            if (!$existsOnDisk && !$existsInGit) {
                $missing[] = $norm;
            }
        }

        if (!empty($missing)) {
            return [
                'ok'      => false,
                'missing' => $missing,
                'message' => 'Los siguientes archivos de la evidencia no existen en el workspace ni aparecen en Git: ' . implode(', ', array_slice($missing, 0, 5))
            ];
        }

        return ['ok' => true];
    }

    /**
     * Auto-consolidación determinista de memoria nodular al completar una historia.
     */
    public function consolidateTaskCompletionMemory(array $task): void
    {
        try {
            $projectId = (string)($task['project_id'] ?? 'default');
            $roleId = (string)($task['assigned_to'] ?? 'developer');
            $evidence = is_array($task['review_evidence'] ?? null)
                ? $task['review_evidence']
                : (json_decode((string)($task['review_evidence_json'] ?? ''), true) ?: []);

            $evidenceSummary = trim((string)($evidence['summary'] ?? ''));
            $title = "Completada: {$task['id']} - " . mb_substr((string)($task['title'] ?? ''), 0, 70);

            $content = $evidenceSummary !== ''
                ? $evidenceSummary
                : "Historia completada exitosamente. DoD: " . (string)($task['dod'] ?? 'Verificada');

            if (!empty($evidence['files_changed'])) {
                $content .= "\nArchivos: " . implode(', ', array_slice((array)$evidence['files_changed'], 0, 6));
            }

            $points = (int)($task['story_points'] ?? 2);
            $importance = min(10, max(5, $points + 3));

            $this->repo->saveMemory([
                'id'               => 'MEM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                'project_id'       => $projectId,
                'role_id'          => $roleId,
                'node_type'        => 'progress',
                'title'            => $title,
                'content_text'     => $content,
                'importance_score' => $importance,
                'is_active'        => 1,
            ]);
        } catch (\Throwable $e) {
            Bootstrap::log("Error consolidando memoria de tarea {$task['id']}: " . $e->getMessage());
        }
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

