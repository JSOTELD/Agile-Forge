<?php
/**
 * AgileForge - DecisionController (ADR)
 * Gestión y registro de Decisiones Arquitectónicas y de Ingeniería.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/BoardService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use PDO;

class DecisionController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function list(array $query = []): void
    {
        $limit = isset($query['limit']) && is_numeric($query['limit']) ? max(1, min(200, (int)$query['limit'])) : null;
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM decisions ORDER BY created_at DESC, rowid DESC" . ($limit ? " LIMIT {$limit}" : ''));
        $decisions = $stmt->fetchAll();
        Bootstrap::jsonResponse(['success' => true, 'count' => count($decisions), 'decisions' => $decisions]);
    }

    public function record(array $input): void
    {
        $title = trim((string)($input['title'] ?? ''));
        $context = trim((string)($input['context'] ?? ''));
        $decision = trim((string)($input['decision'] ?? ''));
        $consequences = trim((string)($input['consequences'] ?? ''));
        $status = in_array($input['status'] ?? '', ['proposed', 'accepted', 'rejected', 'deprecated'], true) ? $input['status'] : 'accepted';

        if ($title === '' || $decision === '') {
            Bootstrap::jsonResponse(['error' => 'El título y la decisión son obligatorios para un ADR'], 422);
        }

        $projectId = isset($input['project_id']) ? trim((string)$input['project_id']) : '';
        if ($projectId !== '' && !$this->repo->projectExists($projectId)) {
            Bootstrap::fail("El proyecto '{$projectId}' no existe", 422);
        }

        if (!empty($input['id'])) {
            $id = trim((string)$input['id']);
            $this->repo->recordDecision($id, $title, $context, $decision, $consequences, $status, $projectId !== '' ? $projectId : null);
        } else {
            // ID secuencial: un ID aleatorio podía coincidir con uno existente y sobrescribirlo
            $id = (new \AgileForge\Services\BoardService($this->repo))->recordDecision($title, $context, $decision, $consequences, $status, $projectId !== '' ? $projectId : null);
        }

        Bootstrap::jsonResponse([
            'success'  => true,
            'decision' => [
                'id'           => $id,
                'title'        => $title,
                'context'      => $context,
                'decision'     => $decision,
                'consequences' => $consequences,
                'status'       => $status,
                'project_id'   => $projectId !== '' ? $projectId : (string)($this->repo->getActiveProject()['id'] ?? 'default')
            ]
        ], 201);
    }
}

