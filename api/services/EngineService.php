<?php
/**
 * AgileForge - EngineService
 * Decide qué motor de IA responde por cada rol y hace la llamada.
 *
 * Orden de prioridad (el primero disponible gana):
 *   1. El motor asignado al rol (si el enrutamiento por rol está activo y ese motor tiene clave / está encendido).
 *      El rol puede tener el proveedor especial "local" = la IA local activa (Ollama o Colibri).
 *   2. La IA local, si está activada.
 *   3. El motor global de "Motor IA", si tiene clave.
 *   4. Ninguno: se informa con honestidad (no se inventan respuestas).
 */

declare(strict_types=1);

namespace AgileForge\Services;

require_once __DIR__ . '/LlmService.php';
require_once __DIR__ . '/LocalAiService.php';

use AgileForge\Repository;
use RuntimeException;

class EngineService
{
    private Repository $repo;
    private LlmService $llm;
    private LocalAiService $local;

    public function __construct(?Repository $repo = null)
    {
        $this->repo = $repo ?? new Repository();
        $this->llm = new LlmService();
        $this->local = new LocalAiService($this->repo);
    }

    public function llm(): LlmService
    {
        return $this->llm;
    }

    /**
     * Describe el motor que usaría un rol, sin hacer llamadas de red.
     * $providers: salida de LlmService::getProviders() (con api_key_configured).
     */
    public static function describe(?array $role, array $aiEngine, array $providers, array $localState): array
    {
        $configured = [];
        $names = [];
        foreach ($providers as $p) {
            $configured[$p['id']] = !empty($p['api_key_configured']);
            $names[$p['id']] = $p['name'] ?? $p['id'];
        }

        $routing = !array_key_exists('role_routing_enabled', $aiEngine) || !empty($aiEngine['role_routing_enabled']);
        $localOn = !empty($localState['enabled']) && !empty($localState['model']);
        $localRuntime = (string)($localState['runtime'] ?? 'ollama');
        $localEngine = [
            'provider' => $localRuntime,
            'model'    => (string)($localState['model'] ?? ''),
            'kind'     => 'local',
            'ready'    => true,
            'label'    => ($localRuntime === 'colibri' ? '🐦 ' : '🦙 ') . ($localState['model'] ?? ''),
        ];

        $roleProv = trim((string)($role['ai_provider'] ?? ''));
        $roleModel = trim((string)($role['ai_model'] ?? ''));
        $skipped = '';

        // 1. Motor propio del rol
        if ($routing && $role && $roleProv !== '') {
            if ($roleProv === 'local') {
                if ($localOn) {
                    return $localEngine + ['source' => 'role', 'reason' => ''];
                }
                $skipped = 'El rol usa la IA local, pero está apagada.';
            } elseif (!empty($configured[$roleProv])) {
                return [
                    'provider' => $roleProv,
                    'model'    => $roleModel,
                    'kind'     => 'api',
                    'ready'    => true,
                    'label'    => $roleModel,
                    'source'   => 'role',
                    'reason'   => '',
                ];
            } else {
                $skipped = "El rol tiene asignado {$roleModel} (" . ($names[$roleProv] ?? $roleProv) . '), pero falta su clave de API.';
            }
        }

        // 2. IA local activada
        if ($localOn) {
            return $localEngine + ['source' => 'local', 'reason' => $skipped];
        }

        // 3. Motor global
        $gProv = (string)($aiEngine['active_provider'] ?? 'anthropic');
        $gModel = (string)($aiEngine['active_model'] ?? '');
        if (!empty($configured[$gProv])) {
            return [
                'provider' => $gProv,
                'model'    => $gModel,
                'kind'     => 'api',
                'ready'    => true,
                'label'    => $gModel,
                'source'   => 'global',
                'reason'   => $skipped,
            ];
        }

        // 4. Sin motor
        $want = ($routing && $roleProv !== '' && $roleProv !== 'local') ? $roleProv : $gProv;
        return [
            'provider' => $want,
            'model'    => ($routing && $roleModel !== '') ? $roleModel : $gModel,
            'kind'     => 'none',
            'ready'    => false,
            'label'    => 'sin motor',
            'source'   => 'none',
            'reason'   => $skipped !== '' ? $skipped : 'No hay ninguna IA disponible: agrega una clave de API en Motor IA o activa la IA local.',
        ];
    }

    /** Motor efectivo para un rol, con datos frescos. */
    public function resolveForRole(?array $role): array
    {
        $aiEngine = $this->repo->getSetting('ai_engine', []);
        $engine = self::describe($role, is_array($aiEngine) ? $aiEngine : [], $this->llm->getProviders(), $this->local->getState());
        if ($engine['kind'] === 'api') {
            $engine['model'] = $this->llm->normalizeModel($engine['provider'], $engine['model']);
            $engine['label'] = $engine['model'];
        }
        return $engine;
    }

    /**
     * Pregunta al motor indicado.
     * $messages: [['role' => 'user'|'assistant', 'content' => '...'], ...]
     */
    public function ask(array $engine, string $systemPrompt, array $messages, array $meta = [], int $maxTokens = 1500): array
    {
        if (($engine['kind'] ?? 'none') === 'none') {
            throw new RuntimeException($engine['reason'] ?? 'No hay motor de IA disponible.');
        }

        $sprintId = (string)($meta['sprint_id'] ?? ($this->repo->getActiveSprint()['id'] ?? 'default'));
        $meta['sprint_id'] = $sprintId;

        if ($engine['kind'] === 'local') {
            $res = $this->local->chat($systemPrompt, $messages, $engine['model'] ?: null, $maxTokens);
            $this->llm->recordUsage(
                (string)($meta['agent_role'] ?? 'system'),
                $meta['task_id'] ?? null,
                $sprintId,
                (string)$engine['provider'],
                (string)($res['model'] ?? $engine['model']),
                (int)($res['input_tokens'] ?? 0),
                (int)($res['output_tokens'] ?? 0),
                (int)($res['total_tokens'] ?? 0),
                0.0,
                (string)($meta['context_source'] ?? 'chat') . '_local'
            );
            $res['provider'] = $engine['provider'];
            return $res;
        }

        return $this->llm->chat($engine['provider'], $engine['model'], $systemPrompt, $messages, [
            'max_tokens' => $maxTokens,
            'metadata'   => $meta,
        ]);
    }

    /** Prompt base de identidad de un agente con memoria nodular opcional. */
    public static function agentSystemPrompt(array $role, array $jurisdiction, string $extra = '', string $memorySnippet = ''): string
    {
        $name = (string)($role['name'] ?? 'Agente');
        $alias = trim((string)($role['alias'] ?? ''));
        $specialty = trim((string)($role['specialty'] ?? ''));
        $skills = $role['assigned_skills'] ?? json_decode((string)($role['assigned_skills_json'] ?? '[]'), true) ?: [];
        $country = (string)($jurisdiction['country_name'] ?? 'Perú');

        $out = "Eres {$name}" . ($alias !== '' ? " ({$alias})" : '') . ', agente del equipo AgileForge. ' .
            ($specialty !== '' ? "Especialidad: {$specialty}. " : '') .
            ($skills ? 'Skills: ' . implode(', ', array_slice($skills, 0, 8)) . '. ' : '') .
            "Trabajas bajo la normativa de {$country}. Responde en el idioma en que te escriben (español por defecto), breve, concreto y orientado a la acción. " .
            'Si no sabes algo o te falta información del proyecto, dilo; no inventes datos.' .
            ($extra !== '' ? ' ' . $extra : '');

        if ($memorySnippet !== '') {
            $out .= "\n\n" . trim($memorySnippet);
        }
        return $out;
    }

    /** Construye el prompt completo del agente resolviendo jurisdicción y memorias nodulares del proyecto. */
    public function buildAgentPrompt(array $role, ?string $projectId = null, string $extra = ''): string
    {
        $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú']);
        $pid = $projectId ?: ($this->repo->getActiveProject()['id'] ?? 'default');
        $roleId = (string)($role['id'] ?? 'all');
        $memSnippet = $this->repo->getCompactMemoriesForPrompt($pid, $roleId, 1500);
        return self::agentSystemPrompt($role, is_array($juris) ? $juris : [], $extra, $memSnippet);
    }
}
