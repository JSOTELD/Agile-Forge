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
use AgileForge\Services\RoleRegistry;
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

    /** Registro de roles del proyecto de la historia (RFC-001): las reglas se deciden por capacidades. */
    private function registryFor(array $task): RoleRegistry
    {
        try {
            $proj = $this->repo->resolveProject($task['project_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            $proj = $this->repo->getActiveProject();
        }
        return RoleRegistry::forProject($proj ?: null);
    }

    /** Roles del tablero con la capacidad, para los mensajes de error. */
    private function rolesWith(RoleRegistry $reg, string $capability): string
    {
        $ids = $reg->allWith($capability, $this->board->roleIds());
        return $ids ? implode(', ', $ids) : 'ninguno';
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

        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::fail('Historia de usuario no encontrada', 404);
        }

        // Bug 5: OTRO PROYECTO (clientes no web no pueden operar historias de otro proyecto)
        if ($client !== 'web') {
            $activeProj = $this->repo->getActiveProject();
            $activeId = (string)($activeProj['id'] ?? 'default');
            if (($task['project_id'] ?? '') !== $activeId) {
                Bootstrap::fail("La historia '{$id}' pertenece al proyecto '{$task['project_id']}', no al proyecto activo '{$activeId}'. Cambia de proyecto o usa el panel web.", 409);
            }
        }

        // Bug 6: REVISION SIN ENTREGA
        if (isset($fields['status']) && (string)$fields['status'] === 'revision' && $client !== 'web') {
            Bootstrap::fail('No se puede mover directamente a revisión sin evidencia: usa submit_for_review con las pruebas ejecutadas.', 409);
        }

        // Mientras una historia esté en "revision" con qa_approved, los clientes que no sean web NO pueden editarla
        if (($task['status'] ?? '') === 'revision' && !empty($task['review_evidence']['qa_approved'])) {
            if ($client !== 'web') {
                Bootstrap::fail('Aprobada por QA: espera la firma humana en el panel', 409);
            }
        }

        // Bug 1: LOCKS (solo el rol dueño del lock vigente o la web pueden editarla)
        $actorRole = trim((string)($input['role'] ?? $input['role_key'] ?? ''));
        if ($client !== 'web' && !empty($task['locked_by'])) {
            $lockedAt = Repository::utcTimestamp($task['locked_at'] ?? null) ?? time();
            if ((time() - $lockedAt) < 1800) {
                if ($actorRole === '' || $actorRole !== $task['locked_by']) {
                    Bootstrap::fail("La historia está bloqueada por el rol: {$task['locked_by']}", 409);
                }
            }
        }

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

        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::fail('Historia de usuario no encontrada', 404);
        }

        // Bug 5: OTRO PROYECTO
        if ($client !== 'web') {
            $activeProj = $this->repo->getActiveProject();
            $activeId = (string)($activeProj['id'] ?? 'default');
            if (($task['project_id'] ?? '') !== $activeId) {
                Bootstrap::fail("La historia '{$id}' pertenece al proyecto '{$task['project_id']}', no al proyecto activo '{$activeId}'. Cambia de proyecto o usa el panel web.", 409);
            }
        }

        // Bug 6: REVISION SIN ENTREGA
        if ($status === 'revision' && $client !== 'web') {
            Bootstrap::fail('No se puede mover directamente a revisión sin evidencia: usa submit_for_review con las pruebas ejecutadas.', 409);
        }

        // ADR-004: "completada" solo la fija un humano desde la web (client: "web") o review_task approve de QA.
        // human_approved ya no se lee de la petición: no es una credencial.
        if ($status === 'completada' && $client !== 'web') {
            Bootstrap::fail(
                'Transición rechazada: pasar a "completada" requiere aprobación humana desde la web (client: "web") '
                . 'o review_task con decision "approve" de un rol con la capacidad can_review_tasks.',
                403
            );
        }

        // Mientras una historia esté en "revision" con qa_approved, los clientes que no sean web NO pueden moverla
        if (($task['status'] ?? '') === 'revision' && !empty($task['review_evidence']['qa_approved'])) {
            if ($client !== 'web') {
                Bootstrap::fail('Aprobada por QA: espera la firma humana en el panel', 409);
            }
            // Si la web la mueve a cualquier estado que no sea "completada", también se borra qa_approved y se registra qa_approval_revoked
            if ($status !== 'completada') {
                $evidence = $task['review_evidence'];
                unset($evidence['qa_approved']);
                $this->repo->updateReviewEvidence($id, $evidence);
                $this->repo->recordEvent($id, $roleKey ?? 'human', 'qa_approval_revoked', [
                    'previous_status' => 'revision',
                    'new_status'      => $status,
                    'reason'          => 'moved_by_web'
                ], 'web');
            }
        }

        // Bug 1: LOCKS (solo el rol dueño del lock vigente o la web pueden moverla)
        if ($client !== 'web' && !empty($task['locked_by'])) {
            $lockedAt = Repository::utcTimestamp($task['locked_at'] ?? null) ?? time();
            if ((time() - $lockedAt) < 1800) {
                if ($roleKey === null || $roleKey === '' || $roleKey !== $task['locked_by']) {
                    Bootstrap::fail("La historia está bloqueada por el rol: {$task['locked_by']}", 409);
                }
            }
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
        } else {
            $memId = 'MEM-TASK-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $id);
            $this->repo->toggleMemory($memId, false);
        }

        Bootstrap::jsonResponse(['success' => true, 'id' => $id, 'status' => $status]);
    }

    public function claim(string $id, string $roleKey, string $client = 'mcp'): void
    {
        $roleKey = trim($roleKey);
        if ($roleKey === '') {
            Bootstrap::jsonResponse(['error' => 'roleKey es obligatorio para tomar una tarea'], 422);
        }

        // Bug 2: ROL INEXISTENTE EN CLAIM (validar ANTES de cualquier otra comprobación)
        if (!$this->repo->roleExists($roleKey)) {
            Bootstrap::fail("El rol '{$roleKey}' no existe", 422);
        }

        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::fail('Historia de usuario no encontrada', 404);
        }

        // Bug 5: OTRO PROYECTO
        if ($client !== 'web') {
            $activeProj = $this->repo->getActiveProject();
            $activeId = (string)($activeProj['id'] ?? 'default');
            if (($task['project_id'] ?? '') !== $activeId) {
                Bootstrap::fail("La historia '{$id}' pertenece al proyecto '{$task['project_id']}', no al proyecto activo '{$activeId}'. Cambia de proyecto o usa el panel web.", 409);
            }
        }

        if (($task['status'] ?? '') === 'revision' && !empty($task['review_evidence']['qa_approved'])) {
            if ($client !== 'web') {
                Bootstrap::fail('Aprobada por QA: espera la firma humana en el panel', 409);
            }
        }

        // RFC-001: tomar historias es una capacidad del rol (el Director orquesta, no ejecuta)
        if ($client !== 'web') {
            $reg = $this->registryFor($task);
            if (!$reg->has($roleKey, 'can_claim_tasks')) {
                Bootstrap::fail("El rol '{$roleKey}' no tiene la capacidad can_claim_tasks: no puede tomar historias (pueden: {$this->rolesWith($reg, 'can_claim_tasks')}).", 403);
            }
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
    public function submitForReview(string $id, array $evidence, string $roleKey, string $client = 'mcp'): void
    {
        $roleKey = trim($roleKey);
        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::jsonResponse(['error' => 'Tarea no encontrada'], 404);
        }

        // Bug 5: OTRO PROYECTO
        if ($client !== 'web') {
            $activeProj = $this->repo->getActiveProject();
            $activeId = (string)($activeProj['id'] ?? 'default');
            if (($task['project_id'] ?? '') !== $activeId) {
                Bootstrap::fail("La historia '{$id}' pertenece al proyecto '{$task['project_id']}', no al proyecto activo '{$activeId}'. Cambia de proyecto o usa el panel web.", 409);
            }
        }

        if ($client !== 'web') {
            $reg = $this->registryFor($task);
            if (!$reg->has($roleKey, 'can_submit_evidence')) {
                Bootstrap::fail("El rol '{$roleKey}' no tiene la capacidad can_submit_evidence: no puede entregar a revisión.", 403);
            }
        }

        $filesChanged = $evidence['files_changed'] ?? [];
        $summary = trim((string)($evidence['summary'] ?? ''));
        $testsRun = (array)($evidence['tests_run'] ?? []);

        if (empty($testsRun)) {
            Bootstrap::fail('Se requiere evidencia de pruebas ejecutadas (evidence.tests_run no puede estar vacío).', 422);
        }

        if (mb_strlen($summary) < 20) {
            Bootstrap::fail('El resumen de entrega (evidence.summary) debe tener al menos 20 caracteres.', 422);
        }

        $evidence['tests_run'] = $testsRun;
        $evidence['summary'] = $summary;

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
     * Revisión de una historia (ADR-004 y RFC-002 revisión independiente).
     * approve exige: estado "revision", revisor qa_security_guard, revisor distinto de quien tiene/tuvo el lock,
     * evidencia previa de entrega, bloque verification y tiempo mínimo de revisión (AGILEFORGE_MIN_REVIEW_SECONDS).
     * Si human_signoff está activo, la historia permanece en "revision" con la marca qa_approved a la espera de la firma en la web.
     * Si human_signoff está inactivo, pasa directo a "completada".
     * request_changes devuelve a "en_progreso" manteniendo el lock del desarrollador.
     */
    public function review(string $id, string $decision, array $checklist, string $reviewerRole, ?array $verification = null): void
    {
        $reviewerRole = trim($reviewerRole);
        if (!in_array($decision, ['approve', 'request_changes'], true)) {
            Bootstrap::fail('Decisión inválida. Debe ser "approve" o "request_changes"', 422);
        }
        if ($reviewerRole === '') {
            Bootstrap::fail('reviewer_role es obligatorio (un rol con la capacidad can_review_tasks, p. ej. qa_security_guard).', 422);
        }

        $task = $this->repo->getTask($id);
        if (!$task) {
            Bootstrap::fail('Tarea no encontrada', 404);
        }
        if (($task['status'] ?? '') !== 'revision') {
            Bootstrap::fail("La historia está en '{$task['status']}': solo se revisa una historia en estado 'revision' (entrégala antes con submit_for_review).", 409);
        }

        // RFC-001: dictaminar (aprobar o pedir cambios) es una capacidad, no un ID fijo
        $reg = $this->registryFor($task);
        if (!$reg->has($reviewerRole, 'can_review_tasks')) {
            Bootstrap::fail("El rol '{$reviewerRole}' no tiene la capacidad can_review_tasks: no puede dictaminar (pueden: {$this->rolesWith($reg, 'can_review_tasks')}).", 403);
        }
        if ($decision === 'approve' && !$reg->has($reviewerRole, 'can_approve_dod')) {
            Bootstrap::fail("El rol '{$reviewerRole}' no tiene la capacidad can_approve_dod: puede pedir cambios pero no aprobar.", 403);
        }

        $humanSignoff = $this->repo->isHumanSignoffEnabled($task['project_id'] ?? null);
        $newStatus = ($decision === 'approve')
            ? ($humanSignoff ? 'revision' : 'completada')
            : 'en_progreso';

        $verificationClean = null;
        $elapsedSeconds = 0;

        if ($decision === 'approve') {
            if (!empty($task['review_evidence']['qa_approved'])) {
                Bootstrap::fail('Ya está aprobada por QA', 409);
            }
            if (in_array($reviewerRole, $this->repo->getLockRoles($id), true)) {
                Bootstrap::fail("El revisor '{$reviewerRole}' tomó o entregó esta historia: no puede aprobar su propio trabajo.", 403);
            }
            if (empty($task['review_evidence'])) {
                Bootstrap::fail('No se puede aprobar sin evidencia registrada previa de entrega (submit_for_review).', 409);
            }

            // Validación de verification (Barrera B.2)
            if (!is_array($verification)) {
                Bootstrap::fail('Se exige el bloque de verificación (verification) para aprobar una historia.', 422);
            }
            $rawCommands = $verification['commands_run'] ?? null;
            if (!is_array($rawCommands) || empty($rawCommands)) {
                Bootstrap::fail('verification.commands_run debe contener al menos un comando ejecutado.', 422);
            }
            $commandsRun = [];
            foreach ($rawCommands as $cmd) {
                if (!is_string($cmd) || trim($cmd) === '') {
                    Bootstrap::fail('verification.commands_run debe contener solo cadenas de texto no vacías.', 422);
                }
                $commandsRun[] = trim($cmd);
            }
            $notes = isset($verification['notes']) && is_string($verification['notes']) ? trim($verification['notes']) : '';
            if (mb_strlen($notes) < 30) {
                Bootstrap::fail('verification.notes debe tener al menos 30 caracteres.', 422);
            }
            $verificationClean = [
                'commands_run' => $commandsRun,
                'notes'        => $notes,
            ];

            // Tiempo mínimo de revisión (Barrera B.3)
            $lastSub = $this->repo->getLastSubmissionEvent($id);
            $elapsedSeconds = isset($lastSub['elapsed_seconds']) ? (int)$lastSub['elapsed_seconds'] : 0;
            if (isset($lastSub['created_at'])) {
                $ts = strtotime($lastSub['created_at'] . ' UTC');
                if ($ts !== false) {
                    $phpElapsed = time() - $ts;
                    if ($phpElapsed > $elapsedSeconds) {
                        $elapsedSeconds = $phpElapsed;
                    }
                }
            }
            $elapsedSeconds = max(0, $elapsedSeconds);

            $rawMinSeconds = getenv('AGILEFORGE_MIN_REVIEW_SECONDS');
            if ($rawMinSeconds === false || $rawMinSeconds === null || $rawMinSeconds === '') {
                $rawMinSeconds = Bootstrap::env('AGILEFORGE_MIN_REVIEW_SECONDS', '60');
            }
            $minReviewSeconds = is_numeric($rawMinSeconds) ? (int)$rawMinSeconds : 60;

            if ($elapsedSeconds < $minReviewSeconds) {
                Bootstrap::fail(
                    "Tiempo de revisión insuficiente ({$elapsedSeconds}s transcurridos, mínimo requerido: {$minReviewSeconds}s).",
                    422,
                    ['elapsed_seconds' => $elapsedSeconds, 'min_seconds' => $minReviewSeconds]
                );
            }

            // Validación de la DoD con Git / archivos del workspace (FUERA de la transacción para no bloquear SQLite)
            $filesChanged = (array)($task['review_evidence']['files_changed'] ?? []);
            if (!empty($filesChanged)) {
                try {
                    $proj = $this->repo->resolveProject($task['project_id'] ?? null);
                } catch (\InvalidArgumentException $e) {
                    $proj = $this->repo->getActiveProject();
                }
                $appRoot = dirname(__DIR__, 2);
                $ws = trim((string)($proj['path'] ?? ''));
                $isAbsWs = $ws !== '' && (bool)preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $ws);
                if (!$isAbsWs) {
                    $ws = ($ws === '' || $ws === '.') ? $appRoot : $appRoot . '/' . ltrim($ws, './\\');
                }
                $gitCheck = $this->verifyDoDGitFiles($ws, $filesChanged);
                if (!$gitCheck['ok']) {
                    Bootstrap::fail("Rechazo de DoD: " . $gitCheck['message'], 422);
                }
            }
        }

        $result = Database::transaction(function (PDO $pdo) use ($id, $decision, $checklist, $reviewerRole, $newStatus, $humanSignoff, $verificationClean, $elapsedSeconds, $reg) {
            $task = $this->repo->getTask($id);
            if (!$task) {
                return ['error' => 'Tarea no encontrada', 'code' => 404];
            }
            if (($task['status'] ?? '') !== 'revision') {
                return ['error' => "La historia está en '{$task['status']}': solo se revisa una historia en estado 'revision' (entrégala antes con submit_for_review).", 'code' => 409];
            }
            if ($decision === 'approve') {
                if (!$reg->has($reviewerRole, 'can_review_tasks') || !$reg->has($reviewerRole, 'can_approve_dod')) {
                    return ['error' => "El rol '{$reviewerRole}' no puede aprobar historias.", 'code' => 403];
                }
                if (in_array($reviewerRole, $this->repo->getLockRoles($id), true)) {
                    return ['error' => "El revisor '{$reviewerRole}' tomó o entregó esta historia: no puede aprobar su propio trabajo.", 'code' => 403];
                }
                if (empty($task['review_evidence'])) {
                    return ['error' => 'No se puede aprobar sin evidencia registrada previa de entrega (submit_for_review).', 'code' => 409];
                }

                $evidence = is_array($task['review_evidence'] ?? null)
                    ? $task['review_evidence']
                    : (json_decode((string)($task['review_evidence_json'] ?? ''), true) ?: []);
                $evidence['verification'] = $verificationClean;
                $evidence['review_duration_seconds'] = $elapsedSeconds;
                $evidence['qa_approved'] = [
                    'reviewer'                => $reviewerRole,
                    'approved_at'             => date('c'),
                    'checklist'               => $checklist,
                    'verification'            => $verificationClean,
                    'review_duration_seconds' => $elapsedSeconds,
                ];

                $evidenceJson = json_encode($evidence, JSON_UNESCAPED_UNICODE);

                if ($humanSignoff) {
                    // Firma humana activa: permanece en revisión, se libera el lock, se marca qa_approved
                    $pdo->prepare("UPDATE tasks SET status = 'revision', locked_by = NULL, locked_at = NULL, review_evidence_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$evidenceJson, $id]);

                    $payload = [
                        'decision'                => 'approve',
                        'checklist'               => $checklist,
                        'verification'            => $verificationClean,
                        'reviewer'                => $reviewerRole,
                        'review_duration_seconds' => $elapsedSeconds,
                        'qa_approved'             => true,
                    ];
                    $this->repo->recordEvent($id, $reviewerRole, 'qa_approved', $payload, 'mcp');

                    $comment = "✅ Aprobada por {$reviewerRole} ({$elapsedSeconds}s). Falta firma humana en el panel web.";
                    $this->repo->addComment($id, $reviewerRole, $reviewerRole, '🔍', $comment);
                } else {
                    // Firma humana inactiva: pasa a completada directamente
                    $pdo->prepare("UPDATE tasks SET status = 'completada', locked_by = NULL, locked_at = NULL, review_evidence_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$evidenceJson, $id]);

                    $payload = [
                        'decision'                => 'approve',
                        'checklist'               => $checklist,
                        'verification'            => $verificationClean,
                        'reviewer'                => $reviewerRole,
                        'status'                  => 'completada',
                        'review_duration_seconds' => $elapsedSeconds,
                    ];
                    $this->repo->recordEvent($id, $reviewerRole, 'task_reviewed', $payload, 'mcp');

                    $comment = "✅ Tarea aprobada por {$reviewerRole} ({$elapsedSeconds}s). Cumplimiento DoD verificado.";
                    $this->repo->addComment($id, $reviewerRole, $reviewerRole, '🔍', $comment);
                }
            } else {
                // Cambios solicitados: vuelve a desarrollo y el lock del desarrollador se mantiene (se renueva)
                $hadQaApproved = !empty($task['review_evidence']['qa_approved']);
                $evidence = is_array($task['review_evidence'] ?? null)
                    ? $task['review_evidence']
                    : (json_decode((string)($task['review_evidence_json'] ?? ''), true) ?: []);

                if ($hadQaApproved) {
                    unset($evidence['qa_approved']);
                }
                $evidenceJson = json_encode($evidence, JSON_UNESCAPED_UNICODE);

                $pdo->prepare("UPDATE tasks SET status = 'en_progreso', review_evidence_json = ?, locked_at = CASE WHEN locked_by IS NOT NULL AND locked_by != '' THEN CURRENT_TIMESTAMP ELSE locked_at END, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$evidenceJson, $id]);

                $payload = ['decision' => $decision, 'checklist' => $checklist, 'reviewer' => $reviewerRole, 'status' => 'en_progreso'];
                $this->repo->recordEvent($id, $reviewerRole, 'task_reviewed', $payload, 'mcp');

                if ($hadQaApproved) {
                    $this->repo->recordEvent($id, $reviewerRole, 'qa_approval_revoked', [
                        'reason'   => 'request_changes',
                        'reviewer' => $reviewerRole,
                    ], 'mcp');
                }

                $comment = "⚠️ Cambios solicitados por {$reviewerRole}. Se regresa a desarrollo.";
                $this->repo->addComment($id, $reviewerRole, $reviewerRole, '🔍', $comment);
            }
            return ['ok' => true];
        });

        if (isset($result['error'])) {
            Bootstrap::fail($result['error'], (int)$result['code']);
        }

        if ($decision === 'approve') {
            if (!$humanSignoff) {
                $approvedTask = $this->repo->getTask($id);
                if ($approvedTask) {
                    $this->consolidateTaskCompletionMemory($approvedTask);
                }
            }
        } else {
            $memId = 'MEM-TASK-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $id);
            $this->repo->toggleMemory($memId, false);
        }

        Bootstrap::jsonResponse([
            'success'               => true,
            'task_id'               => $id,
            'status'                => $newStatus,
            'decision'              => $decision,
            'qa_approved'           => ($decision === 'approve'),
            'human_signoff_pending' => ($decision === 'approve' && $humanSignoff),
        ]);
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

        $gitAvailable = false;
        $gitDiag = '';
        if ($isGit) {
            if (!function_exists('exec')) {
                $gitDiag = 'la función exec() está deshabilitada en PHP';
            } else {
                $testOut = [];
                $testCode = 0;
                @exec('git --version 2>&1', $testOut, $testCode);
                if ($testCode !== 0) {
                    $gitDiag = 'git no está disponible en el PATH del servidor web (código ' . $testCode . ')';
                } else {
                    $gitAvailable = true;
                }
            }
        }

        $gitChangedFiles = [];
        if ($gitAvailable) {
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
            $msg = 'Los siguientes archivos de la evidencia no existen en el workspace: ' . implode(', ', array_slice($missing, 0, 5));
            if ($isGit && !$gitAvailable) {
                $msg .= ". Además, {$gitDiag}, por lo que no se pudo verificar si existían en Git.";
            } elseif ($isGit) {
                $msg .= ' ni aparecen en el diff/log de Git.';
            }
            return [
                'ok'      => false,
                'missing' => $missing,
                'message' => $msg
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
            $roleId = trim((string)($task['assigned_to'] ?? '')) ?: 'all';
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
            $memId = 'MEM-TASK-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$task['id']);

            $this->repo->saveMemory([
                'id'               => $memId,
                'project_id'       => $projectId,
                'role_id'          => $roleId,
                'node_type'        => 'progress',
                'title'            => $title,
                'content_text'     => $content,
                'importance_score' => $importance,
                'is_active'        => 1,
            ], true); // nodo propio del sistema: sigue a la historia si cambió de proyecto
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

        $client = (string)($input['client'] ?? 'api');

        // Bug 5: OTRO PROYECTO
        if ($client !== 'web') {
            $activeProj = $this->repo->getActiveProject();
            $activeId = (string)($activeProj['id'] ?? 'default');
            if (($task['project_id'] ?? '') !== $activeId) {
                Bootstrap::fail("La historia '{$taskId}' pertenece al proyecto '{$task['project_id']}', no al proyecto activo '{$activeId}'. Cambia de proyecto o usa el panel web.", 409);
            }
        }

        $content = trim((string)($input['content'] ?? $input['message'] ?? ''));
        if ($content === '') {
            Bootstrap::jsonResponse(['error' => 'Contenido del comentario vacío'], 422);
        }

        // Bug 7: comentario <= 5000
        if (mb_strlen($content) > 5000) {
            Bootstrap::fail('El contenido del comentario no puede superar los 5000 caracteres', 422);
        }

        $role = trim((string)($input['role'] ?? ''));
        if ($role === '') {
            // Sin rol indicado: comenta quien facilita el sprint
            $role = $this->registryFor($task)->firstWith('can_facilitate_sprint', $this->board->roleIds(), 'project_manager') ?? 'project_manager';
        }
        // Bug 7: rol existente
        if (!$this->repo->roleExists($role)) {
            Bootstrap::fail("El rol '{$role}' no existe", 422);
        }

        $name = trim((string)($input['name'] ?? $role));
        $avatar = trim((string)($input['avatar'] ?? '💬'));

        $commentId = $this->repo->addComment($taskId, $role, $name, $avatar, $content);
        $this->repo->recordEvent($taskId, $role, 'comment_added', ['comment_id' => $commentId], $client);

        Bootstrap::jsonResponse(['success' => true, 'comment_id' => $commentId]);
    }
}

