<?php
/**
 * AgileForge - Bootstrap & Security Middleware
 * Autenticación por token, validación de Host/Origin, Content-Type y protección de Workspace.
 */

declare(strict_types=1);

namespace AgileForge;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Repository.php';

class Bootstrap
{
    private static ?string $apiToken = null;
    private static ?string $viewToken = null;
    private static bool $envLoaded = false;
    private static bool $viewTokenTried = false;

    public static function init(): void
    {
        self::registerErrorHandlers();

        // Headers universales de seguridad y JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');

        self::loadEnv();
        self::verifyHostAndOrigin();
        self::checkRateLimit();
    }

    /**
     * Cualquier error no controlado se devuelve como JSON (nunca HTML ni cuerpo vacío),
     * y queda registrado en data/logs/agileforge.log para poder diagnosticarlo.
     */
    public static function registerErrorHandlers(): void
    {
        if (php_sapi_name() !== 'cli') {
            ini_set('display_errors', '0');
        }

        set_exception_handler(function (\Throwable $e): void {
            self::log('EXCEPTION ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error'   => 'Error interno del servidor: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        });

        register_shutdown_function(function (): void {
            $err = error_get_last();
            if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            self::log('FATAL ' . $err['message'] . ' @ ' . basename((string)$err['file']) . ':' . $err['line']);
            $msg = $err['message'];
            if (str_contains($msg, 'Maximum execution time')) {
                $msg = 'La operación tardó demasiado y el servidor la cortó. Si usas IA local, prueba con un modelo más rápido o menos participantes.';
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        });
    }

    /** Registro de diagnóstico en data/logs/agileforge.log (la carpeta data/ no es accesible por web). */
    public static function log(string $message): void
    {
        $dir = dirname(__DIR__) . '/data/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/agileforge.log';
        if (is_file($file) && filesize($file) > 2_000_000) {
            @rename($file, $dir . '/agileforge.old.log');
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** Valor de una variable del .env (o del entorno del sistema). */
    public static function env(string $key, string $default = ''): string
    {
        if (!self::$envLoaded) {
            self::loadEnv();
        }
        $val = $_ENV[$key] ?? getenv($key);
        return ($val === false || $val === null || $val === '') ? $default : (string)$val;
    }

    /**
     * Escribe (o borra con null) una variable en el .env de forma atómica.
     * Se usa para guardar claves de API que el usuario pega en la interfaz; nunca se devuelven al navegador.
     */
    public static function setEnvValue(string $key, ?string $value): void
    {
        if (!preg_match('/^[A-Z][A-Z0-9_]{2,63}$/', $key)) {
            throw new \InvalidArgumentException('Nombre de variable no válido.');
        }
        if ($value !== null && (preg_match('/[\r\n"\'\\\\]/', $value) || strlen($value) > 400)) {
            throw new \InvalidArgumentException('El valor contiene caracteres no permitidos.');
        }

        $envFile = dirname(__DIR__) . '/.env';
        $lines = is_file($envFile) ? (file($envFile, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $out = [];
        $found = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*#?\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                if (!$found && $value !== null) {
                    $out[] = $key . '=' . $value;
                }
                $found = true;
                continue;
            }
            $out[] = $line;
        }
        if (!$found && $value !== null) {
            $out[] = $key . '=' . $value;
        }

        // Contiene claves de API y tokens: permisos 0600 también al reemplazarlo (en Windows chmod no hace nada)
        $tmp = $envFile . '.tmp';
        if (self::writePrivateFile($tmp, implode("\n", $out) . "\n") === false || !@rename($tmp, $envFile)) {
            @unlink($tmp);
            throw new \RuntimeException('No se pudo escribir el archivo .env');
        }
        @chmod($envFile, 0600);

        if ($value === null) {
            unset($_ENV[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $value;
        }
    }

    /** Escribe un archivo que solo debe leer su propietario (0600). umask 077 evita una ventana con permisos más abiertos. */
    private static function writePrivateFile(string $file, string $content)
    {
        $old = umask(0077);
        try {
            $n = file_put_contents($file, $content, LOCK_EX);
        } finally {
            umask($old);
        }
        @chmod($file, 0600);
        return $n;
    }

    public static function checkRateLimit(int $maxPerMinute = 180): void
    {
        // No aplicar límite en pruebas directas por CLI
        if (php_sapi_name() === 'cli') {
            return;
        }

        if (getenv('AGILEFORGE_RATE_LIMIT') && is_numeric(getenv('AGILEFORGE_RATE_LIMIT'))) {
            $maxPerMinute = (int)getenv('AGILEFORGE_RATE_LIMIT');
        } elseif (getenv('AGILEFORGE_DB_PATH')) {
            $maxPerMinute = 1000;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $now = time();
        $minute = (int)($now / 60);

        // Almacenar conteo en archivo de cache temporal
        $tempDir = sys_get_temp_dir() . '/agileforge_ratelimit';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $hashKey = md5($ip . '_' . self::getToken() . '_' . $minute);
        $file = $tempDir . '/' . $hashKey . '.json';

        $currentCount = 1;
        if (file_exists($file)) {
            $data = json_decode((string)file_get_contents($file), true) ?: [];
            $currentCount = (int)($data['count'] ?? 0) + 1;
        }

        @file_put_contents($file, json_encode(['count' => $currentCount, 'time' => $now]), LOCK_EX);

        // Limpiar archivos viejos (1 de cada 100 peticiones)
        if (rand(1, 100) === 1) {
            foreach (glob($tempDir . '/*.json') ?: [] as $oldFile) {
                if (filemtime($oldFile) < ($now - 120)) {
                    @unlink($oldFile);
                }
            }
        }

        // Si excede el límite permitido por minuto
        if ($currentCount > $maxPerMinute) {
            http_response_code(429);
            header('Retry-After: 60');
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit local excedido: detector de bucles de agentes de IA activado.',
                'detail' => "Se superaron las {$maxPerMinute} peticiones en 60 segundos.",
                'hint' => 'Verifica si algún subagente está ejecutando herramientas en bucle recursivo sin condición de parada.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public static function loadEnv(): void
    {
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            // Primer arranque: token principal + token de solo lectura (ADR-004) + ruta de la base de datos.
            $content = 'AGILEFORGE_API_TOKEN=' . bin2hex(random_bytes(32)) . "\n"
                . 'AGILEFORGE_VIEW_TOKEN=' . bin2hex(random_bytes(32)) . "\n"
                . "AGILEFORGE_DB_PATH=data/agileforge.sqlite\nAGILEFORGE_ENV=local\n";
            self::writePrivateFile($envFile, $content);
        }

        self::parseEnvFile($envFile);
        self::$envLoaded = true;

        if (!self::$viewTokenTried && (self::isPlaceholder(self::$apiToken) || self::isPlaceholder(self::$viewToken))) {
            self::$viewTokenTried = true;
            self::ensureTokens($envFile);
        }
    }

    /** Vacío o valor de ejemplo copiado de .env.example (p. ej. "tu_token_…"): hay que generar uno real. */
    private static function isPlaceholder(?string $val): bool
    {
        return $val === null || $val === '' || (bool)preg_match('/^(tu_|your_|changeme|<)/i', $val);
    }

    private static function parseEnvFile(string $envFile): void
    {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $key = trim($k);
                $val = trim($v, " \t\n\r\0\x0B\"'");
                $_ENV[$key] = $val;
                if ($key === 'AGILEFORGE_API_TOKEN') {
                    self::$apiToken = $val;
                }
                if ($key === 'AGILEFORGE_VIEW_TOKEN') {
                    self::$viewToken = $val;
                }
            }
        }
    }

    /**
     * Genera los tokens que falten en el .env (instalaciones anteriores a ADR-004, o .env copiado de .env.example):
     * AGILEFORGE_API_TOKEN y AGILEFORGE_VIEW_TOKEN. Se serializa con flock y se vuelve a leer dentro del bloqueo
     * para que dos peticiones simultáneas no los regeneren. Nunca toca un token ya válido.
     */
    private static function ensureTokens(string $envFile): void
    {
        // El bloqueo va en un archivo aparte: en Windows no se puede reemplazar (rename) un archivo abierto
        $fh = @fopen(sys_get_temp_dir() . '/agileforge_env_' . md5($envFile) . '.lock', 'c');
        if ($fh === false) {
            return;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return;
            }
            self::$apiToken = self::$viewToken = null;
            self::parseEnvFile($envFile);
            foreach (['AGILEFORGE_API_TOKEN' => self::$apiToken, 'AGILEFORGE_VIEW_TOKEN' => self::$viewToken] as $key => $current) {
                if (!self::isPlaceholder($current)) {
                    continue;
                }
                try {
                    self::setEnvValue($key, bin2hex(random_bytes(32)));
                } catch (\Throwable $e) {
                    self::log("No se pudo escribir {$key} en .env: " . $e->getMessage());
                }
            }
            self::parseEnvFile($envFile);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public static function getToken(): string
    {
        if (self::$apiToken === null) {
            self::loadEnv();
        }
        return self::$apiToken ?? '';
    }

    /**
     * Token de solo lectura (ADR-004): válido únicamente para GET en api/index.php.
     * Lo incrusta vista.php en la meta `agileforge-view-token`. Nunca equivale al token principal.
     * Si el .env no es escribible se deriva del token principal con HMAC (no permite recuperar el principal).
     */
    public static function viewToken(): string
    {
        if (!self::$envLoaded) {
            self::loadEnv();
        }
        if (!self::isPlaceholder(self::$viewToken)) {
            return self::$viewToken;
        }
        $main = self::getToken();
        return $main === '' ? '' : hash_hmac('sha256', 'agileforge-view-token', $main);
    }

    public static function verifyHostAndOrigin(): void
    {
        // Permitir ejecución directa por CLI
        if (php_sapi_name() === 'cli') {
            return;
        }

        // 1. Validar Host
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $hostOnly = explode(':', $host)[0];
        $allowedHosts = ['localhost', '127.0.0.1', '::1'];

        if (!in_array(strtolower($hostOnly), $allowedHosts, true)) {
            self::fail('Acceso denegado: Host no autorizado. Solo permitido en localhost.', 403);
        }

        // 2. Validar Origin / Referer si existen (Protección CSRF)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $parsed = parse_url($origin);
            $originHost = strtolower($parsed['host'] ?? '');
            if (!in_array($originHost, $allowedHosts, true)) {
                self::fail('Acceso denegado: Origin externo no permitido.', 403);
            }
        }
    }

    /**
     * Autenticación por cabecera (X-AgileForge-Token o Authorization: Bearer). Ya no se acepta ?token= en la URL
     * (ADR-004: los tokens en la URL acaban en logs e historiales).
     * El token principal lo permite todo; el token de vista solo cuando $allowViewToken es true (api/index.php)
     * y el método es GET/HEAD. En cualquier otro caso el token de vista recibe 403.
     * Devuelve 'full' (token principal) o 'view' (token de vista): con 'view' el llamante debe aplicar la lista blanca
     * de rutas (Bootstrap::requireViewRoute).
     */
    public static function requireAuth(bool $allowViewToken = false): string
    {
        $token = self::getToken();
        if ($token === '') {
            self::fail('Token del servidor no configurado.', 500);
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader === '' && function_exists('apache_request_headers')) {
            $reqHeaders = apache_request_headers();
            $authHeader = $reqHeaders['Authorization'] ?? $reqHeaders['authorization'] ?? '';
        }
        $customHeader = $_SERVER['HTTP_X_AGILEFORGE_TOKEN'] ?? '';
        if ($customHeader === '' && function_exists('apache_request_headers')) {
            $reqHeaders = apache_request_headers();
            $customHeader = $reqHeaders['X-AgileForge-Token'] ?? $reqHeaders['x-agileforge-token'] ?? '';
        }

        $providedToken = '';
        if ($customHeader !== '') {
            $providedToken = trim($customHeader);
        } elseif (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $providedToken = trim($matches[1]);
        }

        if ($providedToken !== '' && hash_equals($token, $providedToken)) {
            return 'full';
        }

        $view = self::viewToken();
        if ($providedToken !== '' && $view !== '' && hash_equals($view, $providedToken)) {
            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if ($allowViewToken && in_array($method, ['GET', 'HEAD'], true)) {
                return 'view';
            }
            self::fail(
                'Token de vista de solo lectura: solo sirve para GET en api/index.php. Para escribir usa el token principal.',
                403
            );
        }

        self::fail('No autorizado: Token de AgileForge inválido o ausente.', 401, [
            'hint' => 'Envía el token mediante el header "Authorization: Bearer <TOKEN>" o "X-AgileForge-Token: <TOKEN>".',
        ]);
    }

    /**
     * Lista blanca de rutas GET del token de vista (H2). $segments = ruta sin base ni "index.php", p. ej. ['tasks', 'US-1'].
     * Permitidas: overview, events, meetings, meetings/{id}, decisions, tasks, tasks/{id}, sprint/status, roles/{id}/brief.
     * Todo lo demás (/state, /skills, /roles, …) → 403.
     */
    public static function requireViewRoute(array $segments): void
    {
        $n = count($segments);
        $a = $segments[0] ?? '';
        $ok = match (true) {
            $n === 1 => in_array($a, ['overview', 'events', 'meetings', 'decisions', 'tasks', 'memories'], true),
            $n === 2 => ($a === 'meetings' || $a === 'tasks' || $a === 'memories') && $segments[1] !== ''
                || ($a === 'sprint' && $segments[1] === 'status'),
            $n === 3 => $a === 'roles' && $segments[1] !== '' && $segments[2] === 'brief',
            default => false,
        };
        if (!$ok) {
            self::fail(
                'Token de vista de solo lectura: esta ruta no está permitida. Rutas GET permitidas: overview, events, meetings, '
                . 'meetings/{id}, decisions, tasks, tasks/{id}, sprint/status y roles/{id}/brief. Para el resto usa el token principal.',
                403
            );
        }
    }

    /** Error JSON uniforme {"success":false,"error":"…"} y fin de la petición. */
    public static function fail(string $message, int $code = 400, array $extra = []): void
    {
        self::jsonResponse(['success' => false, 'error' => $message] + $extra, $code);
    }

    public static function requireJsonBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (!str_contains(strtolower($contentType), 'application/json')) {
            self::fail('Content-Type no soportado. Se exige application/json', 415);
        }

        $raw = file_get_contents('php://input');
        $input = json_decode((string)$raw, true);

        if (!is_array($input)) {
            self::fail('Payload JSON malformado o vacío', 400);
        }

        return $input;
    }

    public static function validateWorkspacePath(string $path): string
    {
        $clean = trim($path);
        if ($clean === '') {
            throw new \InvalidArgumentException('Ruta de workspace vacía.');
        }

        // Prohibir path traversal
        if (str_contains($clean, '..') || str_contains($clean, "\0")) {
            throw new \InvalidArgumentException('Intento de Path Traversal detectado en la ruta del workspace.');
        }

        $real = realpath($clean);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException("El directorio de workspace especificado no existe o no es accesible: {$clean}");
        }

        return $real;
    }

    public static function jsonResponse(array $data, int $code = 200, bool $pretty = true): void
    {
        http_response_code($code);
        // Contrato de errores: todo >= 400 con "error" lleva también success:false
        if ($code >= 400 && isset($data['error']) && !array_key_exists('success', $data)) {
            $data = ['success' => false] + $data;
        }
        // INVALID_UTF8_SUBSTITUTE: una respuesta de IA cortada a mitad de un carácter no debe dejar el cuerpo vacío
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | ($pretty ? JSON_PRETTY_PRINT : 0));
        if ($json === false) {
            http_response_code(500);
            $json = json_encode(['success' => false, 'error' => 'No se pudo serializar la respuesta: ' . json_last_error_msg()]);
        }
        echo $json;
        exit;
    }
}
