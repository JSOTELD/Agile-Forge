<?php
/**
 * AgileForge - ProjectController
 * Selector y Gestor Multi-Proyecto / Workspace.
 * Permite alternar entre proyectos y la importación de repositorios existentes con escaneo de git log.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use PDO;

class ProjectController
{
    private Repository $repo;

    public function __construct()
    {
        $this->repo = new Repository();
    }

    public function list(): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at ASC");
        $projects = $stmt->fetchAll();

        Bootstrap::jsonResponse([
            'success'  => true,
            'projects' => $projects
        ]);
    }

    public function create(array $input): void
    {
        $name = trim((string)($input['name'] ?? ''));
        $rawPath = trim((string)($input['path'] ?? ''));

        if ($name === '') {
            Bootstrap::jsonResponse(['error' => 'El nombre del proyecto es obligatorio'], 422);
        }

        try {
            $cleanPath = Bootstrap::validateWorkspacePath($rawPath !== '' ? $rawPath : dirname(__DIR__, 2));
        } catch (\InvalidArgumentException $e) {
            Bootstrap::jsonResponse(['error' => $e->getMessage()], 422);
            return;
        }

        $id = strtolower(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $name));
        if ($id === '' || $id === '-') {
            $id = 'proj-' . time();
        }

        $pdo = Database::getPdo();

        // Verificar colisión de ID
        $chk = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE id = ?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            $id .= '-' . rand(10, 99);
        }

        Database::transaction(function (PDO $pdo) use ($id, $name, $cleanPath) {
            // Desactivar otros proyectos
            $pdo->exec("UPDATE projects SET active = 0");

            $stmt = $pdo->prepare("INSERT INTO projects (id, name, path, active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$id, $name, $cleanPath]);

            // Crear Sprint 1 inicial para este proyecto
            $sprintStmt = $pdo->prepare(
                "INSERT INTO sprints (id, project_id, number, name, goal, days_remaining, velocity_points, status)
                 VALUES (?, ?, 1, ?, ?, 10, 20, 'active')"
            );
            $sprintId = "SPRINT-{$id}-01";
            $sprintStmt->execute([
                $sprintId,
                $id,
                "Sprint 1: Inicialización de {$name}",
                "Configurar base técnica, arquitectura inicial y primeros entregables de {$name}."
            ]);
        });

        $this->repo->setSetting('active_workspace', $cleanPath);

        Bootstrap::jsonResponse([
            'success'    => true,
            'message'    => "Proyecto '{$name}' creado y activado correctamente",
            'project_id' => $id,
            'path'       => $cleanPath
        ]);
    }

    public function switchProject(string $projectId): void
    {
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        $stmt->closeCursor();

        if (!$project) {
            Bootstrap::jsonResponse(['error' => 'Proyecto no encontrado'], 404);
            return;
        }

        Database::transaction(function (PDO $pdo) use ($projectId) {
            $pdo->exec("UPDATE projects SET active = 0");
            $u = $pdo->prepare("UPDATE projects SET active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $u->execute([$projectId]);
        });

        $this->repo->setSetting('active_workspace', $project['path']);

        Bootstrap::jsonResponse([
            'success' => true,
            'message' => "Proyecto activo cambiado a '{$project['name']}'",
            'project' => $project
        ]);
    }

    public function importWorkspace(array $input): void
    {
        $rawPath = trim((string)($input['path'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));

        if ($rawPath === '') {
            Bootstrap::jsonResponse(['error' => 'La ruta del workspace a importar es obligatoria'], 422);
            return;
        }

        try {
            $cleanPath = Bootstrap::validateWorkspacePath($rawPath);
        } catch (\InvalidArgumentException $e) {
            Bootstrap::jsonResponse(['error' => $e->getMessage()], 422);
            return;
        }

        if (!is_dir($cleanPath)) {
            Bootstrap::jsonResponse(['error' => "El directorio especificado no existe: {$cleanPath}"], 404);
            return;
        }

        if ($name === '') {
            $name = basename($cleanPath);
        }

        $id = strtolower(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $name));
        if ($id === '' || $id === '-') {
            $id = 'proj-' . time();
        }

        // Inspeccionar si es repositorio Git
        $gitDir = $cleanPath . DIRECTORY_SEPARATOR . '.git';
        $suggestedTasks = [];

        if (is_dir($gitDir)) {
            // Leer commits con paginación acotada (máx 50) para evitar timeouts en repositorios masivos
            $limit = min(50, max(5, (int)($input['limit'] ?? 15)));
            $skip = max(0, (int)($input['skip'] ?? 0));
            $cmd = 'git -C ' . escapeshellarg($cleanPath) . ' log -n ' . $limit . ' --skip=' . $skip . ' --pretty=format:"%h|%s" 2>&1';
            $output = [];
            $exitCode = 0;
            @exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && !empty($output)) {
                $points = [2, 3, 5, 3, 2];
                $roles = ['backend_architect', 'frontend_master', 'lead_architect', 'qa_security_guard'];

                foreach ($output as $idx => $line) {
                    $parts = explode('|', $line, 2);
                    $hash = $parts[0] ?? '';
                    $subject = trim($parts[1] ?? $line);
                    if ($subject === '') continue;

                    $role = $roles[$idx % count($roles)];
                    $pts = $points[$idx % count($points)];

                    $suggestedTasks[] = [
                        'id'              => 'US-' . (200 + $idx),
                        'title'           => "Refactor/Evolución: {$subject}",
                        'story_points'    => $pts,
                        'assigned_to'     => $role,
                        'priority'        => ($idx < 2) ? 'alta' : 'media',
                        'status'          => 'backlog',
                        'user_story'      => "Como equipo deseo continuar la evolución técnica iniciada en el commit {$hash} ({$subject}).",
                        'dod'             => "Código revisado, tests unitarios en verde y documentación actualizada.",
                        'description'     => "Historia generada automáticamente por importación de workspace desde commit git {$hash}.",
                        'skills'          => ['clean-code', 'lean-compact']
                    ];
                }
            }
        }

        // Si no hay commits git o la carpeta no es repo git, crear tareas mínimas de arranque
        if (empty($suggestedTasks)) {
            $suggestedTasks = [
                [
                    'id'              => 'US-201',
                    'title'           => "Auditoría de estructura y dependencias de {$name}",
                    'story_points'    => 3,
                    'assigned_to'     => 'lead_architect',
                    'priority'        => 'alta',
                    'status'          => 'backlog',
                    'user_story'      => "Como arquitecto requiero auditar los archivos y dependencias del workspace importado.",
                    'dod'             => "Inventario de módulos, seguridad y deuda técnica documentada.",
                    'description'     => "Workspace importado desde {$cleanPath}.",
                    'skills'          => ['clean-code']
                ],
                [
                    'id'              => 'US-202',
                    'title'           => "Revisión de accesibilidad y diseño visual",
                    'story_points'    => 2,
                    'assigned_to'     => 'frontend_master',
                    'priority'        => 'media',
                    'status'          => 'backlog',
                    'user_story'      => "Como diseñador frontend requiero chequear ergonomía y cumplimiento WCAG.",
                    'dod'             => "Informe de heurísticas y contraste completado.",
                    'description'     => "Workspace importado desde {$cleanPath}.",
                    'skills'          => ['responsive-design']
                ]
            ];
        }

        $pdo = Database::getPdo();

        // Verificar o regenerar ID
        $chk = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE id = ?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            $id .= '-' . rand(10, 99);
        }

        Database::transaction(function (PDO $pdo) use ($id, $name, $cleanPath, $suggestedTasks) {
            $pdo->exec("UPDATE projects SET active = 0");

            $pStmt = $pdo->prepare("INSERT INTO projects (id, name, path, active) VALUES (?, ?, ?, 1)");
            $pStmt->execute([$id, $name, $cleanPath]);

            // Crear Sprint 1
            $sprintStmt = $pdo->prepare(
                "INSERT INTO sprints (id, project_id, number, name, goal, days_remaining, velocity_points, status)
                 VALUES (?, ?, 1, ?, ?, 10, 20, 'active')"
            );
            $sprintId = "SPRINT-{$id}-01";
            $sprintStmt->execute([
                $sprintId,
                $id,
                "Sprint 1: Onboarding de {$name}",
                "Alineación del equipo de agentes con el código importado en {$cleanPath}."
            ]);

            // Insertar tareas sugeridas en Backlog
            $tStmt = $pdo->prepare(
                "INSERT INTO tasks (id, project_id, title, story_points, assigned_to, priority, status, user_story, dod, description, skills_json, prompt_template)
                 VALUES (?, ?, ?, ?, ?, ?, 'backlog', ?, ?, ?, ?, ?)"
            );

            foreach ($suggestedTasks as $t) {
                $prompt = "Actúa como {$t['assigned_to']}. Ejecuta la tarea: {$t['title']}. DoD: {$t['dod']}";
                $tStmt->execute([
                    $t['id'],
                    $id,
                    $t['title'],
                    $t['story_points'],
                    $t['assigned_to'],
                    $t['priority'],
                    $t['user_story'],
                    $t['dod'],
                    $t['description'],
                    json_encode($t['skills'], JSON_UNESCAPED_UNICODE),
                    $prompt
                ]);
            }
        });

        $this->repo->setSetting('active_workspace', $cleanPath);

        Bootstrap::jsonResponse([
            'success'        => true,
            'message'        => "Workspace '{$name}' importado con éxito con " . count($suggestedTasks) . " historias en Backlog",
            'project_id'     => $id,
            'path'           => $cleanPath,
            'tasks_imported' => count($suggestedTasks)
        ]);
    }
}

