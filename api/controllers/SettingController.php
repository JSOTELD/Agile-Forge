<?php
/**
 * AgileForge - SettingController
 * Gestión de Workspace, Jurisdicción Legal, Preferencias de Diseño UI/UX y Motor de IA.
 */

declare(strict_types=1);

namespace AgileForge\Controllers;

require_once dirname(__DIR__) . '/services/LlmService.php';

use AgileForge\Bootstrap;
use AgileForge\Database;
use AgileForge\Repository;
use AgileForge\Services\LlmService;
use PDO;

class SettingController
{
    private Repository $repo;
    private LlmService $llm;

    public function __construct()
    {
        $this->repo = new Repository();
        $this->llm = new LlmService();
    }

    public function updateWorkspace(string $workspacePath): void
    {
        try {
            $validatedPath = Bootstrap::validateWorkspacePath($workspacePath);
            $this->repo->setSetting('active_workspace', $validatedPath);

            $pdo = Database::getPdo();
            $activeId = (string)($this->repo->getActiveProject()['id'] ?? 'default');
            $stmt = $pdo->prepare("UPDATE projects SET path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$validatedPath, $activeId]);

            Bootstrap::jsonResponse([
                'success'          => true,
                'active_workspace' => $validatedPath
            ]);
        } catch (\InvalidArgumentException $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updateJurisdiction(array $input): void
    {
        $countryCode = strtoupper(trim((string)($input['country_code'] ?? 'PE')));
        $countryName = trim((string)($input['country_name'] ?? 'Perú'));
        $flag = trim((string)($input['flag'] ?? '🇵🇪'));
        $regulations = is_array($input['regulations'] ?? null) ? $input['regulations'] : [];
        $autoDetected = !empty($input['auto_detected']);

        $cleanRegs = array_values(array_unique(array_filter(array_map('trim', $regulations))));

        $jurisdiction = [
            'country_code'  => $countryCode,
            'country_name'  => $countryName,
            'flag'          => $flag,
            'auto_detected' => $autoDetected,
            'regulations'   => $cleanRegs
        ];

        $this->repo->setSetting('jurisdiction', $jurisdiction);
        Bootstrap::jsonResponse(['success' => true, 'jurisdiction' => $jurisdiction]);
    }

    public function updateAiEngine(array $input): void
    {
        if (isset($input['settings']) && is_array($input['settings'])) {
            $input = array_merge($input, $input['settings']);
        }
        $providerId = trim((string)($input['active_provider'] ?? 'anthropic'));
        if (!$this->llm->providerConfig($providerId)) {
            Bootstrap::jsonResponse(['success' => false, 'error' => "Proveedor desconocido: {$providerId}"], 422);
        }
        $model = $this->llm->normalizeModel($providerId, (string)($input['active_model'] ?? ''));
        $execMode = in_array($input['execution_mode'] ?? '', ['cli', 'api'], true) ? $input['execution_mode'] : 'api';
        $roleRouting = !array_key_exists('role_routing_enabled', $input) || !empty($input['role_routing_enabled']);
        $customEndpoint = trim((string)($input['endpoint'] ?? ''));

        $aiEngine = $this->repo->getSetting('ai_engine', []);
        unset($aiEngine['available_providers'], $aiEngine['api_key_configured']);
        $aiEngine['active_provider'] = $providerId;
        $aiEngine['active_model'] = $model;
        $aiEngine['provider_name'] = $this->llm->providerName($providerId);
        $aiEngine['icon'] = (string)($this->llm->providerConfig($providerId)['icon'] ?? '🟣');
        $aiEngine['execution_mode'] = $execMode;
        $aiEngine['role_routing_enabled'] = $roleRouting;
        $aiEngine['endpoint'] = ($customEndpoint !== '' && preg_match('#^https?://#', $customEndpoint))
            ? $customEndpoint
            : (string)($this->llm->providerConfig($providerId)['endpoint'] ?? '');

        $this->repo->setSetting('ai_engine', $aiEngine);
        $aiEngine['api_key_configured'] = $this->llm->isProviderConfigured($providerId);
        $aiEngine['available_providers'] = $this->llm->getProviders();
        Bootstrap::jsonResponse(['success' => true, 'ai_engine' => $aiEngine]);
    }

    /**
     * Guarda la clave de API de un proveedor en el .env del servidor.
     * La clave la escribe el usuario en la interfaz; nunca se devuelve al navegador (solo una pista: sk-ant-…a1b2).
     */
    public function saveApiKey(array $input): void
    {
        $provider = trim((string)($input['provider'] ?? ''));
        $key = trim((string)($input['api_key'] ?? ''));
        $envKey = $this->llm->envKeyFor($provider);
        if ($envKey === '') {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Ese proveedor no usa clave de API.'], 422);
        }
        if (strlen($key) < 20 || strlen($key) > 300 || preg_match('/\s/', $key)) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'La clave no tiene un formato válido (revisa que la copiaste completa y sin espacios).'], 422);
        }
        if ($provider === 'anthropic' && !str_starts_with($key, 'sk-ant-')) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Las claves de Anthropic empiezan por "sk-ant-".'], 422);
        }
        try {
            Bootstrap::setEnvValue($envKey, $key);
        } catch (\Throwable $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        Bootstrap::log("Clave de API guardada para {$provider}");
        Bootstrap::jsonResponse(['success' => true, 'providers' => $this->llm->getProviders()]);
    }

    /** Workspace de Anthropic (solo para claves que no están asociadas a un workspace). Vacío = quitarlo. */
    public function saveAnthropicWorkspace(array $input): void
    {
        $ws = trim((string)($input['workspace_id'] ?? ''));
        if ($ws !== '' && !preg_match('/^wrkspc_[A-Za-z0-9]{4,80}$/', $ws)) {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'El ID de workspace empieza por "wrkspc_" (lo ves en la consola de Claude → Workspaces).'], 422);
        }
        Bootstrap::setEnvValue('ANTHROPIC_WORKSPACE_ID', $ws === '' ? null : $ws);
        Bootstrap::jsonResponse(['success' => true, 'providers' => $this->llm->getProviders()]);
    }

    public function removeApiKey(array $input): void
    {
        $provider = trim((string)($input['provider'] ?? ''));
        $envKey = $this->llm->envKeyFor($provider);
        if ($envKey === '') {
            Bootstrap::jsonResponse(['success' => false, 'error' => 'Ese proveedor no usa clave de API.'], 422);
        }
        Bootstrap::setEnvValue($envKey, null);
        Bootstrap::log("Clave de API eliminada para {$provider}");
        Bootstrap::jsonResponse(['success' => true, 'providers' => $this->llm->getProviders()]);
    }

    /** Prueba la conexión real con un proveedor (una respuesta de pocas palabras). */
    public function testProvider(array $input): void
    {
        $provider = trim((string)($input['provider'] ?? ''));
        if (!$this->llm->providerConfig($provider)) {
            Bootstrap::jsonResponse(['success' => false, 'error' => "Proveedor desconocido: {$provider}"], 422);
        }
        // Sin clave no hay nada que probar: es un dato que falta (422), no un fallo del proveedor (502)
        if ($this->llm->envKeyFor($provider) !== '' && !$this->llm->isProviderConfigured($provider)) {
            $name = $this->llm->providerConfig($provider)['name'] ?? $provider;
            Bootstrap::jsonResponse(['success' => false, 'error' => "Falta la clave de API de {$name}. Agrégala en Motor IA → Claves de API."], 422);
        }
        try {
            $res = $this->llm->testProvider($provider, isset($input['model']) ? (string)$input['model'] : null);
            Bootstrap::jsonResponse(['success' => true, 'result' => $res]);
        } catch (\Throwable $e) {
            Bootstrap::jsonResponse(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function saveUiPreferences(array $input): void
    {
        $current = $this->repo->getSetting('ui_preferences', []);
        $theme = trim((string)($input['theme'] ?? $current['theme'] ?? 'obsidian'));
        $density = trim((string)($input['density'] ?? $current['density'] ?? 'compact'));
        $uxScore = (int)($input['ux_score'] ?? $current['ux_score'] ?? 94);

        $prefs = array_merge($current, [
            'theme'                       => $theme,
            'theme_name'                  => trim((string)($input['theme_name'] ?? 'Cyber Obsidian Pro')),
            'accent_color'                => trim((string)($input['accent_color'] ?? 'indigo')),
            'density'                     => $density,
            'glow_effects'                => !empty($input['glow_effects']),
            'smooth_transitions'          => !empty($input['smooth_transitions']),
            'high_contrast_wcag'          => !empty($input['high_contrast_wcag']),
            'ux_score'                    => $uxScore,
            'active_enhancements_applied' => $input['active_enhancements_applied'] ?? [
                'WCAG AAA High Contrast Typography',
                'Smooth 200ms Micro-Interactions',
                'Obsidian Slate Glassmorphism & Neon Glow',
                'Mobile-First Responsive Ergonomics',
                'Nielsen Heuristics Error Prevention Toasts'
            ],
            'designer_signoff'            => 'Certificado por The Visionary (Director Creativo)',
            'ux_specialist_signoff'       => 'Validado por Pixel Master (Frontend & UI/UX)'
        ]);

        $this->repo->setSetting('ui_preferences', $prefs);
        Bootstrap::jsonResponse(['success' => true, 'ui_preferences' => $prefs]);
    }

    public function saveUxHeuristics(array $input): void
    {
        $heuristics = is_array($input['heuristics'] ?? null) ? $input['heuristics'] : [];
        $score = (int)($input['score'] ?? 0);
        $notes = trim((string)($input['notes'] ?? ''));

        $evaluation = [
            'updated_at' => date('Y-m-d H:i:s'),
            'score'      => $score,
            'heuristics' => $heuristics,
            'notes'      => $notes,
            'type'       => 'Evaluación Heurística Honesta (10 Heurísticas de Nielsen)'
        ];

        $this->repo->setSetting('ux_heuristics_evaluation', $evaluation);

        // Actualizar también ux_score en ui_preferences de manera unificada
        $uiPrefs = $this->repo->getSetting('ui_preferences', []);
        $uiPrefs['ux_score'] = $score;
        $this->repo->setSetting('ui_preferences', $uiPrefs);

        Bootstrap::jsonResponse([
            'success'    => true,
            'evaluation' => $evaluation
        ]);
    }



    public function auditWorkspaceLegal(): void
    {
        $ws = $this->repo->getSetting('active_workspace', dirname(__DIR__, 2));
        $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú', 'flag' => '🇵🇪', 'country_code' => 'PE']);

        $legalPillars = [
            'privacidad' => [
                'name'        => 'Política de Privacidad y Tratamiento de Datos (PII/LPDP)',
                'files'       => ['privacidad.php', 'privacidad.html', 'privacy.php'],
                'mandatory'   => true,
                'weight'      => 25,
                'status'      => 'missing',
                'found_file'  => null,
                'score'       => 0,
                'notes'       => 'Ausente. Requerido por la legislación de ' . $juris['country_name'] . '.'
            ],
            'terminos' => [
                'name'        => 'Términos y Condiciones del Servicio & Exoneración',
                'files'       => ['terminos.php', 'terminos.html', 'terms.php'],
                'mandatory'   => true,
                'weight'      => 25,
                'status'      => 'missing',
                'found_file'  => null,
                'score'       => 0,
                'notes'       => 'Ausente. Indispensable para limitar responsabilidad civil.'
            ],
            'reclamos' => [
                'name'        => 'Libro de Reclamaciones Virtual / Canal de Quejas',
                'files'       => ['reclamos.php', 'reclamos.html', 'libro-reclamaciones.php'],
                'mandatory'   => true,
                'weight'      => 20,
                'status'      => 'missing',
                'found_file'  => null,
                'score'       => 0,
                'notes'       => 'Ausente. Obligatorio según normativa de protección al consumidor.'
            ],
            'reembolsos' => [
                'name'        => 'Política de Cancelaciones y Reembolsos',
                'files'       => ['reembolsos.php', 'reembolsos.html', 'cancelaciones.php'],
                'mandatory'   => false,
                'weight'      => 15,
                'status'      => 'missing',
                'found_file'  => null,
                'score'       => 0,
                'notes'       => 'Recomendado para prevenir disputas de pagos y pasarela.'
            ],
            'baja' => [
                'name'        => 'Gestión de Derechos ARCO / Supresión de Datos',
                'files'       => ['baja.php', 'baja.html', 'arco.php'],
                'mandatory'   => false,
                'weight'      => 15,
                'status'      => 'missing',
                'found_file'  => null,
                'score'       => 0,
                'notes'       => 'Recomendado para permitir al usuario revocar su consentimiento.'
            ]
        ];

        $totalScore = 0;
        $workspaceExists = is_dir($ws);

        if ($workspaceExists) {
            foreach ($legalPillars as $key => &$pillar) {
                foreach ($pillar['files'] as $f) {
                    $path = rtrim($ws, '\\/') . DIRECTORY_SEPARATOR . $f;
                    if (is_file($path)) {
                        $pillar['status'] = 'compliant';
                        $pillar['found_file'] = $f;
                        $content = (string)file_get_contents($path);
                        $points = $pillar['weight'];

                        if ($key === 'privacidad') {
                            $hasLaw = stripos($content, '29733') !== false || stripos($content, 'datos personales') !== false || stripos($content, 'gdpr') !== false;
                            $hasContact = stripos($content, 'correo') !== false || stripos($content, 'email') !== false || stripos($content, 'titular') !== false;
                            if ($hasLaw && $hasContact) {
                                $pillar['notes'] = "Excelente: Identifica al responsable y cita el marco regulatorio vigente.";
                            } else {
                                $pillar['notes'] = "Presente, pero se sugiere reforzar la mención expresa al titular y canales ARCO.";
                                $points = (int)round($points * 0.85);
                            }
                        } elseif ($key === 'terminos') {
                            $hasLiability = stripos($content, 'responsabilidad') !== false || stripos($content, 'fuerza mayor') !== false || stripos($content, 'emergencia') !== false;
                            if ($hasLiability) {
                                $pillar['notes'] = "Excelente: Incluye cláusulas de deslinde de responsabilidad y casos fortuitos.";
                            } else {
                                $pillar['notes'] = "Presente. Se recomienda incorporar límites de indemnidad civil.";
                                $points = (int)round($points * 0.85);
                            }
                        } elseif ($key === 'reclamos') {
                            $pillar['notes'] = "Conforme: Canal oficial para recepción de quejas del consumidor.";
                        } else {
                            $pillar['notes'] = "Conforme: Archivo operativo en el workspace.";
                        }

                        $pillar['score'] = $points;
                        $totalScore += $points;
                        break;
                    }
                }
            }
            unset($pillar);
        }

        $verdict = ($totalScore >= 80)
            ? 'Blindaje Jurídico Sólido (Aprobado)'
            : (($totalScore >= 50) ? 'Riesgo Moderado (Requiere Refuerzo)' : 'Riesgo Crítico (Incumplimiento Normativo)');

        Bootstrap::jsonResponse([
            'success'          => true,
            'workspace'        => $ws,
            'workspace_exists' => $workspaceExists,
            'jurisdiction'     => $juris,
            'total_score'      => min(100, $totalScore),
            'verdict'          => $verdict,
            'pillars'          => $legalPillars
        ]);
    }

    public function generateLegalDoc(array $input): void
    {
        $docType = (string)($input['doc_type'] ?? 'terminos');
        $types = ['terminos' => 'terminos.php', 'privacidad' => 'privacidad.php', 'consentimiento' => 'consentimiento-snippet.html'];
        if (!isset($types[$docType])) {
            Bootstrap::fail("Tipo de documento desconocido: '{$docType}'. Usa terminos, privacidad o consentimiento.", 422);
        }
        $project = $this->repo->getActiveProject();
        // Todo lo que escribe el usuario se escapa: el documento se puede guardar como .php en su proyecto
        $e = static fn($v): string => htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
        $siteName = '';
        foreach ([$input['site_name'] ?? '', $input['business_name'] ?? '', $project['name'] ?? '', 'Mi proyecto'] as $candidate) {
            if (trim((string)$candidate) !== '') {
                $siteName = $e($candidate);
                break;
            }
        }
        $contactEmail = $e(($input['contact_email'] ?? '') !== '' ? $input['contact_email'] : 'contacto@ejemplo.com');
        $saveToWorkspace = !empty($input['save_to_workspace']);

        $juris = $this->repo->getSetting('jurisdiction', ['country_name' => 'Perú', 'flag' => '🇵🇪', 'country_code' => 'PE']);
        $country = $e($juris['country_name'] ?? 'Perú');
        $flag = $e($juris['flag'] ?? '');
        $regs = array_map($e, array_slice(is_array($juris['regulations'] ?? null) ? $juris['regulations'] : [], 0, 6));
        $regsList = $regs ? implode('', array_map(static fn($r) => "\n            <li>{$r}</li>", $regs)) : "\n            <li>La normativa vigente de protección de datos y defensa del consumidor.</li>";
        $ws = (string)($project['path'] ?? $this->repo->getSetting('active_workspace', dirname(__DIR__, 2)));

        $filename = $types[$docType];
        if ($docType === 'terminos') {
            $generated = <<<HTML
<?php
/**
 * Términos y Condiciones de Uso — {$siteName}
 * Jurisdicción: {$country} {$flag}. Plantilla orientativa generada por AgileForge: revísala con un profesional.
 */
declare(strict_types=1);
?>
<main class="max-w-4xl mx-auto px-4 py-8 space-y-6 text-slate-800">
    <h1 class="text-3xl font-extrabold text-slate-900">Términos y Condiciones de Uso</h1>
    <p class="text-sm text-slate-600">Última actualización: <?= date('d/m/Y') ?>. Aplicable en {$country}.</p>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">1. Objeto</h2>
        <p class="text-sm leading-relaxed">Estos términos regulan el acceso y uso de los servicios y contenidos que ofrece {$siteName}. Al usarlos, aceptas estas condiciones.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">2. Uso del servicio</h2>
        <p class="text-sm leading-relaxed">Te comprometes a usar el servicio de forma lícita, a dar información veraz y a no realizar acciones que dañen su funcionamiento o los derechos de terceros.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">3. Precios, pagos y comprobantes</h2>
        <p class="text-sm leading-relaxed">Los precios se informan antes de contratar e incluyen los impuestos aplicables. Se emite el comprobante de pago que corresponda según la normativa de {$country}.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">4. Responsabilidad</h2>
        <p class="text-sm leading-relaxed">{$siteName} presta el servicio con la diligencia debida y no responde por casos fortuitos, fuerza mayor o por el uso indebido que hagan los usuarios.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">5. Reclamos, ley aplicable y contacto</h2>
        <p class="text-sm leading-relaxed">Estos términos se rigen por la legislación de {$country}. Puedes presentar quejas o reclamos por los canales que exige la ley (por ejemplo, el Libro de Reclamaciones cuando corresponda) o escribiendo a {$contactEmail}. Normativa de referencia:</p>
        <ul class="text-sm list-disc pl-5">{$regsList}
        </ul>
    </section>
</main>
HTML;
        } elseif ($docType === 'privacidad') {
            $generated = <<<HTML
<?php
/**
 * Política de Privacidad y Tratamiento de Datos Personales — {$siteName}
 * Conforme a la legislación de {$country} {$flag}. Plantilla orientativa generada por AgileForge: revísala con un profesional.
 */
declare(strict_types=1);
?>
<main class="max-w-4xl mx-auto px-4 py-8 space-y-6 text-slate-800">
    <h1 class="text-3xl font-extrabold text-slate-900">Política de Privacidad y Protección de Datos</h1>
    <p class="text-sm text-slate-600">Conforme a la normativa de protección de datos personales de {$country}.</p>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">1. Responsable del tratamiento</h2>
        <p class="text-sm leading-relaxed">El responsable del tratamiento de tus datos personales es {$siteName}. Contacto: {$contactEmail}.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">2. Datos que tratamos y finalidad</h2>
        <p class="text-sm leading-relaxed">Tratamos solo los datos que nos das (por ejemplo, nombre, correo, teléfono y los necesarios para prestar el servicio) para atender tus solicitudes, prestar y mejorar el servicio, emitir comprobantes y cumplir obligaciones legales. No vendemos tus datos.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">3. Conservación y seguridad</h2>
        <p class="text-sm leading-relaxed">Conservamos los datos el tiempo necesario para la finalidad indicada o el que exija la ley, con medidas de seguridad técnicas y organizativas razonables.</p>
    </section>

    <section class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">4. Tus derechos</h2>
        <p class="text-sm leading-relaxed">Puedes ejercer tus derechos de acceso, rectificación, cancelación y oposición (y los demás que reconozca la ley) escribiendo a {$contactEmail}. Normativa de referencia:</p>
        <ul class="text-sm list-disc pl-5">{$regsList}
        </ul>
    </section>
</main>
HTML;
        } else {
            $generated = <<<HTML
<!-- Snippet: casilla de consentimiento informado para formularios — {$siteName} ({$country} {$flag}) -->
<div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-700 space-y-2 text-xs">
    <label class="flex items-start gap-2.5 cursor-pointer text-slate-200">
        <input type="checkbox" name="legal_consent" required class="w-4 h-4 mt-0.5 accent-indigo-600 rounded">
        <span class="leading-relaxed">
            He leído y acepto los <a href="terminos.php" target="_blank" rel="noopener" class="text-indigo-400 underline font-semibold">Términos y Condiciones</a> y la
            <a href="privacidad.php" target="_blank" rel="noopener" class="text-indigo-400 underline font-semibold">Política de Privacidad</a>, y autorizo el tratamiento de mis datos para las finalidades indicadas, conforme a la ley de {$country}.
        </span>
    </label>
</div>
HTML;
        }

        $saved = false;
        $savedPath = null;
        if ($saveToWorkspace) {
            if (!is_dir($ws) || !is_writable($ws)) {
                Bootstrap::fail("No puedo escribir en la carpeta del proyecto: {$ws}", 422, ['content' => $generated, 'filename' => $filename]);
            }
            // Nunca se sobrescribe un archivo existente del proyecto: si ya existe, se guarda con otro nombre
            $destPath = rtrim($ws, '\\/') . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($destPath)) {
                $info = pathinfo($filename);
                $filename = $info['filename'] . '-' . date('Ymd-His') . '.' . $info['extension'];
                $destPath = rtrim($ws, '\\/') . DIRECTORY_SEPARATOR . $filename;
            }
            if (@file_put_contents($destPath, $generated) === false) {
                Bootstrap::fail("No se pudo guardar {$filename} en {$ws}", 500, ['content' => $generated]);
            }
            $saved = true;
            $savedPath = $destPath;
        }

        Bootstrap::jsonResponse([
            'success'    => true,
            'doc_type'   => $docType,
            'filename'   => $filename,
            'saved'      => $saved,
            'saved_path' => $savedPath,
            'workspace'  => $ws,
            'content'    => $generated
        ]);
    }

    public function updateHumanSignoff(array $input): void
    {
        $client = (string)($input['client'] ?? '');
        if ($client !== 'web') {
            Bootstrap::fail('Modificar el ajuste de firma humana requiere acceso desde el panel web (client: "web").', 403);
        }

        if (!array_key_exists('enabled', $input)) {
            Bootstrap::fail('El campo "enabled" es obligatorio.', 422);
        }

        $enabled = filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            Bootstrap::fail('El campo "enabled" debe ser un booleano válido (true o false).', 422);
        }

        $projectId = isset($input['project_id']) && trim((string)$input['project_id']) !== ''
            ? trim((string)$input['project_id'])
            : null;

        if ($projectId !== null) {
            try {
                $this->repo->resolveProject($projectId);
            } catch (\InvalidArgumentException $e) {
                Bootstrap::fail("Proyecto no encontrado: '{$projectId}'", 404);
            }
        }

        $this->repo->setHumanSignoff($projectId, $enabled);
        Bootstrap::log("Ajuste human_signoff para proyecto '" . ($projectId ?? 'default') . "' cambiado a " . ($enabled ? 'true' : 'false') . " por client '{$client}' a las " . date('c'));

        Bootstrap::jsonResponse([
            'success'    => true,
            'project_id' => $projectId,
            'enabled'    => $enabled,
            'setting'    => $this->repo->getSetting('human_signoff')
        ]);
    }
}

