<?php
/**
 * AgileForge - RoleRegistry (RFC-001, fase 1)
 *
 * Los roles se definen en archivos Markdown con frontmatter:
 *   - pack core:          plugin/agileforge/packs/core/<id>.md   (los 8 roles del tablero)
 *   - roles del proyecto: <workspace>/.agileforge/roles/<id>.md   (nuevos o que sustituyen a uno del core)
 *
 * El código decide por CAPACIDADES (lista cerrada de 9), no por IDs fijos.
 * La base de datos sigue guardando nombre, avatar, motor y skills de cada rol para el panel;
 * los archivos son la fuente de las capacidades.
 *
 * Un rol que existe en la base pero no tiene archivo (creado antes del registro) conserva el
 * comportamiento de siempre: puede tomar historias y entregarlas, nada más.
 */

declare(strict_types=1);

namespace AgileForge\Services;

final class RoleRegistry
{
    /** Lista cerrada: ninguna otra capacidad es reconocida. */
    public const CAPABILITIES = [
        'can_claim_tasks',
        'can_submit_evidence',
        'can_review_tasks',
        'can_approve_dod',
        'can_record_adr',
        'can_facilitate_sprint',
        'can_forge_agents',
        'can_audit_compliance',
        'can_orchestrate',
    ];

    /** Capacidades de un rol de la base que no tiene archivo (comportamiento previo al registro). */
    public const DEFAULT_CAPABILITIES = ['can_claim_tasks', 'can_submit_evidence'];

    public const MODEL_ALIASES = ['sonnet', 'opus', 'haiku', 'fable', 'inherit'];
    public const EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];
    public const COLORS = ['red', 'green', 'blue', 'purple', 'yellow', 'cyan', 'orange', 'pink'];

    /** Herramientas que un rol del proyecto solo recibe declarándolas (y con aviso). */
    public const RISKY_TOOLS = ['Bash', 'Edit', 'Write', 'NotebookEdit'];
    public const NATIVE_TOOLS = ['Read', 'Grep', 'Glob', 'Bash', 'Edit', 'Write', 'NotebookEdit', 'WebFetch', 'WebSearch', 'Agent', 'TodoWrite'];

    public const ID_PATTERN = '/^[a-z][a-z0-9_]{1,47}$/';

    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<string, array> id → definición */
    private array $roles = [];
    /** @var list<array{file:string,errors:list<string>}> */
    private array $problems = [];

    /** @param list<array{dir:string,source:string}> $sources en orden: los posteriores sustituyen a los anteriores */
    public function __construct(array $sources)
    {
        foreach ($sources as $src) {
            $files = is_dir($src['dir']) ? (glob(rtrim($src['dir'], '/\\') . '/*.md') ?: []) : [];
            sort($files);
            foreach ($files as $file) {
                $errors = [];
                try {
                    [$data, $body] = self::parseFile($file);
                    $errors = self::validate($data, $file);
                } catch (\Throwable $e) {
                    $errors = [$e->getMessage()];
                    $data = null;
                    $body = '';
                }
                if ($errors) {
                    $this->problems[] = ['file' => $file, 'errors' => $errors];
                    continue;
                }
                $data['capabilities'] = array_values(array_unique(array_map('strval', (array)($data['capabilities'] ?? []))));
                $data['source'] = $src['source'];
                $data['file'] = $file;
                $data['body'] = $body;
                $this->roles[$data['id']] = $data;
            }
        }
    }

    public static function appRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function corePackDir(): string
    {
        return self::appRoot() . '/plugin/agileforge/packs/core';
    }

    /** Carpeta del proyecto: ruta absoluta tal cual; vacía o "." es la raíz de la app; relativa, contra la raíz. */
    public static function workspacePath(?array $project): string
    {
        $root = self::appRoot();
        $ws = trim((string)($project['path'] ?? ''));
        if ($ws === '' || $ws === '.') {
            return $root;
        }
        if (preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $ws)) {
            return $ws;
        }
        return $root . '/' . ltrim($ws, './\\');
    }

    /** Registro para un proyecto (o solo el core si no hay proyecto). Se cachea por carpeta durante la petición. */
    public static function forProject(?array $project): self
    {
        $ws = $project === null ? '' : self::workspacePath($project);
        if (!isset(self::$cache[$ws])) {
            $sources = [['dir' => self::corePackDir(), 'source' => 'core']];
            if ($ws !== '') {
                $local = rtrim($ws, '/\\') . '/.agileforge/roles';
                if (realpath($local) !== realpath(self::corePackDir())) {
                    $sources[] = ['dir' => $local, 'source' => 'project'];
                }
            }
            self::$cache[$ws] = new self($sources);
        }
        return self::$cache[$ws];
    }

    /** Registro del proyecto activo. */
    public static function active(): self
    {
        try {
            $project = (new \AgileForge\Repository())->getActiveProject();
        } catch (\Throwable $e) {
            $project = null;
        }
        return self::forProject($project ?: null);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    public function get(string $id): ?array
    {
        return $this->roles[$id] ?? null;
    }

    /** @return array<string, array> */
    public function all(): array
    {
        return $this->roles;
    }

    /** @return list<array{file:string,errors:list<string>}> */
    public function problems(): array
    {
        return $this->problems;
    }

    /** @return list<string> */
    public function capabilities(string $roleId): array
    {
        $role = $this->roles[$roleId] ?? null;
        return $role === null ? self::DEFAULT_CAPABILITIES : $role['capabilities'];
    }

    public function has(string $roleId, string $capability): bool
    {
        return in_array($capability, $this->capabilities($roleId), true);
    }

    /**
     * Primer rol, entre los que existen en el tablero, que tiene la capacidad.
     * Se respeta el orden de $candidates, salvo que $preferred la tenga.
     */
    public function firstWith(string $capability, array $candidates, ?string $preferred = null): ?string
    {
        if ($preferred !== null && in_array($preferred, $candidates, true) && $this->has($preferred, $capability)) {
            return $preferred;
        }
        foreach ($candidates as $id) {
            if ($this->has((string)$id, $capability)) {
                return (string)$id;
            }
        }
        return null;
    }

    /** @return list<string> */
    public function allWith(string $capability, array $candidates): array
    {
        return array_values(array_filter(array_map('strval', $candidates), fn($id) => $this->has($id, $capability)));
    }

    // ───────────────────────── lectura del archivo ─────────────────────────

    /** @return array{0: array, 1: string} frontmatter y cuerpo */
    public static function parseFile(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('No se pudo leer el archivo.');
        }
        if (strlen($raw) > 64 * 1024) {
            throw new \RuntimeException('El archivo supera 64 KB.');
        }
        $raw = str_replace("\r\n", "\n", preg_replace('/^\xEF\xBB\xBF/', '', $raw));
        if (!preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $raw, $m)) {
            throw new \RuntimeException('Falta el bloque de frontmatter (--- … ---) al inicio.');
        }
        return [self::parseFrontmatter($m[1]), $m[2]];
    }

    /**
     * Subconjunto de YAML suficiente para los roles: "clave: valor", listas con "- item" o [a, b],
     * cadenas con o sin comillas y comentarios con #. Nada de mapas anidados ni anclas.
     */
    public static function parseFrontmatter(string $yaml): array
    {
        $out = [];
        $listKey = null;
        foreach (explode("\n", $yaml) as $n => $line) {
            $lineNo = $n + 1;
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }
            if (preg_match('/^\s+-\s*(.*)$/', $line, $m) || preg_match('/^-\s+(.*)$/', $line, $m)) {
                if ($listKey === null) {
                    throw new \RuntimeException("Línea {$lineNo}: elemento de lista sin clave.");
                }
                $out[$listKey][] = self::scalar($m[1], $lineNo);
                continue;
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(.*)$/', $line, $m)) {
                throw new \RuntimeException("Línea {$lineNo}: formato no reconocido.");
            }
            if (preg_match('/^\s/', $line)) {
                throw new \RuntimeException("Línea {$lineNo}: no se admiten claves anidadas.");
            }
            $key = $m[1];
            $rest = self::stripComment($m[2]);
            if (array_key_exists($key, $out)) {
                throw new \RuntimeException("Línea {$lineNo}: la clave «{$key}» está repetida.");
            }
            if ($rest === '') {
                $out[$key] = [];
                $listKey = $key;
                continue;
            }
            $listKey = null;
            if (str_starts_with($rest, '[')) {
                if (!str_ends_with($rest, ']')) {
                    throw new \RuntimeException("Línea {$lineNo}: lista sin cerrar.");
                }
                $inner = trim(substr($rest, 1, -1));
                $out[$key] = $inner === '' ? [] : array_map(fn($v) => self::scalar($v, $lineNo), str_getcsv($inner, ',', '"', '\\'));
                continue;
            }
            $out[$key] = self::scalar($rest, $lineNo);
        }
        return $out;
    }

    private static function stripComment(string $v): string
    {
        $v = trim($v);
        if ($v === '' || $v[0] === '"' || $v[0] === "'") {
            return $v;
        }
        $pos = strpos($v, ' #');
        return trim($pos === false ? $v : substr($v, 0, $pos));
    }

    private static function scalar(string $v, int $lineNo): string
    {
        $v = self::stripComment($v);
        if ($v !== '' && $v[0] === '"') {
            if (!preg_match('/^"((?:[^"\\\\]|\\\\.)*)"$/', $v, $m)) {
                throw new \RuntimeException("Línea {$lineNo}: comillas sin cerrar.");
            }
            return stripcslashes($m[1]);
        }
        if ($v !== '' && $v[0] === "'") {
            if (!preg_match("/^'((?:[^']|'')*)'$/", $v, $m)) {
                throw new \RuntimeException("Línea {$lineNo}: comillas sin cerrar.");
            }
            return str_replace("''", "'", $m[1]);
        }
        return $v;
    }

    // ───────────────────────── validación (nivel 1) ─────────────────────────

    /**
     * Validación determinista de un rol. Devuelve la lista de errores (vacía si es válido).
     * $skillCatalog: IDs de skills válidas; null = no se comprueban (p. ej. sin base de datos).
     * @return list<string>
     */
    public static function validate(array $data, string $file = '', ?array $skillCatalog = null): array
    {
        $e = [];
        $id = $data['id'] ?? null;
        if (!is_string($id) || !preg_match(self::ID_PATTERN, $id)) {
            $e[] = 'id obligatorio en snake_case (minúsculas, números y _; empieza por letra; 2-48 caracteres).';
        } elseif ($file !== '' && pathinfo($file, PATHINFO_FILENAME) !== $id) {
            $e[] = "El archivo debe llamarse {$id}.md (igual que su id).";
        }
        foreach (['name'] as $req) {
            if (!is_string($data[$req] ?? null) || trim($data[$req]) === '') {
                $e[] = "Falta «{$req}».";
            }
        }
        foreach (['name' => 80, 'alias' => 80, 'avatar' => 16, 'description' => 400, 'specialty' => 400] as $k => $max) {
            if (isset($data[$k]) && (!is_string($data[$k]) || mb_strlen($data[$k]) > $max)) {
                $e[] = "«{$k}» debe ser texto de hasta {$max} caracteres.";
            }
        }
        if (!array_key_exists('capabilities', $data) || !is_array($data['capabilities'])) {
            $e[] = 'Falta «capabilities» (lista; puede estar vacía).';
        } else {
            foreach ($data['capabilities'] as $cap) {
                if (!in_array($cap, self::CAPABILITIES, true)) {
                    $e[] = "Capacidad desconocida: «{$cap}». Válidas: " . implode(', ', self::CAPABILITIES) . '.';
                }
            }
        }
        if (isset($data['model'])) {
            $model = $data['model'];
            if (!is_string($model) || !(in_array($model, self::MODEL_ALIASES, true) || preg_match('/^claude-[a-z0-9.-]+$/', $model))) {
                $e[] = '«model» debe ser sonnet, opus, haiku, fable, inherit o un ID claude-….';
            }
        }
        if (isset($data['effort']) && !in_array($data['effort'], self::EFFORTS, true)) {
            $e[] = '«effort» debe ser ' . implode(', ', self::EFFORTS) . '.';
        }
        if (isset($data['color']) && !in_array($data['color'], self::COLORS, true)) {
            $e[] = '«color» debe ser ' . implode(', ', self::COLORS) . '.';
        }
        foreach (['tools', 'disallowedTools', 'skills'] as $k) {
            if (isset($data[$k]) && !is_array($data[$k])) {
                $e[] = "«{$k}» debe ser una lista.";
            }
        }
        foreach ((array)($data['tools'] ?? []) as $tool) {
            if (!self::isKnownTool((string)$tool)) {
                $e[] = "Herramienta no permitida: «{$tool}». Solo nativas de Claude Code o del tablero (board__…).";
            }
        }
        if ($skillCatalog !== null) {
            foreach ((array)($data['skills'] ?? []) as $sk) {
                if (!in_array($sk, $skillCatalog, true)) {
                    $e[] = "Skill inexistente: «{$sk}».";
                }
            }
        }
        $text = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '';
        if (preg_match('/(sk-ant-[A-Za-z0-9_-]{10,}|sk-[A-Za-z0-9]{20,}|AIza[0-9A-Za-z_-]{20,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,})/', $text)) {
            $e[] = 'El archivo parece contener una clave de API o un token: quítalo.';
        }
        return $e;
    }

    /** Nativas conocidas o del tablero de AgileForge (con o sin prefijo de plugin). Nada de comodines. */
    public static function isKnownTool(string $tool): bool
    {
        if (in_array($tool, self::NATIVE_TOOLS, true)) {
            return true;
        }
        if (preg_match('/^board__[a-z_]+$/', $tool)) {
            return true; // forma recomendada: el generador le pone el prefijo MCP del entorno
        }
        return (bool)preg_match('/^mcp__[a-z0-9_-]*(agileforge|board)[a-z0-9_-]*__[a-z_]+$/', $tool);
    }

    /** Skills válidas: las del plugin más las personalizadas de la base. */
    public static function skillCatalog(?\PDO $pdo = null): array
    {
        $ids = array_map('basename', glob(self::appRoot() . '/plugin/agileforge/skills/*', GLOB_ONLYDIR) ?: []);
        if ($pdo !== null) {
            try {
                $ids = array_merge($ids, $pdo->query("SELECT id FROM skills WHERE is_custom = 1")->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            } catch (\Throwable $e) {
            }
        }
        return array_values(array_unique($ids));
    }
}
