<?php
/**
 * AgileForge - Script de Migración Idempotente
 * Migra los datos desde data/team.json hacia SQLite (data/agileforge.sqlite)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/Database.php';

use AgileForge\Database;

$rootDir = dirname(__DIR__);
$jsonFile = $rootDir . '/data/team.json';
$dbFile = $rootDir . '/data/agileforge.sqlite';

if (!file_exists($jsonFile)) {
    echo "[-] No se encontró data/team.json. Abortando.\n";
    exit(1);
}

$raw = file_get_contents($jsonFile);
$data = json_decode((string)$raw, true);

if (!is_array($data)) {
    echo "[-] JSON inválido en data/team.json.\n";
    exit(1);
}

echo "[*] Inicializando base de datos SQLite en: {$dbFile}\n";
$pdo = Database::init($dbFile);

Database::transaction(function (PDO $pdo) use ($data, $rootDir) {
    // 1. Proyectos
    $ws = $data['active_workspace'] ?? $rootDir;
    $pStmt = $pdo->prepare("INSERT OR REPLACE INTO projects (id, name, path, active) VALUES ('default', 'AgileForge Default', ?, 1)");
    $pStmt->execute([$ws]);

    // 2. Settings generales
    $sStmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value_json, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    $settingsMap = [
        'org_name'              => $data['org_name'] ?? 'AgileForge Ecosystem',
        'active_workspace'      => $ws,
        'jurisdiction'          => $data['jurisdiction'] ?? [],
        'ai_engine'             => $data['ai_engine'] ?? [],
        'available_skills_pool' => $data['available_skills_pool'] ?? [],
        'ui_preferences'        => $data['ui_preferences'] ?? ['theme' => 'obsidian', 'density' => 'compact', 'ux_score' => 94],
    ];

    foreach ($settingsMap as $k => $v) {
        $sStmt->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE)]);
    }

    // 3. Sprint
    if (!empty($data['sprint'])) {
        $sp = $data['sprint'];
        $spStmt = $pdo->prepare("INSERT OR REPLACE INTO sprints (id, project_id, number, name, goal, days_remaining, velocity_points, status) VALUES ('sprint-1', 'default', ?, ?, ?, ?, ?, 'active')");
        $spStmt->execute([
            (int)($sp['number'] ?? 1),
            $sp['name'] ?? 'Sprint 1',
            $sp['goal'] ?? '',
            (int)($sp['days_remaining'] ?? 0),
            (int)($sp['velocity_points'] ?? 0),
        ]);
    }

    // 4. Roles
    if (!empty($data['roles']) && is_array($data['roles'])) {
        $rStmt = $pdo->prepare("INSERT OR REPLACE INTO roles (id, name, alias, avatar, ai_provider, ai_model, description, specialty, assigned_skills_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $rsStmt = $pdo->prepare("INSERT OR IGNORE INTO role_skills (role_id, skill_id) VALUES (?, ?)");

        foreach ($data['roles'] as $role) {
            $assigned = $role['assigned_skills'] ?? [];
            $rStmt->execute([
                $role['id'],
                $role['name'] ?? '',
                $role['alias'] ?? '',
                $role['avatar'] ?? '🤖',
                $role['ai_provider'] ?? 'anthropic',
                $role['ai_model'] ?? 'Claude 3.7 Sonnet',
                $role['description'] ?? '',
                $role['specialty'] ?? '',
                json_encode($assigned, JSON_UNESCAPED_UNICODE)
            ]);

            foreach ($assigned as $skillId) {
                $rsStmt->execute([$role['id'], $skillId]);
            }
        }
    }

    // 5. Catálogo de Skills
    if (!empty($data['skills_catalog']) && is_array($data['skills_catalog'])) {
        $skStmt = $pdo->prepare("INSERT OR REPLACE INTO skills (id, name, level, category, description, tags_json, is_custom) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['skills_catalog'] as $sk) {
            $skStmt->execute([
                $sk['id'],
                $sk['name'] ?? $sk['id'],
                $sk['level'] ?? 'Principal',
                $sk['category'] ?? 'General',
                $sk['desc'] ?? '',
                json_encode($sk['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                !empty($sk['is_custom']) ? 1 : 0
            ]);
        }
    }

    // 6. Tareas (Tasks)
    if (!empty($data['tasks']) && is_array($data['tasks'])) {
        $tStmt = $pdo->prepare("INSERT OR REPLACE INTO tasks (id, project_id, title, story_points, assigned_to, ai_provider, ai_model, priority, status, user_story, dod, description, skills_json, prompt_template, created_at) VALUES (?, 'default', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['tasks'] as $t) {
            $tStmt->execute([
                $t['id'],
                $t['title'] ?? '',
                (int)($t['story_points'] ?? 1),
                $t['assigned_to'] ?? 'project_manager',
                $t['ai_provider'] ?? 'anthropic',
                $t['ai_model'] ?? 'Claude 3.7 Sonnet',
                $t['priority'] ?? 'media',
                $t['status'] ?? 'backlog',
                $t['user_story'] ?? '',
                $t['dod'] ?? '',
                $t['description'] ?? '',
                json_encode($t['skills'] ?? [], JSON_UNESCAPED_UNICODE),
                $t['prompt_template'] ?? '',
                $t['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }

    // 7. Reuniones y Turnos
    if (!empty($data['meetings']) && is_array($data['meetings'])) {
        $mStmt = $pdo->prepare("INSERT OR REPLACE INTO meetings (id, project_id, title, type, topic, participants_json, summary, resolution, action_items_json, converted, created_at) VALUES (?, 'default', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $turnStmt = $pdo->prepare("INSERT INTO meeting_turns (meeting_id, turn_index, role_id, name, alias, avatar, ai_model, statement, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($data['meetings'] as $m) {
            $mStmt->execute([
                $m['id'],
                $m['title'] ?? 'Reunión sin título',
                $m['type'] ?? 'brainstorming',
                $m['topic'] ?? '',
                json_encode($m['participants'] ?? [], JSON_UNESCAPED_UNICODE),
                $m['summary'] ?? '',
                $m['resolution'] ?? '',
                json_encode($m['action_items'] ?? [], JSON_UNESCAPED_UNICODE),
                !empty($m['converted']) ? 1 : 0,
                $m['created_at'] ?? date('Y-m-d H:i:s')
            ]);

            // Limpiar turnos existentes para evitar duplicados en re-ejecución
            $pdo->prepare("DELETE FROM meeting_turns WHERE meeting_id = ?")->execute([$m['id']]);

            if (!empty($m['turns']) && is_array($m['turns'])) {
                foreach ($m['turns'] as $idx => $trn) {
                    $turnStmt->execute([
                        $m['id'],
                        $idx,
                        $trn['role_id'] ?? 'unknown',
                        $trn['name'] ?? '',
                        $trn['alias'] ?? '',
                        $trn['avatar'] ?? '🤖',
                        $trn['ai_model'] ?? '',
                        $trn['statement'] ?? '',
                        $trn['timestamp'] ?? date('H:i')
                    ]);
                }
            }
        }
    }

    // 8. Mensajes de Chat
    if (!empty($data['chat_messages']) && is_array($data['chat_messages'])) {
        $cStmt = $pdo->prepare("INSERT OR REPLACE INTO chat_messages (id, sender_role, sender_name, avatar, timestamp, text) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['chat_messages'] as $msg) {
            $cStmt->execute([
                $msg['id'] ?? ('MSG-' . uniqid()),
                $msg['sender_role'] ?? 'user',
                $msg['sender_name'] ?? 'Usuario',
                $msg['avatar'] ?? '👤',
                $msg['timestamp'] ?? date('H:i'),
                $msg['text'] ?? ''
            ]);
        }
    }
});

echo "[+] Migración completada con éxito. Tablas pobladas y listas.\n";

