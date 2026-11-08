<?php
/**
 * AgileForge - ChatController
 * Mensajería y canal de consulta con los agentes del equipo.
 * Soporta llamadas reales a LLMs o notificación honesta de 'sin motor' / 'modo plugin'.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/LlmService.php';
require_once dirname(__DIR__) . '/services/LocalAiService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Services\LlmService;
use AgileForge\Services\LocalAiService;
use PDO;

class ChatController
{
    private Repository $repo;
    private LlmService $llm;
    private LocalAiService $local;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->llm = new LlmService();
        $this->local = new LocalAiService($this->repo);
    }

    public function sendMessage(array $input): void
    {
        $targetRole = trim((string)($input['target_role'] ?? 'project_manager'));
        $queryText = trim((string)($input['message'] ?? ''));

        if ($queryText === '') {
            Bootstrap::jsonResponse(['error' => 'Mensaje vacío'], 422);
        }

        $pdo = Database::getPdo();

        $randSuffix = bin2hex(random_bytes(3));
        $userMsgId = 'MSG-' . (int)(microtime(true) * 1000) . '-U-' . $randSuffix;
        $userTime = date('H:i');
        $userMsg = [
            'id'          => $userMsgId,
            'sender_role' => 'user',
            'sender_name' => 'Tú (Líder de Producto)',
            'avatar'      => '👤',
            'timestamp'   => $userTime,
            'text'        => $queryText
        ];

        // Buscar información del rol
        $rStmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $rStmt->execute([$targetRole]);
        $agent = $rStmt->fetch();

        $agentName = $agent ? $agent['name'] : 'Agente IA';
        $agentAvatar = $agent ? $agent['avatar'] : '🤖';

        $juris = $this->repo->getSetting('jurisdiction', [
            'country_name' => 'Perú',
            'flag'         => '🇵🇪',
            'regulations'  => ['Ley N° 29733 (Protección de Datos Personales)']
        ]);
        $aiEngine = $this->repo->getSetting('ai_engine', [
            'active_provider'      => 'anthropic',
            'active_model'         => 'claude-sonnet-5-5',
            'execution_mode'       => 'cli',
            'role_routing_enabled' => false
        ]);

        $roleRouting = !empty($aiEngine['role_routing_enabled']);
        $providerUsed = ($roleRouting && !empty($agent['ai_provider'])) ? $agent['ai_provider'] : ($aiEngine['active_provider'] ?? 'anthropic');
        $modelUsed = ($roleRouting && !empty($agent['ai_model'])) ? $agent['ai_model'] : ($aiEngine['active_model'] ?? 'claude-sonnet-5-5');
        $execMode = $aiEngine['execution_mode'] ?? 'cli';

        $replyText = '';
        $tokensUsed = null;
        $costUsd = 0.0;
        $hasEngine = false;

        $localState = $this->local->getState();
        $useLocal = !empty($localState['enabled']) && !empty($localState['model']);

        if ($useLocal) {
            // IA LOCAL ACTIVADA: responde el modelo de Ollama en esta PC, sin enviar nada a la nube
            $providerUsed = (string)($localState['runtime'] ?? 'ollama');
            $modelUsed = (string)$localState['model'];
            $alias = $agent['alias'] ?? '';
            $specialty = $agent['specialty'] ?? '';
            $systemPrompt = "Eres {$agentName}" . ($alias !== '' ? " ({$alias})" : '') . ", agente del equipo AgileForge. " .
                ($specialty !== '' ? "Especialidad: {$specialty}. " : '') .
                "Trabajas bajo la normativa de {$juris['country_name']}. Responde siempre en español, breve, concreto y orientado a la acción. " .
                "Si no sabes algo, dilo. No inventes datos del proyecto.";

            try {
                $history = $this->recentHistory($targetRole, $agentName);
                $history[] = ['role' => 'user', 'content' => $queryText];
                $callRes = $this->local->chat($systemPrompt, $history, $modelUsed);
                $replyText = $callRes['text'];
                $tokensUsed = $callRes['total_tokens'];
                $costUsd = 0.0;
                $hasEngine = true;
                $this->llm->recordUsage($targetRole, null, 'default', $providerUsed, $modelUsed,
                    $callRes['input_tokens'], $callRes['output_tokens'], $callRes['total_tokens'], 0.0, 'chat_local');
            } catch (\Throwable $e) {
                $replyText = "⚠️ La IA local está activada, pero no respondió: " . $e->getMessage() .
                    "\n\nAbre Ollama (menú Inicio) o inicia Colibri (bin\\colibri-iniciar.cmd), o desactiva la IA local con el botón 🦙 de la barra superior.";
                $hasEngine = false;
            }
        } elseif ($execMode === 'api') {
            // Modo API: Intentar llamada server-side con API Key
            if ($this->llm->isProviderConfigured($providerUsed)) {
                try {
                    $systemPrompt = "Eres {$agentName} ({$agent['alias']}) en AgileForge. Especialidad: {$agent['specialty']}. " .
                        "Operas bajo la normativa de {$juris['country_name']} {$juris['flag']}. Responde con brevedad, rigor técnico y orientación a resultados Scrum.";

                    $callRes = $this->llm->complete(
                        $providerUsed,
                        $modelUsed,
                        $queryText,
                        $systemPrompt,
                        ['agent_role' => $targetRole, 'context_source' => 'chat']
                    );

                    $replyText = $callRes['text'];
                    $tokensUsed = $callRes['total_tokens'];
                    $costUsd = $callRes['estimated_cost_usd'];
                    $hasEngine = true;
                } catch (\Throwable $e) {
                    $replyText = "⚠️ Error al conectar con la API de {$providerUsed}: " . $e->getMessage();
                }
            } else {
                // Sin clave configurada en .env
                $replyText = "⚠️ Sin motor configurado: Este rol está configurado con {$providerUsed} ({$modelUsed}), pero no se ha encontrado una API Key válida en el archivo .env.\n\n" .
                    "Para habilitar respuestas server-side, añade la clave correspondiente en tu archivo .env, o ejecuta la misión del agente directamente en Claude Code / MCP.";
                $hasEngine = false;
            }
        } else {
            // Modo Plugin (por defecto)
            $replyText = "ℹ️ Modo Plugin Activo: El trabajo de este agente se delega a la IA anfitriona (Claude Code u otra vía MCP). " .
                "Para interactuar con {$agentName}, invócalo en tu terminal mediante el comando /tomar o ejecuta la herramienta correspondiente en el MCP Server.";
            $hasEngine = true;
        }

        $agentMsgId = 'MSG-' . (int)(microtime(true) * 1000 + 1) . '-A-' . bin2hex(random_bytes(3));
        $agentMsg = [
            'id'          => $agentMsgId,
            'sender_role' => $targetRole,
            'sender_name' => $agentName,
            'avatar'      => $agentAvatar,
            'ai_model'    => $modelUsed,
            'ai_provider' => $providerUsed,
            'timestamp'   => $userTime,
            'text'        => $replyText,
            'has_engine'  => $hasEngine,
            'tokens_used' => $tokensUsed,
            'cost_usd'    => $costUsd
        ];

        Database::transaction(function (PDO $pdo) use ($userMsg, $agentMsg) {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (id, sender_role, sender_name, avatar, timestamp, text, ai_model, ai_provider) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userMsg['id'], $userMsg['sender_role'], $userMsg['sender_name'], $userMsg['avatar'], $userMsg['timestamp'], $userMsg['text'], null, null]);
            $stmt->execute([$agentMsg['id'], $agentMsg['sender_role'], $agentMsg['sender_name'], $agentMsg['avatar'], $agentMsg['timestamp'], $agentMsg['text'], $agentMsg['ai_model'], $agentMsg['ai_provider']]);
        });

        Bootstrap::jsonResponse(['success' => true, 'messages' => [$userMsg, $agentMsg]]);
    }

    /**
     * Últimos mensajes del chat como contexto (máx. 6). Los de otros agentes se marcan con su nombre.
     */
    private function recentHistory(string $targetRole, string $agentName): array
    {
        $pdo = Database::getPdo();
        $rows = $pdo->query(
            "SELECT sender_role, sender_name, text FROM (
                SELECT rowid AS _rid, * FROM chat_messages ORDER BY created_at DESC, rowid DESC LIMIT 6
             ) ORDER BY _rid ASC"
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $text = (string)($r['text'] ?? '');
            if ($text === '' || str_starts_with($text, 'ℹ️ Modo Plugin') || str_starts_with($text, '⚠️')) {
                continue;
            }
            if (($r['sender_role'] ?? '') === 'user') {
                $out[] = ['role' => 'user', 'content' => $text];
            } elseif (($r['sender_role'] ?? '') === $targetRole) {
                $out[] = ['role' => 'assistant', 'content' => $text];
            } else {
                $out[] = ['role' => 'user', 'content' => '[Comentario de ' . ($r['sender_name'] ?? 'otro agente') . ']: ' . $text];
            }
        }
        return $out;
    }
}
