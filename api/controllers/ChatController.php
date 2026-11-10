<?php
/**
 * AgileForge - ChatController
 * Chat con los agentes. Cada agente responde con su motor (EngineService);
 * el Director además ejecuta acciones sobre el tablero (DirectorService).
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/EngineService.php';
require_once dirname(__DIR__) . '/services/DirectorService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Services\DirectorService;
use AgileForge\Services\EngineService;
use PDO;

class ChatController
{
    private Repository $repo;
    private EngineService $engines;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->engines = new EngineService($this->repo);
    }

    public function sendMessage(array $input): void
    {
        $targetRole = trim((string)($input['target_role'] ?? DirectorService::ROLE_ID));
        $queryText = trim((string)($input['message'] ?? ''));

        if ($queryText === '') {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Mensaje vacío'], 422);
        }
        if (mb_strlen($queryText) > 8000) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'El mensaje es demasiado largo (máximo 8000 caracteres).'], 422);
        }

        $pdo = Database::getPdo();
        $rStmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $rStmt->execute([$targetRole]);
        $agent = $rStmt->fetch();
        // Cierra el cursor: un SELECT a medio leer deja abierta la transacción de SQLite durante toda la
        // llamada a la IA (minutos con IA local) y bloquea las escrituras ("database is locked").
        $rStmt->closeCursor();
        if (!$agent) {
            Bootstrap::jsonResponse(['success' => false, 'error' => "El agente '{$targetRole}' no existe."], 404);
        }

        $time = date('H:i');
        $userMsg = [
            'id'          => 'MSG-' . (int)(microtime(true) * 1000) . '-U-' . bin2hex(random_bytes(3)),
            'sender_role' => 'user',
            'sender_name' => 'Tú (Líder de Producto)',
            'avatar'      => '👤',
            'timestamp'   => $time,
            'text'        => $queryText,
            'meta'        => ['target' => $targetRole],
        ];
        // Se guarda primero: así las consultas que haga el Director quedan después en el historial
        $this->insert($userMsg, null, null);

        if (!empty($input['stream'])) {
            $this->streamMessage($userMsg, $agent, $targetRole, $queryText);
            return;
        }

        $meta = [];
        $model = null;
        $provider = null;

        if ($targetRole === DirectorService::ROLE_ID) {
            try {
                $director = new DirectorService($this->repo);
                $res = $director->run($queryText);
                $replyText = $res['reply'];
                $model = $res['engine']['model'];
                $provider = $res['engine']['provider'];
                $meta = [
                    'actions'  => array_values(array_filter($res['actions'], fn($a) => $a['visible'])),
                    'tokens'   => $res['tokens'],
                    'cost_usd' => $res['cost_usd'],
                    'source'   => $res['engine']['source'],
                ];
            } catch (\Throwable $e) {
                Bootstrap::log('Director: ' . $e->getMessage());
                $replyText = '⚠️ El Director no pudo trabajar: ' . $e->getMessage();
                $meta = ['error' => true];
            }
        } else {
            $engine = $this->engines->resolveForRole($agent);
            $model = $engine['model'];
            $provider = $engine['provider'];
            if (!$engine['ready']) {
                $replyText = '⚠️ ' . $engine['reason'] . "\n\nOpciones: agrega una clave de API en Motor IA → Claves de API, activa la IA local (botón 🦙 de la barra superior), o trabaja con este agente desde Claude Code con el plugin (/tomar).";
                $meta = ['error' => true];
                $model = null;
            } else {
                try {
                    $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú']);
                    $pid = (string)($this->repo->getActiveProject()['id'] ?? 'default');
                    $memSnippet = $this->repo->getCompactMemoriesForPrompt($pid, $targetRole, 1500);
                    $system = EngineService::agentSystemPrompt($agent, is_array($juris) ? $juris : [], '', $memSnippet);
                    $history = $this->recentHistory($targetRole);
                    $history[] = ['role' => 'user', 'content' => $queryText];
                    $res = $this->engines->ask($engine, $system, $history, ['agent_role' => $targetRole, 'context_source' => 'chat'], 1500);
                    $replyText = $res['text'] !== '' ? $res['text'] : '(El modelo respondió vacío.)';
                    $model = $res['model'] ?? $model;
                    $meta = ['tokens' => $res['total_tokens'] ?? null, 'cost_usd' => $res['estimated_cost_usd'] ?? 0, 'source' => $engine['source']];
                    if (!empty($engine['reason'])) {
                        $meta['note'] = $engine['reason'];
                    }
                } catch (\Throwable $e) {
                    Bootstrap::log("Chat {$targetRole}: " . $e->getMessage());
                    $hint = $engine['kind'] === 'local'
                        ? "\n\nRevisa que Ollama o Colibri estén encendidos, o desactiva la IA local con el botón 🦙."
                        : '';
                    $replyText = "⚠️ {$agent['name']} no pudo responder ({$engine['label']}): " . $e->getMessage() . $hint;
                    $meta = ['error' => true];
                }
            }
        }

        $agentMsg = [
            'id'          => 'MSG-' . (int)(microtime(true) * 1000 + 1) . '-A-' . bin2hex(random_bytes(3)),
            'sender_role' => $targetRole,
            'sender_name' => $agent['name'],
            'avatar'      => $agent['avatar'] ?: '🤖',
            'timestamp'   => date('H:i'),
            'text'        => $replyText,
            'ai_model'    => $model,
            'ai_provider' => $provider,
            'meta'        => $meta,
        ];
        if (empty($meta['error'])) {
            $this->insert($agentMsg, $model, $provider);
        }

        Bootstrap::jsonResponse([
            'success'  => empty($meta['error']),
            'messages' => [$userMsg, $agentMsg],
            'error'    => !empty($meta['error']) ? $replyText : null,
        ]);
    }

    private function insert(array $m, ?string $model, ?string $provider): void
    {
        Database::getPdo()->prepare(
            "INSERT INTO chat_messages (id, sender_role, sender_name, avatar, timestamp, text, ai_model, ai_provider, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $m['id'], $m['sender_role'], $m['sender_name'], $m['avatar'], $m['timestamp'], $m['text'],
            $model, $provider, !empty($m['meta']) ? json_encode($m['meta'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
        ]);
    }

    private function streamMessage(array $userMsg, array $agent, string $targetRole, string $queryText): void
    {
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        while (@ob_end_flush());
        @ob_implicit_flush(1);

        $sendEvent = static function (string $event, array $data): void {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n";
            @flush();
        };

        $sendEvent('start', ['user_message' => $userMsg]);

        $meta = [];
        $model = null;
        $provider = null;
        $replyText = '';

        if ($targetRole === DirectorService::ROLE_ID) {
            try {
                $director = new DirectorService($this->repo);
                $res = $director->run($queryText);
                $replyText = $res['reply'];
                $model = $res['engine']['model'];
                $provider = $res['engine']['provider'];
                $meta = [
                    'actions'  => array_values(array_filter($res['actions'], fn($a) => $a['visible'])),
                    'tokens'   => $res['tokens'],
                    'cost_usd' => $res['cost_usd'],
                    'source'   => $res['engine']['source'],
                ];
            } catch (\Throwable $e) {
                Bootstrap::log('Director Stream: ' . $e->getMessage());
                $replyText = '⚠️ El Director no pudo trabajar: ' . $e->getMessage();
                $meta = ['error' => true];
            }
        } else {
            $engine = $this->engines->resolveForRole($agent);
            $model = $engine['model'];
            $provider = $engine['provider'];
            if (!$engine['ready']) {
                $replyText = '⚠️ ' . $engine['reason'];
                $meta = ['error' => true];
            } else {
                try {
                    $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú']);
                    $pid = (string)($this->repo->getActiveProject()['id'] ?? 'default');
                    $memSnippet = $this->repo->getCompactMemoriesForPrompt($pid, $targetRole, 1500);
                    $system = EngineService::agentSystemPrompt($agent, is_array($juris) ? $juris : [], '', $memSnippet);
                    $history = $this->recentHistory($targetRole);
                    $history[] = ['role' => 'user', 'content' => $queryText];
                    $res = $this->engines->ask($engine, $system, $history, ['agent_role' => $targetRole, 'context_source' => 'chat_stream'], 1500);
                    $replyText = $res['text'] !== '' ? $res['text'] : '(El modelo respondió vacío.)';
                    $model = $res['model'] ?? $model;
                    $meta = ['tokens' => $res['total_tokens'] ?? null, 'cost_usd' => $res['estimated_cost_usd'] ?? 0, 'source' => $engine['source']];
                } catch (\Throwable $e) {
                    Bootstrap::log("Chat Stream {$targetRole}: " . $e->getMessage());
                    $replyText = "⚠️ {$agent['name']} no pudo responder: " . $e->getMessage();
                    $meta = ['error' => true];
                }
            }
        }

        // Emitir el texto en chunks vía SSE
        $chunks = preg_split('/(?<=[ \n\.\,\!\?])/u', $replyText, -1, PREG_SPLIT_NO_EMPTY) ?: [$replyText];
        foreach ($chunks as $chunk) {
            $sendEvent('delta', ['text' => $chunk]);
            usleep(15000);
        }

        $agentMsg = [
            'id'          => 'MSG-' . (int)(microtime(true) * 1000 + 1) . '-A-' . bin2hex(random_bytes(3)),
            'sender_role' => $targetRole,
            'sender_name' => $agent['name'],
            'avatar'      => $agent['avatar'] ?: '🤖',
            'timestamp'   => date('H:i'),
            'text'        => $replyText,
            'ai_model'    => $model,
            'ai_provider' => $provider,
            'meta'        => $meta,
        ];

        if (empty($meta['error'])) {
            $this->insert($agentMsg, $model, $provider);
        }

        $sendEvent('done', [
            'success'       => empty($meta['error']),
            'agent_message' => $agentMsg,
            'error'         => !empty($meta['error']) ? $replyText : null
        ]);
        exit;
    }

    /**
     * Últimos mensajes de la conversación con este agente (máx. 6), sin contar el que se acaba de guardar.
     */
    private function recentHistory(string $targetRole): array
    {
        $stmt = Database::getPdo()->prepare(
            "SELECT sender_role, sender_name, text FROM (
                SELECT rowid AS _rid, * FROM chat_messages
                WHERE sender_role = ? OR (sender_role = 'user' AND (meta_json IS NULL OR meta_json LIKE ?))
                ORDER BY created_at DESC, rowid DESC LIMIT 7
             ) ORDER BY _rid ASC"
        );
        $stmt->execute([$targetRole, '%"target":"' . $targetRole . '"%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        array_pop($rows); // el mensaje del usuario recién insertado

        $out = [];
        foreach ($rows as $r) {
            $text = (string)($r['text'] ?? '');
            if ($text === '' || str_starts_with($text, 'ℹ️ Modo Plugin') || str_starts_with($text, '⚠️')) {
                continue;
            }
            $out[] = ['role' => ($r['sender_role'] ?? '') === 'user' ? 'user' : 'assistant', 'content' => mb_substr($text, 0, 1500)];
        }
        return $out;
    }
}
