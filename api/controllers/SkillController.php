<?php
/**
 * AgileForge - SkillController
 * Gestión del Catálogo de Skills e incorporación de nuevas habilidades personalizadas.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use PDO;

class SkillController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function list(): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM skills ORDER BY name ASC");
        $skills = [];
        while ($row = $stmt->fetch()) {
            $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
            $skills[] = $row;
        }
        Bootstrap::jsonResponse(['success' => true, 'count' => count($skills), 'skills' => $skills]);
    }

    public function create(array $input): void
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            Bootstrap::jsonResponse(['error' => 'El nombre de la habilidad es obligatorio'], 422);
        }

        $id = !empty($input['id']) ? trim((string)$input['id']) : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $level = trim((string)($input['level'] ?? 'Principal'));
        $category = trim((string)($input['category'] ?? 'Custom'));
        $desc = trim((string)($input['desc'] ?? $input['description'] ?? ''));
        $tags = is_array($input['tags'] ?? null) ? $input['tags'] : ['Custom', 'Agent'];

        $pdo = Database::getPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO skills (id, name, level, category, description, tags_json, is_custom)
             VALUES (?, ?, ?, ?, ?, ?, 1)
             ON CONFLICT(id) DO UPDATE SET
                name = excluded.name, level = excluded.level, category = excluded.category,
                description = excluded.description, tags_json = excluded.tags_json"
        );
        $stmt->execute([$id, $name, $level, $category, $desc, json_encode($tags, JSON_UNESCAPED_UNICODE)]);

        Bootstrap::jsonResponse([
            'success' => true,
            'skill'   => [
                'id'        => $id,
                'name'      => $name,
                'level'     => $level,
                'category'  => $category,
                'desc'      => $desc,
                'tags'      => $tags,
                'is_custom' => true
            ]
        ], 201);
    }
}

