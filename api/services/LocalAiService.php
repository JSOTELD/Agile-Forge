<?php
/**
 * AgileForge - LocalAiService
 * Detecta motores de IA local (Ollama, LM Studio) en esta PC y ejecuta chats contra ellos.
 *
 * Seguridad:
 *  - Solo se consultan direcciones locales fijas (OLLAMA_HOST de .env o 127.0.0.1). Nunca una URL enviada por el cliente.
 *  - El modelo a usar debe existir en la lista detectada; no se acepta un nombre arbitrario.
 */

declare(strict_types=1);

namespace AgileForge\Services;

use AgileForge\Repository;
use RuntimeException;

class LocalAiService
{
    private const SETTING_KEY = 'local_ai';
    private const DEFAULT_NUM_CTX = 8192;

    private Repository $repo;

    public function __construct(?Repository $repo = null)
    {
        $this->repo = $repo ?? new Repository();
    }

    /** URL base de Ollama: OLLAMA_HOST de .env si apunta a esta máquina; si no, 127.0.0.1:11434. */
    public static function ollamaBase(): string
    {
        $env = trim((string)($_ENV['OLLAMA_HOST'] ?? getenv('OLLAMA_HOST') ?: ''));
        if ($env !== '') {
            if (!preg_match('#^https?://#i', $env)) {
                $env = 'http://' . $env;
            }
            $host = strtolower((string)parse_url($env, PHP_URL_HOST));
            $port = (int)(parse_url($env, PHP_URL_PORT) ?: 11434);
            if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
                return 'http://127.0.0.1:' . $port;
            }
        }
        return 'http://127.0.0.1:11434';
    }

    /** URL base de Colibri (coli serve): COLIBRI_HOST de .env si apunta a esta máquina; si no, 127.0.0.1:8000. */
    public static function colibriBase(): string
    {
        $env = trim((string)($_ENV['COLIBRI_HOST'] ?? getenv('COLIBRI_HOST') ?: ''));
        if ($env !== '') {
            if (!preg_match('#^https?://#i', $env)) {
                $env = 'http://' . $env;
            }
            $host = strtolower((string)parse_url($env, PHP_URL_HOST));
            $port = (int)(parse_url($env, PHP_URL_PORT) ?: 8000);
            if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
                return 'http://127.0.0.1:' . $port;
            }
        }
        return 'http://127.0.0.1:8000';
    }

    public static function baseFor(string $runtime): string
    {
        return $runtime === 'colibri' ? self::colibriBase() : self::ollamaBase();
    }

    // ------------------------------------------------------------------
    // Ciclo de vida de Colibri: AgileForge puede encenderlo/apagarlo SOLO si el usuario
    // lo permitió ("colibri_control") y tiene la IA local activada con Colibri.
    // Coordinación con el acceso directo del escritorio mediante archivos "lease":
    //   run/agileforge.lease -> AgileForge lo está usando (no apagar al cerrar la ventana)
    //   run/desktop.lease    -> la ventana del escritorio está abierta (latido cada 15 s)
    // ------------------------------------------------------------------

    /** Carpeta de configuración de Colibri (setup.json, logs, scripts de control). */
    public static function colibriHome(): string
    {
        $cfgFile = dirname(__DIR__, 2) . '/config/local-ai.json';
        if (is_file($cfgFile)) {
            $cfg = json_decode((string)file_get_contents($cfgFile), true);
            if (is_array($cfg) && !empty($cfg['colibri_home'])) {
                return rtrim((string)$cfg['colibri_home'], '\\/');
            }
        }
        $local = getenv('LOCALAPPDATA') ?: '';
        return $local !== '' ? $local . '\\colibri' : '';
    }

    private function runDir(): string
    {
        return self::colibriHome() . '\\run';
    }

    private function controlScript(): string
    {
        return self::colibriHome() . '\\agileforge\\colibri-control.ps1';
    }

    /** ¿Puede AgileForge encender/apagar Colibri en esta instalación? [bool, motivo] */
    public function controlSupport(): array
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
            return [false, 'Solo está implementado para Windows.'];
        }
        if (self::colibriHome() === '' || !is_file($this->controlScript())) {
            return [false, 'No se encontró el script de control de Colibri (' . $this->controlScript() . ').'];
        }
        $profile = (string)(getenv('USERPROFILE') ?: '');
        if (stripos($profile, 'systemprofile') !== false) {
            return [false, 'Apache corre como servicio de Windows (cuenta SYSTEM): enciende Colibri con el acceso directo del escritorio.'];
        }
        return [true, ''];
    }

    public function colibriReachable(): bool
    {
        return is_array($this->httpGetJson(self::colibriBase() . '/health', 1.5));
    }

    public function desktopActive(): bool
    {
        $f = $this->runDir() . '\\desktop.lease';
        return is_file($f) && (time() - (int)@filemtime($f)) < 60;
    }

    private function leaseFile(): string
    {
        return $this->runDir() . '\\agileforge.lease';
    }

    private function hasLease(): bool
    {
        return is_file($this->leaseFile());
    }

    private function takeLease(): void
    {
        if (!is_dir($this->runDir())) {
            @mkdir($this->runDir(), 0777, true);
        }
        @file_put_contents($this->leaseFile(), date('c') . " AgileForge usa Colibri (IA local activada)\n");
    }

    private function releaseLease(): void
    {
        if ($this->hasLease()) {
            @unlink($this->leaseFile());
        }
    }

    /** Lanza el script de control en segundo plano (no bloquea la petición). */
    private function runControl(string $action): bool
    {
        [$ok] = $this->controlSupport();
        if (!$ok || !in_array($action, ['start', 'stop'], true)) {
            return false;
        }
        $cmd = [
            'powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-WindowStyle', 'Hidden',
            '-File', $this->controlScript(), $action,
        ];
        $nul = ['file', 'NUL', 'r'];
        $out = ['file', 'NUL', 'w'];
        $proc = @proc_open($cmd, [0 => $nul, 1 => $out, 2 => $out], $pipes, self::colibriHome(), null,
            ['bypass_shell' => true, 'create_process_group' => true]);
        return is_resource($proc);
    }

    private function canAutoControl(array $state): bool
    {
        [$ok] = $this->controlSupport();
        return $ok && !empty($state['colibri_control']);
    }

    /**
     * Ajusta Colibri a la elección del usuario:
     * - IA local activada + motor Colibri + permiso: toma el lease y lo enciende si está apagado.
     * - En cualquier otro caso: suelta el lease y lo apaga si nadie más lo usa (ventana del escritorio).
     */
    private function syncColibri(array $state): array
    {
        $wants = !empty($state['enabled']) && ($state['runtime'] ?? '') === 'colibri' && $this->canAutoControl($state);
        if ($wants) {
            $this->takeLease();
            if (!$this->colibriReachable()) {
                $startedAt = (int)($state['colibri_starting_at'] ?? 0);
                if (time() - $startedAt > 240) {
                    $this->runControl('start');
                    $state['colibri_starting_at'] = time();
                }
                $state['colibri_starting'] = (time() - (int)$state['colibri_starting_at']) < 240;
            } else {
                $state['colibri_starting'] = false;
            }
        } elseif ($this->hasLease()) {
            $this->releaseLease();
            if ($this->canAutoControl($state) && !$this->desktopActive() && $this->colibriReachable()) {
                $this->runControl('stop');
                $state['colibri_stopping_at'] = time();
            }
        }
        $state['agileforge_lease'] = $this->hasLease();
        return $state;
    }

    /** Si hace falta, enciende Colibri y espera a que responda (máx. $timeout s). */
    public function ensureColibri(int $timeout = 240): bool
    {
        if ($this->colibriReachable()) {
            return true;
        }
        $state = $this->getState();
        if (!$this->canAutoControl($state) || empty($state['enabled'])) {
            return false;
        }
        $state = $this->saveState($this->syncColibri($state));
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            sleep(2);
            if ($this->colibriReachable()) {
                return true;
            }
        }
        return false;
    }

    /** Permiso del usuario para que AgileForge encienda/apague Colibri. */
    public function setColibriControl(bool $allowed): array
    {
        $state = $this->getState();
        $state['colibri_control'] = $allowed;
        $state = $this->syncColibri($state);
        $this->saveState($state);
        return $this->detect();
    }

    /** Estado guardado (activado, modelo, última detección). */
    public function getState(): array
    {
        $state = $this->repo->getSetting(self::SETTING_KEY, []);
        if (!is_array($state)) {
            $state = [];
        }
        return array_merge([
            'enabled'        => false,
            'runtime'        => 'ollama',
            'model'          => null,
            'num_ctx'        => self::DEFAULT_NUM_CTX,
            'detected'       => false,
            'runtimes'       => [],
            'last_checked'   => null,
        ], $state);
    }

    private function saveState(array $state): array
    {
        $this->repo->setSetting(self::SETTING_KEY, $state);
        return $state;
    }

    /** ¿La IA local está activada y con un modelo elegido? */
    public function isActive(): bool
    {
        $s = $this->getState();
        return !empty($s['enabled']) && !empty($s['model']);
    }

    /**
     * Busca motores locales y guarda el resultado.
     * Si la IA local estaba activa pero el motor ya no responde, queda activada pero marcada como no disponible.
     */
    public function detect(): array
    {
        $runtimes = [];

        // 1) Ollama
        $base = self::ollamaBase();
        $version = $this->httpGetJson($base . '/api/version', 1.5);
        if (is_array($version)) {
            $tags = $this->httpGetJson($base . '/api/tags', 2.0) ?: [];
            $ps = $this->httpGetJson($base . '/api/ps', 1.5) ?: [];
            $loaded = array_map(fn($m) => (string)($m['name'] ?? ''), (array)($ps['models'] ?? []));

            $models = [];
            foreach ((array)($tags['models'] ?? []) as $m) {
                $name = (string)($m['name'] ?? $m['model'] ?? '');
                if ($name === '') continue;
                $details = (array)($m['details'] ?? []);
                $models[] = [
                    'id'             => $name,
                    'size_gb'        => round(((int)($m['size'] ?? 0)) / 1_000_000_000, 1),
                    'parameter_size' => (string)($details['parameter_size'] ?? ''),
                    'quantization'   => (string)($details['quantization_level'] ?? ''),
                    'family'         => (string)($details['family'] ?? ''),
                    'loaded'         => in_array($name, $loaded, true),
                ];
            }
            usort($models, fn($a, $b) => $a['size_gb'] <=> $b['size_gb']);

            $runtimes[] = [
                'id'       => 'ollama',
                'name'     => 'Ollama',
                'icon'     => '🦙',
                'endpoint' => $base,
                'version'  => (string)($version['version'] ?? ''),
                'models'   => $models,
            ];
        }

        // 2) Colibri (coli serve): servidor compatible con OpenAI para modelos MoE grandes
        $cBase = self::colibriBase();
        $cModels = $this->httpGetJson($cBase . '/v1/models', 1.5);
        if (is_array($cModels) && isset($cModels['data']) && is_array($cModels['data'])) {
            $health = $this->httpGetJson($cBase . '/health', 1.0) ?: [];
            $models = [];
            foreach ($cModels['data'] as $m) {
                $id = (string)($m['id'] ?? '');
                if ($id === '') continue;
                $ctx = (int)($m['context_length'] ?? $m['max_model_len'] ?? 0);
                $models[] = [
                    'id'             => $id,
                    'size_gb'        => null,
                    'parameter_size' => '',
                    'quantization'   => '',
                    'family'         => 'colibri',
                    'context'        => $ctx,
                    'loaded'         => true,
                ];
            }
            $runtimes[] = [
                'id'       => 'colibri',
                'name'     => 'Colibri',
                'icon'     => '🐦',
                'endpoint' => $cBase,
                'version'  => (string)($health['version'] ?? ''),
                'models'   => $models,
            ];
        }

        // 3) LM Studio (servidor local compatible con OpenAI). Solo se informa; el chat usa Ollama o Colibri.
        $lms = $this->httpGetJson('http://127.0.0.1:1234/v1/models', 1.0);
        if (is_array($lms) && isset($lms['data'])) {
            $runtimes[] = [
                'id'       => 'lmstudio',
                'name'     => 'LM Studio',
                'icon'     => '🧪',
                'endpoint' => 'http://127.0.0.1:1234',
                'version'  => '',
                'models'   => array_map(fn($m) => ['id' => (string)($m['id'] ?? ''), 'size_gb' => null, 'parameter_size' => '', 'quantization' => '', 'family' => '', 'loaded' => false], (array)$lms['data']),
                'chat_supported' => false,
            ];
        }

        $state = $this->getState();
        $state['runtimes'] = $runtimes;
        $state['last_checked'] = date('c');

        // Motores con los que se puede chatear y sus modelos
        $usable = [];
        foreach ($runtimes as $rt) {
            if (in_array($rt['id'], ['ollama', 'colibri'], true) && !empty($rt['models'])) {
                $usable[$rt['id']] = array_column($rt['models'], 'id');
            }
        }
        $state['ollama_running'] = isset($usable['ollama']) || $this->runtimeListed($runtimes, 'ollama');
        $state['colibri_running'] = isset($usable['colibri']);

        // Modelos de Colibri vistos antes: se pueden elegir aunque esté apagado si AgileForge puede encenderlo
        $known = is_array($state['known_models'] ?? null) ? $state['known_models'] : [];
        if (isset($usable['colibri'])) {
            $known['colibri'] = $usable['colibri'];
        }
        $state['known_models'] = $known;
        [$ctlOk, $ctlWhy] = $this->controlSupport();
        $state['colibri_control'] = !empty($state['colibri_control']);
        $state['colibri_control_supported'] = $ctlOk;
        $state['colibri_control_reason'] = $ctlWhy;
        $state['desktop_active'] = $this->desktopActive();
        $state['agileforge_lease'] = $this->hasLease();
        $state['colibri_offline_models'] = (!isset($usable['colibri']) && $ctlOk && $state['colibri_control'])
            ? ($known['colibri'] ?? []) : [];
        $state['colibri_starting'] = !isset($usable['colibri']) && (time() - (int)($state['colibri_starting_at'] ?? 0)) < 240;
        $state['php_user'] = (string)(getenv('USERNAME') ?: '');

        $state['detected'] = !empty($usable) || !empty($state['colibri_offline_models']);

        $runtime = (string)($state['runtime'] ?? 'ollama');
        $model = (string)($state['model'] ?? '');
        $current = $usable[$runtime] ?? [];

        if (!in_array($model, $current, true)) {
            // El motor o modelo elegido no está: si está activada, se marca como no disponible
            // pero se conserva la elección (puede ser que Colibri o Ollama estén apagados un momento).
            if (empty($state['enabled']) || $model === '') {
                if ($current) {
                    $state['model'] = $current[0];
                } elseif ($usable) {
                    $first = array_key_first($usable);
                    $state['runtime'] = $first;
                    $state['model'] = $usable[$first][0];
                }
            }
        }
        $state['available'] = in_array((string)($state['model'] ?? ''), $usable[(string)($state['runtime'] ?? '')] ?? [], true);

        return $this->saveState($state);
    }

    private function runtimeListed(array $runtimes, string $id): bool
    {
        foreach ($runtimes as $rt) {
            if (($rt['id'] ?? '') === $id) return true;
        }
        return false;
    }

    /** Activa o desactiva la IA local. Solo se puede activar si hay un motor con modelos. */
    public function setEnabled(bool $enabled, ?string $model = null, ?string $runtime = null): array
    {
        $state = $this->detect();

        if ($enabled) {
            if (!$state['detected']) {
                throw new RuntimeException('No se detectó una IA local con modelos. Abre Ollama, inicia Colibri o permite que AgileForge lo encienda.');
            }
            if ($model !== null && $model !== '') {
                $runtime = $runtime ?: (string)($state['runtime'] ?? 'ollama');
                $this->assertKnownModel($state, $runtime, $model);
                $state['runtime'] = $runtime;
                $state['model'] = $model;
            }
        }

        $state['enabled'] = $enabled;
        $state = $this->syncColibri($state);
        $state['available'] = $this->isModelUsableNow($state);
        return $this->saveState($state);
    }

    private function isModelUsableNow(array $state): bool
    {
        foreach ($state['runtimes'] ?? [] as $rt) {
            if (($rt['id'] ?? '') === ($state['runtime'] ?? '')
                && in_array((string)($state['model'] ?? ''), array_column($rt['models'] ?? [], 'id'), true)) {
                return true;
            }
        }
        return false;
    }

    public function setModel(string $model, ?int $numCtx = null, ?string $runtime = null): array
    {
        $state = $this->detect();
        $runtime = $runtime ?: (string)($state['runtime'] ?? 'ollama');
        $this->assertKnownModel($state, $runtime, $model);
        $state['runtime'] = $runtime;
        $state['model'] = $model;
        if ($numCtx !== null) {
            $state['num_ctx'] = max(2048, min(32768, $numCtx));
        }
        $state = $this->syncColibri($state);
        $state['available'] = $this->isModelUsableNow($state);
        return $this->saveState($state);
    }

    private function assertKnownModel(array $state, string $runtime, string $model): void
    {
        if (!in_array($runtime, ['ollama', 'colibri'], true)) {
            throw new RuntimeException("Motor local no soportado: '{$runtime}'.");
        }
        foreach ($state['runtimes'] as $rt) {
            if ($rt['id'] !== $runtime) continue;
            if (in_array($model, array_column($rt['models'], 'id'), true)) {
                return;
            }
        }
        if ($runtime === 'colibri' && in_array($model, $state['colibri_offline_models'] ?? [], true)) {
            return; // apagado, pero AgileForge puede encenderlo
        }
        $name = $runtime === 'colibri' ? 'Colibri' : 'Ollama';
        throw new RuntimeException("El modelo '{$model}' no está disponible en {$name}.");
    }

    /**
     * Chat con el modelo local activo. Devuelve el mismo formato que LlmService::complete().
     * @param array $messages Lista de ['role' => 'user'|'assistant', 'content' => string]
     */
    public function chat(string $systemPrompt, array $messages, ?string $model = null, int $maxTokens = 1024): array
    {
        $state = $this->getState();
        $model = $model ?: (string)($state['model'] ?? '');
        if ($model === '') {
            throw new RuntimeException('No hay un modelo local elegido.');
        }

        @set_time_limit(600);

        $runtime = (string)($state['runtime'] ?? 'ollama');
        if ($runtime === 'colibri') {
            if (!$this->ensureColibri(240)) {
                throw new RuntimeException($this->canAutoControl($state)
                    ? 'Colibri no terminó de encender a tiempo. Vuelve a intentarlo en unos segundos.'
                    : 'Colibri está apagado. Ábrelo con el acceso directo "Colibri IA" o permite que AgileForge lo encienda (Motor IA → IA local).');
            }
            return $this->chatColibri($model, $systemPrompt, $messages, $maxTokens);
        }

        $payload = [
            'model'      => $model,
            'messages'   => array_merge(
                $systemPrompt !== '' ? [['role' => 'system', 'content' => $systemPrompt]] : [],
                $messages
            ),
            'stream'     => false,
            'think'      => false,
            'keep_alive' => '15m',
            'options'    => ['num_ctx' => (int)($state['num_ctx'] ?? self::DEFAULT_NUM_CTX), 'num_predict' => max(256, $maxTokens)],
        ];

        $started = microtime(true);
        $res = $this->httpPostJson(self::ollamaBase() . '/api/chat', $payload, 540);
        $elapsed = microtime(true) - $started;

        $text = trim((string)($res['message']['content'] ?? ''));
        // Algunos modelos con razonamiento devuelven el bloque de pensamiento dentro del texto.
        $text = trim((string)preg_replace('#<think>.*?</think>#s', '', $text));
        if ($text === '') {
            throw new RuntimeException('El modelo local no devolvió texto.');
        }

        $in = (int)($res['prompt_eval_count'] ?? 0);
        $out = (int)($res['eval_count'] ?? 0);
        $evalNs = (int)($res['eval_duration'] ?? 0);

        return [
            'text'               => $text,
            'provider'           => 'ollama',
            'model'              => $model,
            'input_tokens'       => $in,
            'output_tokens'      => $out,
            'total_tokens'       => $in + $out,
            'estimated_cost_usd' => 0.0,
            'tokens_per_second'  => $evalNs > 0 ? round($out / ($evalNs / 1e9), 1) : null,
            'seconds'            => round($elapsed, 1),
        ];
    }

    /** Chat contra Colibri (API compatible con OpenAI). Qwen3.6 responde sin razonamiento salvo que se pida. */
    private function chatColibri(string $model, string $systemPrompt, array $messages, int $maxTokens = 1024): array
    {
        $payload = [
            'model'       => $model,
            'messages'    => array_merge(
                $systemPrompt !== '' ? [['role' => 'system', 'content' => $systemPrompt]] : [],
                $messages
            ),
            'stream'      => false,
            'max_tokens'  => max(256, $maxTokens),
            'temperature' => 0.7,
        ];

        $started = microtime(true);
        $res = $this->httpPostJson(self::colibriBase() . '/v1/chat/completions', $payload, 540, 'Colibri');
        $elapsed = microtime(true) - $started;

        $text = trim((string)($res['choices'][0]['message']['content'] ?? ''));
        $text = trim((string)preg_replace('#<think>.*?</think>#s', '', $text));
        if ($text === '') {
            throw new RuntimeException('Colibri no devolvió texto.');
        }
        $in = (int)($res['usage']['prompt_tokens'] ?? 0);
        $out = (int)($res['usage']['completion_tokens'] ?? 0);

        return [
            'text'               => $text,
            'provider'           => 'colibri',
            'model'              => $model,
            'input_tokens'       => $in,
            'output_tokens'      => $out,
            'total_tokens'       => $in + $out,
            'estimated_cost_usd' => 0.0,
            // Aproximado: incluye la lectura del prompt; el panel de Colibri muestra la velocidad exacta.
            'tokens_per_second'  => ($elapsed > 0 && $out > 0) ? round($out / $elapsed, 1) : null,
            'seconds'            => round($elapsed, 1),
        ];
    }

    private function httpGetJson(string $url, float $timeout): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_CONNECTTIMEOUT_MS => (int)($timeout * 1000),
            CURLOPT_TIMEOUT_MS        => (int)($timeout * 1000),
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) {
            return null;
        }
        $data = json_decode((string)$body, true);
        return is_array($data) ? $data : null;
    }

    private function httpPostJson(string $url, array $payload, int $timeoutSeconds, string $engine = 'Ollama'): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => $timeoutSeconds,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $err !== '') {
            $base = (string)parse_url($url, PHP_URL_SCHEME) . '://' . (string)parse_url($url, PHP_URL_HOST) . ':' . (string)parse_url($url, PHP_URL_PORT);
            throw new RuntimeException("{$engine} no responde en {$base}. ¿Está abierto? ({$err})");
        }
        $data = json_decode((string)$body, true);
        if ($code >= 400) {
            $msg = '';
            if (is_array($data)) {
                $e = $data['error'] ?? '';
                $msg = is_array($e) ? (string)($e['message'] ?? '') : (string)$e;
            }
            throw new RuntimeException("{$engine} devolvió HTTP {$code}" . ($msg !== '' ? ": {$msg}" : ''));
        }
        return is_array($data) ? $data : [];
    }
}
