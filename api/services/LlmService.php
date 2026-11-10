<?php
/**
 * AgileForge - LlmService (Multi-LLM Hub & Token/Cost Accounting)
 * Soporta Anthropic, OpenAI, Google Gemini, DeepSeek y Ollama.
 * Llamadas server-side con API Keys en .env (nunca expuestas al cliente) y contabilidad real de costos.
 */

declare(strict_types=1);

namespace AgileForge\Services;

use AgileForge\Bootstrap;
use AgileForge\Database;
use RuntimeException;

class LlmService
{
    public const ANTHROPIC_VERSION = '2023-06-01';

    private array $config;
    private string $configPath;
    private static array $ollamaStatusCache = [];

    public function __construct()
    {
        $this->configPath = dirname(__DIR__, 2) . '/config/models.json';
        if (file_exists($this->configPath)) {
            $raw = file_get_contents($this->configPath);
            $this->config = json_decode((string)$raw, true) ?: [];
        } else {
            $this->config = ['providers' => []];
        }
    }

    // ------------------------------------------------------------------
    // Catálogo de proveedores y modelos
    // ------------------------------------------------------------------

    /** Catálogo de proveedores con el estado real de api_key_configured (sin exponer claves). */
    public function getProviders(): array
    {
        $out = [];
        foreach ($this->config['providers'] ?? [] as $id => $p) {
            $p['id'] = $p['id'] ?? $id;
            $p['api_key_configured'] = $this->isProviderConfigured($id);
            $p['key_hint'] = $this->keyHint($id);
            if ($id === 'anthropic') {
                $p['workspace_id'] = $this->anthropicWorkspace();
            }
            $out[] = $p;
        }
        return $out;
    }

    public function providerConfig(string $providerId): ?array
    {
        return $this->config['providers'][$providerId] ?? null;
    }

    public function providerName(string $providerId): string
    {
        return (string)($this->providerConfig($providerId)['name'] ?? $providerId);
    }

    /** IDs de modelo válidos de un proveedor. */
    public function modelIds(string $providerId): array
    {
        return array_values(array_map(fn($m) => (string)$m['id'], $this->providerConfig($providerId)['models'] ?? []));
    }

    /**
     * Devuelve un ID de modelo válido: acepta el ID exacto o el nombre visible ("Claude Sonnet 5.5");
     * si no lo reconoce, usa el modelo por defecto del proveedor.
     */
    public function normalizeModel(string $providerId, ?string $model): string
    {
        $p = $this->providerConfig($providerId);
        if (!$p) {
            return (string)$model;
        }
        $model = trim((string)$model);
        foreach ($p['models'] ?? [] as $m) {
            if (strcasecmp((string)$m['id'], $model) === 0 || strcasecmp((string)($m['name'] ?? ''), $model) === 0) {
                return (string)$m['id'];
            }
        }
        return (string)($p['default_model'] ?? ($p['models'][0]['id'] ?? $model));
    }

    public function isKnownModel(string $providerId, string $model): bool
    {
        return in_array($model, $this->modelIds($providerId), true);
    }

    public function envKeyFor(string $providerId): string
    {
        return (string)($this->providerConfig($providerId)['env_key'] ?? '');
    }

    private function apiKey(string $providerId): string
    {
        $envKey = $this->envKeyFor($providerId);
        if ($envKey === '') {
            return '';
        }
        if (class_exists(Bootstrap::class, false)) {
            return Bootstrap::env($envKey);
        }
        return (string)($_ENV[$envKey] ?? getenv($envKey) ?: '');
    }

    private function keyHint(string $providerId): string
    {
        $key = $this->apiKey($providerId);
        if ($key === '' || strlen($key) < 12) {
            return '';
        }
        return substr($key, 0, 7) . '…' . substr($key, -4);
    }

    public function isProviderConfigured(string $providerId): bool
    {
        $p = $this->providerConfig($providerId);
        if (!$p) {
            return false;
        }
        if ($providerId === 'ollama') {
            return $this->checkOllamaStatus((string)($p['endpoint'] ?? 'http://127.0.0.1:11434'));
        }
        $val = $this->apiKey($providerId);
        return ($val !== '' && strlen($val) > 15 && !str_starts_with($val, 'tu_clave') && !str_contains($val, '...'));
    }

    private function checkOllamaStatus(string $endpoint): bool
    {
        if (isset(self::$ollamaStatusCache[$endpoint])) {
            return self::$ollamaStatusCache[$endpoint];
        }
        $base = $this->ollamaBase($endpoint);
        $ch = curl_init($base . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return self::$ollamaStatusCache[$endpoint] = ($httpCode === 200);
    }

    private function ollamaBase(string $endpoint): string
    {
        $parts = parse_url($endpoint) ?: [];
        $host = $parts['host'] ?? '127.0.0.1';
        if ($host === 'localhost') {
            $host = '127.0.0.1'; // Ollama escucha en IPv4; evita la espera por ::1 en Windows
        }
        return ($parts['scheme'] ?? 'http') . '://' . $host . ':' . ($parts['port'] ?? 11434);
    }

    // ------------------------------------------------------------------
    // Llamadas
    // ------------------------------------------------------------------

    /** Compatibilidad: una sola pregunta con system prompt. */
    public function complete(
        string $providerId,
        string $modelId,
        string $prompt,
        string $systemPrompt = '',
        array $metadata = []
    ): array {
        return $this->chat($providerId, $modelId, $systemPrompt, [['role' => 'user', 'content' => $prompt]], ['metadata' => $metadata]);
    }

    /**
     * Conversación multi-turno con cualquier proveedor.
     * $messages: [['role' => 'user'|'assistant', 'content' => string], ...]
     * $opts: max_tokens, temperature, timeout, metadata (agent_role, task_id, context_source)
     */
    public function chat(string $providerId, string $modelId, string $systemPrompt, array $messages, array $opts = []): array
    {
        $provider = $this->providerConfig($providerId);
        if (!$provider) {
            throw new RuntimeException("Proveedor no reconocido: '{$providerId}'");
        }
        if (!$this->isProviderConfigured($providerId)) {
            $envKey = $this->envKeyFor($providerId) ?: 'API_KEY';
            throw new RuntimeException(
                $providerId === 'ollama'
                    ? 'Ollama no está encendido en esta PC.'
                    : "Falta la clave de API de {$this->providerName($providerId)} ({$envKey}). Agrégala en Motor IA → Claves de API."
            );
        }

        $modelId = $this->normalizeModel($providerId, $modelId);
        $maxTokens = (int)($opts['max_tokens'] ?? 2048);
        $timeout = (int)($opts['timeout'] ?? 120);
        $temperature = $opts['temperature'] ?? null;
        $messages = $this->cleanMessages($messages);
        $apiKey = $this->apiKey($providerId);

        $replyText = '';
        $inputTokens = 0;
        $outputTokens = 0;

        switch ($providerId) {
            case 'anthropic':
                $payload = [
                    'model'      => $modelId,
                    'max_tokens' => $maxTokens,
                    'messages'   => $messages,
                ];
                if ($systemPrompt !== '') {
                    $payload['system'] = $systemPrompt;
                }
                if ($temperature !== null) {
                    $payload['temperature'] = (float)$temperature;
                }
                $res = $this->anthropicRaw($payload, $timeout);
                $replyText = $this->anthropicText($res);
                $inputTokens = (int)($res['usage']['input_tokens'] ?? 0);
                $outputTokens = (int)($res['usage']['output_tokens'] ?? 0);
                break;

            case 'openai':
                // Responses API (modelos GPT-6)
                $payload = [
                    'model'             => $modelId,
                    'input'             => $messages,
                    'max_output_tokens' => max(256, $maxTokens),
                ];
                if ($systemPrompt !== '') {
                    $payload['instructions'] = $systemPrompt;
                }
                $res = $this->httpPost((string)$provider['endpoint'], $payload, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ], $timeout);
                $replyText = (string)($res['output_text'] ?? '');
                if ($replyText === '') {
                    foreach ($res['output'] ?? [] as $item) {
                        foreach (($item['type'] ?? '') === 'message' ? ($item['content'] ?? []) : [] as $c) {
                            if (($c['type'] ?? '') === 'output_text') {
                                $replyText .= (string)($c['text'] ?? '');
                            }
                        }
                    }
                }
                $inputTokens = (int)($res['usage']['input_tokens'] ?? 0);
                $outputTokens = (int)($res['usage']['output_tokens'] ?? 0);
                break;

            case 'deepseek':
                $msgs = [];
                if ($systemPrompt !== '') {
                    $msgs[] = ['role' => 'system', 'content' => $systemPrompt];
                }
                $msgs = array_merge($msgs, $messages);
                // DeepSeek V4 razona por defecto: se deja margen para el razonamiento además de la respuesta
                $payload = ['model' => $modelId, 'messages' => $msgs, 'max_tokens' => max(4096, $maxTokens * 2)];
                $res = $this->httpPost((string)$provider['endpoint'], $payload, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ], $timeout);
                $replyText = (string)($res['choices'][0]['message']['content'] ?? '');
                $inputTokens = (int)($res['usage']['prompt_tokens'] ?? 0);
                $outputTokens = (int)($res['usage']['completion_tokens'] ?? 0);
                break;

            case 'google':
                $contents = [];
                foreach ($messages as $m) {
                    $contents[] = [
                        'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $m['content']]],
                    ];
                }
                $payload = ['contents' => $contents, 'generationConfig' => ['maxOutputTokens' => $maxTokens]];
                if (str_starts_with($modelId, 'gemini-3')) {
                    // Gemini 3 "piensa" por defecto y ese razonamiento consume el límite de salida
                    $payload['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'low'];
                }
                if ($systemPrompt !== '') {
                    $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
                }
                $url = rtrim((string)$provider['endpoint'], '/') . "/models/{$modelId}:generateContent";
                $res = $this->httpPost($url, $payload, ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey], $timeout);
                foreach ($res['candidates'][0]['content']['parts'] ?? [] as $part) {
                    if (empty($part['thought'])) {
                        $replyText .= (string)($part['text'] ?? '');
                    }
                }
                $inputTokens = (int)($res['usageMetadata']['promptTokenCount'] ?? 0);
                $outputTokens = (int)($res['usageMetadata']['candidatesTokenCount'] ?? 0);
                break;

            case 'ollama':
                $msgs = [];
                if ($systemPrompt !== '') {
                    $msgs[] = ['role' => 'system', 'content' => $systemPrompt];
                }
                $payload = [
                    'model'    => $modelId,
                    'messages' => array_merge($msgs, $messages),
                    'stream'   => false,
                    'think'    => false,
                ];
                $res = $this->httpPost($this->ollamaBase((string)$provider['endpoint']) . '/api/chat', $payload, ['Content-Type: application/json'], max($timeout, 300));
                $replyText = (string)($res['message']['content'] ?? '');
                $inputTokens = (int)($res['prompt_eval_count'] ?? 0);
                $outputTokens = (int)($res['eval_count'] ?? 0);
                break;

            default:
                throw new RuntimeException("Proveedor no soportado: '{$providerId}'");
        }

        $replyText = trim((string)preg_replace('/<think>.*?<\/think>/s', '', $replyText));
        $totalTokens = $inputTokens + $outputTokens;
        $costUsd = $this->calculateCost($providerId, $modelId, $inputTokens, $outputTokens);

        $meta = $opts['metadata'] ?? [];
        if (($opts['record_usage'] ?? true) !== false) {
            $sprintId = !empty($meta['sprint_id']) ? (string)$meta['sprint_id'] : (string)((new \AgileForge\Repository())->getActiveSprint()['id'] ?? 'default');
            $this->recordUsage(
                (string)($meta['agent_role'] ?? 'system'),
                $meta['task_id'] ?? null,
                $sprintId,
                $providerId,
                $modelId,
                $inputTokens,
                $outputTokens,
                $totalTokens,
                $costUsd,
                (string)($meta['context_source'] ?? 'chat')
            );
        }

        return [
            'text'               => $replyText,
            'provider'           => $providerId,
            'model'              => $modelId,
            'input_tokens'       => $inputTokens,
            'output_tokens'      => $outputTokens,
            'total_tokens'       => $totalTokens,
            'estimated_cost_usd' => $costUsd,
        ];
    }

    /**
     * Llamada directa a la Messages API de Anthropic (se usa para el Director con herramientas).
     * Reintenta una vez ante saturación (429/529).
     */
    public function anthropicRaw(array $payload, int $timeout = 120): array
    {
        $apiKey = $this->apiKey('anthropic');
        if ($apiKey === '') {
            throw new RuntimeException('Falta la clave de API de Anthropic (ANTHROPIC_API_KEY). Agrégala en Motor IA → Claves de API.');
        }
        $endpoint = (string)($this->providerConfig('anthropic')['endpoint'] ?? 'https://api.anthropic.com/v1/messages');
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
        ];
        // Claves no asociadas a un workspace necesitan indicar uno en cada petición
        $workspace = $this->anthropicWorkspace();
        if ($workspace !== '') {
            $headers[] = 'anthropic-workspace-id: ' . $workspace;
        }

        try {
            return $this->httpPost($endpoint, $payload, $headers, $timeout);
        } catch (RuntimeException $e) {
            if (preg_match('/HTTP (429|529|503)/', $e->getMessage())) {
                sleep(3);
                return $this->httpPost($endpoint, $payload, $headers, $timeout);
            }
            throw $e;
        }
    }

    /** ID de workspace de Anthropic opcional (ANTHROPIC_WORKSPACE_ID). No es un secreto. */
    public function anthropicWorkspace(): string
    {
        $val = class_exists(Bootstrap::class, false)
            ? Bootstrap::env('ANTHROPIC_WORKSPACE_ID')
            : (string)($_ENV['ANTHROPIC_WORKSPACE_ID'] ?? getenv('ANTHROPIC_WORKSPACE_ID') ?: '');
        return preg_match('/^wrkspc_[A-Za-z0-9]{4,80}$/', $val) ? $val : '';
    }

    /** Une los bloques de texto de una respuesta de Anthropic. */
    public function anthropicText(array $res): string
    {
        $text = '';
        foreach ($res['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string)($block['text'] ?? '');
            }
        }
        return trim($text);
    }

    /** Prueba rápida de conexión con un proveedor (respuesta mínima). */
    public function testProvider(string $providerId, ?string $model = null): array
    {
        $model = $this->normalizeModel($providerId, $model ?: ($this->providerConfig($providerId)['default_model'] ?? ''));
        $t0 = microtime(true);
        $res = $this->chat($providerId, $model, 'Responde solo con la palabra: listo', [
            ['role' => 'user', 'content' => '¿Estás disponible?'],
        ], ['max_tokens' => 20, 'timeout' => 45, 'metadata' => ['agent_role' => 'system', 'context_source' => 'connection_test']]);
        $seconds = round(microtime(true) - $t0, 1);
        $elapsedMs = round((microtime(true) - $t0) * 1000);
        return [
            'provider'   => $providerId,
            'model'      => $res['model'],
            'text'       => $res['text'],
            'sample'     => $res['text'],
            'seconds'    => $seconds,
            'elapsed_ms' => $elapsedMs,
        ];
    }

    /**
     * Normaliza mensajes: solo user/assistant, contenido no vacío, roles alternados y empezando por user
     * (requisito de Anthropic y Gemini).
     */
    private function cleanMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $content = is_string($m['content'] ?? null) ? trim($m['content']) : '';
            if ($content === '') {
                continue;
            }
            if ($out && $out[count($out) - 1]['role'] === $role) {
                $out[count($out) - 1]['content'] .= "\n\n" . $content;
                continue;
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        while ($out && $out[0]['role'] === 'assistant') {
            array_shift($out);
        }
        if (!$out) {
            $out[] = ['role' => 'user', 'content' => 'Hola'];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Costos
    // ------------------------------------------------------------------

    public function calculateCost(string $providerId, string $modelId, int $inTokens, int $outTokens): float
    {
        foreach ($this->providerConfig($providerId)['models'] ?? [] as $m) {
            if (($m['id'] ?? '') === $modelId) {
                $costIn = ($inTokens / 1_000_000) * (float)($m['input_cost_per_m'] ?? 0.0);
                $costOut = ($outTokens / 1_000_000) * (float)($m['output_cost_per_m'] ?? 0.0);
                return round($costIn + $costOut, 6);
            }
        }
        return 0.0;
    }

    public function recordUsage(
        string $agentRole,
        ?string $taskId,
        string $sprintId,
        string $provider,
        string $model,
        int $inTokens,
        int $outTokens,
        int $totalTokens,
        float $costUsd,
        string $contextSource
    ): void {
        try {
            $pdo = Database::getPdo();
            $stmt = $pdo->prepare(
                "INSERT INTO ai_token_usage (agent_role, task_id, sprint_id, provider, model, input_tokens, output_tokens, total_tokens, estimated_cost_usd, context_source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $agentRole, $taskId, $sprintId, $provider, $model, $inTokens, $outTokens, $totalTokens, $costUsd, $contextSource
            ]);
        } catch (\Throwable $e) {
            error_log('Error registrando token usage: ' . $e->getMessage());
        }
    }

    public function getUsageSummary(?string $sprintId = null): array
    {
        $pdo = Database::getPdo();
        $sql = "SELECT
                    COUNT(*) as total_calls,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(total_tokens) as total_tokens,
                    SUM(estimated_cost_usd) as total_cost_usd
                FROM ai_token_usage";
        $params = [];
        if ($sprintId !== null) {
            $sql .= " WHERE sprint_id = ?";
            $params[] = $sprintId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $overall = $stmt->fetch() ?: [];

        $byAgentSql = "SELECT agent_role, COUNT(*) as calls, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost_usd
                       FROM ai_token_usage";
        if ($sprintId !== null) {
            $byAgentSql .= " WHERE sprint_id = ?";
            $baStmt = $pdo->prepare($byAgentSql . " GROUP BY agent_role ORDER BY cost_usd DESC");
            $baStmt->execute([$sprintId]);
            $byAgent = $baStmt->fetchAll();
        } else {
            $byAgent = $pdo->query(
                $byAgentSql . " GROUP BY agent_role ORDER BY cost_usd DESC"
            )->fetchAll();
        }

        return [
            'total_calls'         => (int)($overall['total_calls'] ?? 0),
            'total_input_tokens'  => (int)($overall['total_input_tokens'] ?? 0),
            'total_output_tokens' => (int)($overall['total_output_tokens'] ?? 0),
            'total_tokens'        => (int)($overall['total_tokens'] ?? 0),
            'total_cost_usd'      => round((float)($overall['total_cost_usd'] ?? 0.0), 4),
            'by_agent'            => $byAgent,
        ];
    }

    // ------------------------------------------------------------------
    // HTTP
    // ------------------------------------------------------------------

    private function httpPost(string $url, array $payload, array $headers, int $timeout = 120): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("No se pudo conectar con la API del modelo: {$err}");
        }

        $data = json_decode((string)$response, true);
        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? $data['message'] ?? (is_string($data['error'] ?? null) ? $data['error'] : "Error HTTP {$httpCode}");
            if ($httpCode === 401) {
                $msg .= ' (la clave de API no es válida o fue revocada)';
            }
            throw new RuntimeException("Error en la API del proveedor (HTTP {$httpCode}): {$msg}");
        }

        return is_array($data) ? $data : [];
    }
}
