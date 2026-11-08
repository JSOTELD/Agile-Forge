<?php
/**
 * AgileForge - RoleController
 * Gestión de agentes, especialidades, habilidades asignadas y brief de sistema.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use PDO;

class RoleController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function list(): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
        $roles = [];
        while ($row = $stmt->fetch()) {
            $row['assigned_skills'] = json_decode($row['assigned_skills_json'] ?? '[]', true);
            $roles[] = $row;
        }
        Bootstrap::jsonResponse(['success' => true, 'count' => count($roles), 'roles' => $roles]);
    }

    public function show(string $id): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();

        if (!$role) {
            Bootstrap::jsonResponse(['error' => 'Rol no encontrado'], 404);
        }

        $role['assigned_skills'] = json_decode($role['assigned_skills_json'] ?? '[]', true);
        Bootstrap::jsonResponse(['success' => true, 'role' => $role]);
    }

    public function updateSkills(string $roleId, array $skills): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        if (!$stmt->fetch()) {
            Bootstrap::jsonResponse(['error' => 'Rol no encontrado'], 404);
        }

        $cleanSkills = array_values(array_unique(array_filter(array_map('trim', $skills))));

        Database::transaction(function (PDO $pdo) use ($roleId, $cleanSkills) {
            $uStmt = $pdo->prepare("UPDATE roles SET assigned_skills_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $uStmt->execute([json_encode($cleanSkills, JSON_UNESCAPED_UNICODE), $roleId]);

            $pdo->prepare("DELETE FROM role_skills WHERE role_id = ?")->execute([$roleId]);
            $rsStmt = $pdo->prepare("INSERT OR IGNORE INTO role_skills (role_id, skill_id) VALUES (?, ?)");
            foreach ($cleanSkills as $sId) {
                $rsStmt->execute([$roleId, $sId]);
            }
        });

        Bootstrap::jsonResponse(['success' => true, 'role_id' => $roleId, 'skills' => $cleanSkills]);
    }

    public function getBrief(string $roleId): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();

        if (!$role) {
            Bootstrap::jsonResponse(['error' => 'Rol no encontrado'], 404);
        }

        $assignedSkillIds = json_decode($role['assigned_skills_json'] ?? '[]', true) ?: [];
        $skillsInfo = [];

        if (!empty($assignedSkillIds)) {
            $placeholders = implode(',', array_fill(0, count($assignedSkillIds), '?'));
            $sStmt = $pdo->prepare("SELECT * FROM skills WHERE id IN ($placeholders)");
            $sStmt->execute($assignedSkillIds);
            while ($sRow = $sStmt->fetch()) {
                $sRow['tags'] = json_decode($sRow['tags_json'] ?? '[]', true);
                $skillsInfo[] = $sRow;
            }
        }

        $jurisdiction = $this->repo->getSetting('jurisdiction', [
            'country_code' => 'PE',
            'country_name' => 'Perú',
            'flag'         => '🇵🇪',
            'regulations'  => ['Ley 29733 (Protección de Datos)']
        ]);

        $brief = [
            'role_id'            => $role['id'],
            'name'               => $role['name'],
            'alias'              => $role['alias'],
            'avatar'             => $role['avatar'],
            'specialty'          => $role['specialty'],
            'ai_model'           => $role['ai_model'],
            'ai_provider'        => $role['ai_provider'],
            'description'        => $role['description'],
            'active_skills'      => $skillsInfo,
            'legal_jurisdiction' => $jurisdiction,
            'system_prompt'      => "Eres {$role['name']} ({$role['alias']}) en AgileForge. Tu especialidad es {$role['specialty']}. Opera bajo la normativa de {$jurisdiction['country_name']} {$jurisdiction['flag']}."
        ];

        Bootstrap::jsonResponse(['success' => true, 'brief' => $brief]);
    }
}

