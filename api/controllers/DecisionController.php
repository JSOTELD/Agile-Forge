<?php
/**
 * AgileForge - DecisionController (ADR)
 * Gestión y registro de Decisiones Arquitectónicas y de Ingeniería.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

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

    public function list(): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM decisions ORDER BY created_at DESC");
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

        $id = !empty($input['id']) ? trim((string)$input['id']) : ('ADR-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT));

        $this->repo->recordDecision($id, $title, $context, $decision, $consequences, $status);

        Bootstrap::jsonResponse([
            'success'  => true,
            'decision' => [
                'id'           => $id,
                'title'        => $title,
                'context'      => $context,
                'decision'     => $decision,
                'consequences' => $consequences,
                'status'       => $status
            ]
        ], 201);
    }
}

