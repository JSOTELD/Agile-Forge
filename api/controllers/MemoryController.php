<?php
/**
 * AgileForge - MemoryController
 * Gestión de la memoria nodular interna de agentes por proyecto y rol.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

use AgileForge\Bootstrap;
use AgileForge\Repository;

class MemoryController
{
    private Repository $repo;

    public function __construct(?Repository $repo = null)
    {
        $this->repo = $repo ?? new Repository();
    }

    public function list(array $query = []): void
    {
        $projectId = isset($query['project']) && $query['project'] !== ''
            ? (string)$query['project']
            : (isset($query['project_id']) && $query['project_id'] !== '' ? (string)$query['project_id'] : null);

        // Sin project -> proyecto ACTIVO
        if ($projectId === null || $projectId === '') {
            $active = $this->repo->getActiveProject();
            $projectId = (string)($active['id'] ?? 'default');
        }

        $roleId = isset($query['role']) && $query['role'] !== ''
            ? (string)$query['role']
            : (isset($query['role_id']) && $query['role_id'] !== '' ? (string)$query['role_id'] : null);

        $nodeType = isset($query['type']) && $query['type'] !== ''
            ? (string)$query['type']
            : (isset($query['node_type']) && $query['node_type'] !== '' ? (string)$query['node_type'] : null);

        $activeOnly = !empty($query['active_only']);
        $limit = isset($query['limit']) && is_numeric($query['limit']) ? max(1, min(200, (int)$query['limit'])) : 50;

        $memories = $this->repo->getMemories($projectId, $roleId, $nodeType, $activeOnly, $limit);

        Bootstrap::jsonResponse([
            'success'  => true,
            'count'    => count($memories),
            'memories' => $memories
        ]);
    }

    public function show(string $id): void
    {
        $mem = $this->repo->getMemory($id);
        if (!$mem) {
            Bootstrap::fail('Nodo de memoria no encontrado', 404);
        }
        Bootstrap::jsonResponse([
            'success' => true,
            'memory'  => $mem
        ]);
    }

    public function save(array $input): void
    {
        try {
            $mem = $this->repo->saveMemory($input);
            Bootstrap::jsonResponse([
                'success' => true,
                'memory'  => $mem
            ]);
        } catch (\InvalidArgumentException $e) {
            Bootstrap::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Bootstrap::fail('Error al guardar nodo de memoria: ' . $e->getMessage(), 500);
        }
    }

    public function toggle(string $id, array $input = []): void
    {
        $active = array_key_exists('is_active', $input) ? $input['is_active'] : null;
        $mem = $this->repo->toggleMemory($id, $active);
        if (!$mem) {
            Bootstrap::fail('Nodo de memoria no encontrado', 404);
        }
        Bootstrap::jsonResponse([
            'success' => true,
            'memory'  => $mem
        ]);
    }

    public function delete(string $id): void
    {
        $ok = $this->repo->deleteMemory($id);
        if (!$ok) {
            Bootstrap::fail('Nodo de memoria no encontrado o ya eliminado', 404);
        }
        Bootstrap::jsonResponse([
            'success' => true,
            'deleted' => $id
        ]);
    }
}
