<?php
/**
 * AgileForge - LocalAiController
 * Detección y activación/desactivación de la IA local (Ollama) desde la web.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/LocalAiService.php';

use AgileForge\Bootstrap;
use AgileForge\Services\LocalAiService;

class LocalAiController
{
    private LocalAiService $local;

    public function __construct()
    {
        $this->local = new LocalAiService();
    }

    public function detect(): void
    {
        Bootstrap::jsonResponse(['success' => true, 'local_ai' => $this->local->detect()]);
    }

    public function toggle(array $input): void
    {
        $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $model = isset($input['model']) ? trim((string)$input['model']) : null;
        $runtime = isset($input['runtime']) ? trim((string)$input['runtime']) : null;
        try {
            $state = $this->local->setEnabled($enabled, $model, $runtime ?: null);
            Bootstrap::jsonResponse(['success' => true, 'local_ai' => $state]);
        } catch (\Throwable $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage(), 'local_ai' => $this->local->getState()], 409);
        }
    }

    public function update(array $input): void
    {
        $model = trim((string)($input['model'] ?? ''));
        $numCtx = isset($input['num_ctx']) ? (int)$input['num_ctx'] : null;
        $runtime = isset($input['runtime']) ? trim((string)$input['runtime']) : null;
        if ($model === '') {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Falta el modelo'], 422);
        }
        try {
            Bootstrap::jsonResponse(['success' => true, 'local_ai' => $this->local->setModel($model, $numCtx, $runtime ?: null)]);
        } catch (\Throwable $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Permiso para que AgileForge encienda/apague Colibri cuando la IA local lo necesite. */
    public function settings(array $input): void
    {
        if (!array_key_exists('colibri_control', $input)) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Nada que actualizar'], 422);
        }
        $allowed = filter_var($input['colibri_control'], FILTER_VALIDATE_BOOLEAN);
        Bootstrap::jsonResponse(['success' => true, 'local_ai' => $this->local->setColibriControl($allowed)]);
    }

    /** Prueba rápida: una respuesta corta del modelo activo. */
    public function test(): void
    {
        try {
            $res = $this->local->chat(
                'Responde en español, en una sola frase corta.',
                [['role' => 'user', 'content' => 'Confirma que estás funcionando localmente.']]
            );
            Bootstrap::jsonResponse(['success' => true, 'result' => $res]);
        } catch (\Throwable $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }
}
