<?php
/**
 * AgileForge - SprintController
 * Métricas en vivo del Sprint, velocidad, puntos por columna y bloqueos.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/LlmService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Services\LlmService;

class SprintController
{
    private Repository $repo;
    private LlmService $llm;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->llm = new LlmService();
    }

    public function getStatus(): void
    {
        $state = $this->repo->getFullState();
        $sprint = $state['sprint'];
        $tasks = $state['tasks'];

        $pointsByStatus = [
            'backlog'     => 0,
            'en_progreso' => 0,
            'revision'    => 0,
            'completada'  => 0,
        ];

        $countsByStatus = [
            'backlog'     => 0,
            'en_progreso' => 0,
            'revision'    => 0,
            'completada'  => 0,
        ];

        $blockedTasks = [];
        $totalPoints = 0;

        foreach ($tasks as $t) {
            $st = $t['status'] ?? 'backlog';
            $pts = (int)($t['story_points'] ?? 1);
            $totalPoints += $pts;

            if (isset($pointsByStatus[$st])) {
                $pointsByStatus[$st] += $pts;
                $countsByStatus[$st] += 1;
            }

            if (!empty($t['locked_by'])) {
                $blockedTasks[] = [
                    'id'        => $t['id'],
                    'title'     => $t['title'],
                    'locked_by' => $t['locked_by'],
                    'locked_at' => $t['locked_at']
                ];
            }
        }

        $completedPoints = $pointsByStatus['completada'];
        $progressPct = $totalPoints > 0 ? (int)round(($completedPoints / $totalPoints) * 100) : 0;

        Bootstrap::jsonResponse([
            'success'            => true,
            'sprint'             => $sprint,
            'total_points'       => $totalPoints,
            'completed_points'   => $completedPoints,
            'progress_percent'   => $progressPct,
            'points_by_status'   => $pointsByStatus,
            'counts_by_status'   => $countsByStatus,
            'blocked_tasks'      => $blockedTasks,
            'total_tasks'        => count($tasks),
            'costs_summary'      => $this->llm->getUsageSummary()
        ]);
    }
}

