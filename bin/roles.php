<?php
/**
 * AgileForge - registro de roles en archivos (RFC-001, fase 1)
 *
 *   php bin/roles.php list     [--workspace=DIR]
 *   php bin/roles.php validate [--workspace=DIR]
 *   php bin/roles.php agents   [--workspace=DIR] [--prefix=mcp__srv__] [--dry-run] [--force]
 *
 * list      Roles del pack core y del proyecto, con su origen y capacidades.
 * validate  Validación fija (nivel 1): formato, id, capacidades de la lista cerrada, modelo,
 *           herramientas, skills del catálogo y claves de API escritas por error. Sale con 1 si hay errores.
 * agents    Genera .claude/agents/<id>.md en el proyecto para cada rol del proyecto
 *           (<workspace>/.agileforge/roles/*.md). Siempre con lista explícita de herramientas:
 *           sin "tools" Claude Code daría TODAS al subagente. Los roles del core ya tienen su
 *           subagente en el plugin y no se generan.
 *
 * Sin --workspace se usa la carpeta del proyecto activo del tablero (o la raíz de AgileForge).
 */

declare(strict_types=1);

use AgileForge\Services\RoleRegistry;

const MARKER = '<!-- generado por AgileForge desde .agileforge/roles; edita el rol, no este archivo -->';

$root = dirname(__DIR__);
require_once $root . '/api/services/RoleRegistry.php';

$args = $argv;
array_shift($args);
$cmd = $args[0] ?? 'list';
$opts = [];
foreach (array_slice($args, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}

function out(string $s = ''): void
{
    fwrite(STDOUT, $s . PHP_EOL);
}

function err(string $s): void
{
    fwrite(STDERR, $s . PHP_EOL);
}

/** Carpeta del proyecto: --workspace, o la del proyecto activo en la base, o la raíz. */
function workspace(array $opts, string $root): string
{
    if (!empty($opts['workspace']) && is_string($opts['workspace'])) {
        $ws = realpath($opts['workspace']);
        if ($ws === false || !is_dir($ws)) {
            err("No existe la carpeta: {$opts['workspace']}");
            exit(2);
        }
        return $ws;
    }
    try {
        require_once $root . '/api/bootstrap.php';
        require_once $root . '/api/Database.php';
        require_once $root . '/api/Repository.php';
        $proj = (new \AgileForge\Repository())->getActiveProject();
        return RoleRegistry::workspacePath($proj ?: null);
    } catch (\Throwable $e) {
        return $root;
    }
}

function skillCatalog(string $root): array
{
    try {
        require_once $root . '/api/bootstrap.php';
        require_once $root . '/api/Database.php';
        return RoleRegistry::skillCatalog(\AgileForge\Database::getPdo());
    } catch (\Throwable $e) {
        return RoleRegistry::skillCatalog(null);
    }
}

$ws = workspace($opts, $root);
$localDir = rtrim($ws, '/\\') . '/.agileforge/roles';
$sources = [['dir' => RoleRegistry::corePackDir(), 'source' => 'core']];
if (realpath($localDir) !== realpath(RoleRegistry::corePackDir())) {
    $sources[] = ['dir' => $localDir, 'source' => 'project'];
}

switch ($cmd) {
    case 'list':
        $reg = new RoleRegistry($sources);
        out("Proyecto: {$ws}");
        foreach ($reg->all() as $id => $r) {
            out(sprintf('  %-22s %-8s %s', $id, $r['source'], implode(', ', $r['capabilities']) ?: '(sin capacidades)'));
        }
        foreach ($reg->problems() as $p) {
            out('  ✗ ' . basename($p['file']) . ': ' . implode(' ', $p['errors']));
        }
        exit($reg->problems() ? 1 : 0);

    case 'validate':
        $catalog = skillCatalog($root);
        $count = 0;
        $bad = 0;
        foreach ($sources as $src) {
            foreach (glob(rtrim($src['dir'], '/\\') . '/*.md') ?: [] as $file) {
                $count++;
                try {
                    [$data] = RoleRegistry::parseFile($file);
                    $errors = RoleRegistry::validate($data, $file, $catalog);
                } catch (\Throwable $e) {
                    $errors = [$e->getMessage()];
                    $data = [];
                }
                if ($src['source'] === 'project') {
                    $risky = array_values(array_intersect((array)($data['tools'] ?? []), RoleRegistry::RISKY_TOOLS));
                    if ($risky) {
                        out('  ⚠ ' . basename($file) . ': pide herramientas con riesgo (' . implode(', ', $risky) . '). Revísalo antes de activarlo.');
                    }
                }
                if ($errors) {
                    $bad++;
                    out('  ✗ ' . $src['source'] . '/' . basename($file));
                    foreach ($errors as $e) {
                        out('      - ' . $e);
                    }
                } else {
                    out('  ✓ ' . $src['source'] . '/' . basename($file));
                }
            }
        }
        out("{$count} archivo(s), {$bad} con errores.");
        exit($bad ? 1 : 0);

    case 'agents':
        $reg = new RoleRegistry($sources);
        if ($reg->problems()) {
            err('Hay roles con errores: ejecuta "php bin/roles.php validate" y corrígelos antes de generar.');
            exit(1);
        }
        $prefix = is_string($opts['prefix'] ?? null) ? $opts['prefix'] : detectPrefix($ws);
        if (!preg_match('/^mcp__[a-z0-9_-]+__$/', $prefix)) {
            err("Prefijo MCP no válido: {$prefix} (forma: mcp__<servidor>__)");
            exit(2);
        }
        $dir = rtrim($ws, '/\\') . '/.claude/agents';
        $written = 0;
        $found = 0;
        foreach ($reg->all() as $id => $r) {
            if ($r['source'] !== 'project') {
                continue;
            }
            $found++;
            $target = $dir . '/' . str_replace('_', '-', $id) . '.md';
            $content = renderAgent($r, $prefix);
            if (is_file($target) && empty($opts['force']) && !str_contains((string)file_get_contents($target), MARKER)) {
                out("  · {$target} existe y no lo generó AgileForge: no se toca (usa --force para sustituirlo).");
                continue;
            }
            if (!empty($opts['dry-run'])) {
                out("── {$target}");
                out($content);
                continue;
            }
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                err("No se pudo crear {$dir}");
                exit(1);
            }
            file_put_contents($target, $content);
            $written++;
            out("  ✓ {$target}");
        }
        out($found ? "{$written} subagente(s) escritos. Prefijo MCP usado: {$prefix}" : 'No hay roles del proyecto en ' . $localDir);
        exit(0);

    default:
        err('Uso: php bin/roles.php list|validate|agents [--workspace=DIR] [--prefix=mcp__srv__] [--dry-run] [--force]');
        exit(2);
}

/** Herramientas del tablero que corresponden a cada capacidad. */
function capabilityTools(array $caps): array
{
    $map = [
        'can_claim_tasks'       => ['claim_task'],
        'can_submit_evidence'   => ['submit_for_review', 'remember'],
        'can_review_tasks'      => ['review_task'],
        'can_record_adr'        => ['record_decision', 'list_decisions'],
        'can_facilitate_sprint' => ['create_task', 'update_task_status', 'start_sprint', 'start_meeting', 'add_meeting_turn', 'close_meeting'],
        'can_orchestrate'       => ['get_overview', 'create_task', 'ask_agent'],
    ];
    $tools = [];
    foreach ($caps as $c) {
        $tools = array_merge($tools, $map[$c] ?? []);
    }
    return $tools;
}

/** Prefijo de las herramientas del tablero: servidor del .mcp.json del proyecto, o el del plugin. */
function detectPrefix(string $ws): string
{
    $file = rtrim($ws, '/\\') . '/.mcp.json';
    if (is_file($file)) {
        $cfg = json_decode((string)file_get_contents($file), true);
        foreach ((array)($cfg['mcpServers'] ?? []) as $name => $srv) {
            $cmdline = json_encode($srv);
            if (is_string($name) && preg_match('/^[A-Za-z0-9_-]+$/', $name) && str_contains((string)$cmdline, 'agileforge-mcp')) {
                return 'mcp__' . $name . '__';
            }
        }
    }
    return 'mcp__plugin_agileforge_board__';
}

function yamlStr(string $s): string
{
    return '"' . addcslashes($s, "\"\\\n") . '"';
}

function renderAgent(array $r, string $prefix): string
{
    $readOnly = ['Read', 'Grep', 'Glob', 'board__get_sprint_status', 'board__list_tasks', 'board__get_task',
        'board__get_role_brief', 'board__add_comment', 'board__list_memories', 'board__get_memory'];
    $declared = array_map('strval', (array)($r['tools'] ?? []));
    $tools = array_merge($readOnly, $declared, array_map(fn($t) => 'board__' . $t, capabilityTools($r['capabilities'])));
    $tools = array_values(array_unique(array_map(
        fn($t) => str_starts_with($t, 'board__') ? $prefix . substr($t, 7) : $t,
        $tools
    )));
    // Lo que no se declaró queda prohibido de forma explícita (segunda capa)
    $disallowed = array_values(array_unique(array_merge(
        array_diff(RoleRegistry::RISKY_TOOLS, $declared),
        array_map('strval', (array)($r['disallowedTools'] ?? []))
    )));

    $lines = ['---', 'name: ' . str_replace('_', '-', $r['id'])];
    $desc = trim(($r['name'] ?? $r['id']) . '. ' . ($r['description'] ?? ''));
    $lines[] = 'description: ' . yamlStr($desc);
    $lines[] = 'model: ' . ($r['model'] ?? 'inherit');
    if (!empty($r['effort'])) {
        $lines[] = 'effort: ' . $r['effort'];
    }
    if (!empty($r['color'])) {
        $lines[] = 'color: ' . $r['color'];
    }
    $lines[] = 'tools:';
    foreach ($tools as $t) {
        $lines[] = '  - ' . $t;
    }
    if ($disallowed) {
        $lines[] = 'disallowedTools:';
        foreach ($disallowed as $t) {
            $lines[] = '  - ' . $t;
        }
    }
    if (!empty($r['skills'])) {
        $lines[] = 'skills:';
        foreach ((array)$r['skills'] as $s) {
            $lines[] = '  - ' . $s;
        }
    }
    $lines[] = '---';
    $lines[] = MARKER;
    $lines[] = '';
    $lines[] = trim((string)($r['body'] ?? '')) ?: '# ' . ($r['name'] ?? $r['id']);
    $lines[] = '';
    $lines[] = '## En el tablero AgileForge';
    $lines[] = "- Tu id de rol es `{$r['id']}`: úsalo en role_key / reviewer_role.";
    $lines[] = '- Capacidades: ' . ($r['capabilities'] ? implode(', ', $r['capabilities']) : 'ninguna (solo lectura y comentarios)') . '.';
    $lines[] = '- Antes de empezar, lee tu brief con get_role_brief.';
    return implode("\n", $lines) . "\n";
}
