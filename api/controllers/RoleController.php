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
use AgileForge\Services\EngineService;
use AgileForge\Services\LlmService;
use PDO;

require_once dirname(__DIR__) . '/services/EngineService.php';

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
        $stmt->closeCursor();

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

        // Solo skills que existen en el catálogo (se descartan IDs inventados o mal escritos)
        $cleanSkills = (new Repository())->cleanSkillIds($skills);

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

    /**
     * Asigna el motor de IA de un rol: proveedor de API + modelo, "local" (la IA local activa)
     * o vacío para heredar el motor global.
     */
    public function updateEngine(array $input): void
    {
        $roleId = trim((string)($input['role_id'] ?? ''));
        $provider = trim((string)($input['ai_provider'] ?? ''));
        $model = trim((string)($input['ai_model'] ?? ''));

        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        if (!$stmt->fetch()) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Rol no encontrado'], 404);
        }

        $llm = new LlmService();
        if ($provider === '' || $provider === 'local') {
            $model = $provider === 'local' ? 'IA local' : '';
        } elseif (!$llm->providerConfig($provider)) {
            Bootstrap::jsonResponse(['success' => false, 'error' => "Proveedor desconocido: {$provider}"], 422);
        } else {
            if (!$llm->isKnownModel($provider, $model)) {
                Bootstrap::jsonResponse(['success' => false, 'error' => "El modelo '{$model}' no pertenece a " . $llm->providerName($provider)], 422);
            }
        }

        $pdo->prepare("UPDATE roles SET ai_provider = ?, ai_model = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$provider === '' ? null : $provider, $model === '' ? null : $model, $roleId]);

        $engines = new EngineService($this->repo);
        $role = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $role->execute([$roleId]);
        Bootstrap::jsonResponse(['success' => true, 'role_id' => $roleId, 'engine' => $engines->resolveForRole($role->fetch())]);
    }

    public function getBrief(string $roleId): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();
        $stmt->closeCursor();

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

        $rawMemories = $this->repo->getMemories(null, $roleId, null, true, 5);
        $compactMemories = array_map(static function (array $m): array {
            return [
                'id'         => $m['id'],
                'node_type'  => $m['node_type'],
                'title'      => $m['title'],
                // Columnas reales de agent_memories: content_text / importance_score (recortado para el brief)
                'content'    => mb_strimwidth((string)($m['content_text'] ?? ''), 0, 400, '…'),
                'importance' => (int)($m['importance_score'] ?? 0),
            ];
        }, $rawMemories);

        $systemPrompt = "Eres {$role['name']} ({$role['alias']}) en AgileForge. Tu especialidad es {$role['specialty']}. Opera bajo la normativa de {$jurisdiction['country_name']} {$jurisdiction['flag']}.";
        if (!empty($compactMemories)) {
            $systemPrompt .= "\n\nMemoria contextual previa del rol en este proyecto:\n";
            foreach ($compactMemories as $cm) {
                $systemPrompt .= "- [{$cm['node_type']}] {$cm['title']}: {$cm['content']}\n";
            }
        }

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
            'memories'           => $compactMemories,
            'system_prompt'      => $systemPrompt,
        ];

        Bootstrap::jsonResponse(['success' => true, 'brief' => $brief]);
    }
}

