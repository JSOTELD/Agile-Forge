<?php
/**
 * AgileForge - LlmService (Multi-LLM Hub & Token/Cost Accounting)
 * Soporta Anthropic, OpenAI, Google Gemini, DeepSeek y Ollama local.
 * Llamadas server-side con API Keys en .env (nunca expuestas al cliente) y contabilidad real de costos.
 */

declare(strict_types=1);

namespace AgileForge\Services;

use AgileForge\Database;
use PDO;
use RuntimeException;

class LlmService
{
    private array $config;
    private string $configPath;

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

    /**
     * Devuelve el catálogo de proveedores con el estado real de api_key_configured
     */
    public function getProviders(): array
    {
        $providers = $this->config['providers'] ?? [];
        $out = [];

        foreach ($providers as $id => $p) {
            $envKey = $p['env_key'] ?? '';
            $isConfigured = false;

            if ($id === 'ollama') {
                $isConfigured = $this->checkOllamaStatus($p['endpoint'] ?? 'http://localhost:11434');
            } elseif ($envKey !== '') {
                $keyVal = $_ENV[$envKey] ?? getenv($envKey) ?: '';
                $isConfigured = ($keyVal !== '' && strlen($keyVal) > 5 && !str_starts_with($keyVal, 'tu_clave'));
            }

            $p['api_key_configured'] = $isConfigured;
            $out[] = $p;
        }

        return $out;
    }

    public function isProviderConfigured(string $providerId): bool
    {
        $providers = $this->config['providers'] ?? [];
        $p = $providers[$providerId] ?? null;
        if (!$p) return false;

        if ($providerId === 'ollama') {
            return $this->checkOllamaStatus($p['endpoint'] ?? 'http://localhost:11434');
        }

        $envKey = $p['env_key'] ?? '';
        if ($envKey === '') return false;
        $val = $_ENV[$envKey] ?? getenv($envKey) ?: '';
        return ($val !== '' && strlen($val) > 5 && !str_starts_with($val, 'tu_clave'));
    }

    private function checkOllamaStatus(string $endpoint): bool
    {
        $ch = curl_init(rtrim($endpoint, '/') . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode === 200);
    }

    /**
     * Ejecuta una llamada real al LLM configurado.
     * Si no hay API key configurada, lanza RuntimeException informando el estado "sin motor".
     */
    public function complete(
        string $providerId,
        string $modelId,
        string $prompt,
        string $systemPrompt = '',
        array $metadata = []
    ): array {
        if (!$this->isProviderConfigured($providerId)) {
            $providers = $this->config['providers'] ?? [];
            $envKey = $providers[$providerId]['env_key'] ?? 'API_KEY';
            throw new RuntimeException(
                "Sin motor configurado: No se ha detectado una API Key válida para '{$providerId}' ({$envKey}) en el archivo .env. " .
                "Agrega tu clave en .env para llamadas server-side, o interactúa directamente con el rol a través del plugin en Claude Code o MCP."
            );
        }

        $providers = $this->config['providers'] ?? [];
        $provider = $providers[$providerId] ?? null;
        if (!$provider) {
            throw new RuntimeException("Proveedor no reconocido: '{$providerId}'");
        }

        $envKey = $provider['env_key'] ?? '';
        $apiKey = ($envKey !== '') ? ($_ENV[$envKey] ?? getenv($envKey) ?: '') : '';

        $replyText = '';
        $inputTokens = 0;
        $outputTokens = 0;

        switch ($providerId) {
            case 'anthropic':
                $payload = [
                    'model'      => $modelId,
                    'max_tokens' => 2048,
                    'system'     => $systemPrompt,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ];
                $headers = [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01'
                ];
                $res = $this->httpPost($provider['endpoint'], $payload, $headers);
                $replyText = $res['content'][0]['text'] ?? '';
                $inputTokens = (int)($res['usage']['input_tokens'] ?? 0);
                $outputTokens = (int)($res['usage']['output_tokens'] ?? 0);
                break;

            case 'openai':
            case 'deepseek':
                $messages = [];
                if ($systemPrompt !== '') {
                    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
                }
                $messages[] = ['role' => 'user', 'content' => $prompt];

                $payload = [
                    'model'    => $modelId,
                    'messages' => $messages,
                ];
                $headers = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ];
                $res = $this->httpPost($provider['endpoint'], $payload, $headers);
                $replyText = $res['choices'][0]['message']['content'] ?? '';
                $inputTokens = (int)($res['usage']['prompt_tokens'] ?? 0);
                $outputTokens = (int)($res['usage']['completion_tokens'] ?? 0);
                break;

            case 'google':
                $url = rtrim($provider['endpoint'], '/') . "/models/{$modelId}:generateContent?key=" . urlencode($apiKey);
                $payload = [
                    'contents' => [
                        ['parts' => [['text' => ($systemPrompt ? "{$systemPrompt}\n\n" : "") . $prompt]]]
                    ]
                ];
                $res = $this->httpPost($url, $payload, ['Content-Type: application/json']);
                $replyText = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $inputTokens = (int)($res['usageMetadata']['promptTokenCount'] ?? 0);
                $outputTokens = (int)($res['usageMetadata']['candidatesTokenCount'] ?? 0);
                break;

            case 'ollama':
                $payload = [
                    'model'  => $modelId,
                    'prompt' => ($systemPrompt ? "{$systemPrompt}\n\n" : "") . $prompt,
                    'stream' => false
                ];
                $res = $this->httpPost($provider['endpoint'], $payload, ['Content-Type: application/json']);
                $replyText = $res['response'] ?? '';
                $inputTokens = (int)($res['prompt_eval_count'] ?? 0);
                $outputTokens = (int)($res['eval_count'] ?? 0);
                break;

            default:
                throw new RuntimeException("Proveedor no soportado: '{$providerId}'");
        }

        $totalTokens = $inputTokens + $outputTokens;
        $costUsd = $this->calculateCost($providerId, $modelId, $inputTokens, $outputTokens);

        // Registrar uso en SQLite
        $this->recordUsage(
            $metadata['agent_role'] ?? 'system',
            $metadata['task_id'] ?? null,
            $metadata['sprint_id'] ?? 'default',
            $providerId,
            $modelId,
            $inputTokens,
            $outputTokens,
            $totalTokens,
            $costUsd,
            $metadata['context_source'] ?? 'chat'
        );

        return [
            'text'               => $replyText,
            'provider'           => $providerId,
            'model'              => $modelId,
            'input_tokens'       => $inputTokens,
            'output_tokens'      => $outputTokens,
            'total_tokens'       => $totalTokens,
            'estimated_cost_usd' => $costUsd
        ];
    }

    private function calculateCost(string $providerId, string $modelId, int $inTokens, int $outTokens): float
    {
        $providers = $this->config['providers'] ?? [];
        $models = $providers[$providerId]['models'] ?? [];

        $modelConfig = null;
        foreach ($models as $m) {
            if ($m['id'] === $modelId) {
                $modelConfig = $m;
                break;
            }
        }

        if (!$modelConfig) {
            return 0.0;
        }

        $inPricePerM = (float)($modelConfig['input_cost_per_m'] ?? 0.0);
        $outPricePerM = (float)($modelConfig['output_cost_per_m'] ?? 0.0);

        $costIn = ($inTokens / 1_000_000) * $inPricePerM;
        $costOut = ($outTokens / 1_000_000) * $outPricePerM;

        return round($costIn + $costOut, 6);
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

        // Por agente
        $byAgentStmt = $pdo->query(
            "SELECT agent_role, COUNT(*) as calls, SUM(total_tokens) as tokens, SUM(estimated_cost_usd) as cost_usd 
             FROM ai_token_usage GROUP BY agent_role ORDER BY cost_usd DESC"
        );
        $byAgent = $byAgentStmt->fetchAll();

        return [
            'total_calls'        => (int)($overall['total_calls'] ?? 0),
            'total_input_tokens' => (int)($overall['total_input_tokens'] ?? 0),
            'total_output_tokens'=> (int)($overall['total_output_tokens'] ?? 0),
            'total_tokens'       => (int)($overall['total_tokens'] ?? 0),
            'total_cost_usd'     => round((float)($overall['total_cost_usd'] ?? 0.0), 4),
            'by_agent'           => $byAgent
        ];
    }

    private function httpPost(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("Fallo en llamada HTTP a la API del modelo: {$err}");
        }

        $data = json_decode((string)$response, true);
        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? $data['message'] ?? "Error HTTP {$httpCode}";
            throw new RuntimeException("Error en la API del proveedor (HTTP {$httpCode}): {$msg}");
        }

        return is_array($data) ? $data : [];
    }
}
