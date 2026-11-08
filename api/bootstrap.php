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

    public static function init(): void
    {
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

    public static function checkRateLimit(int $maxPerMinute = 180): void
    {
        // No aplicar límite en pruebas directas por CLI
        if (php_sapi_name() === 'cli') {
            return;
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
            $token = bin2hex(random_bytes(32));
            $content = "AGILEFORGE_API_TOKEN={$token}\nAGILEFORGE_DB_PATH=data/agileforge.sqlite\nAGILEFORGE_ENV=local\n";
            file_put_contents($envFile, $content);
        }

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
            }
        }
    }

    public static function getToken(): string
    {
        if (self::$apiToken === null) {
            self::loadEnv();
        }
        return self::$apiToken ?? '';
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
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado: Host no autorizado. Solo permitido en localhost.']);
            exit;
        }

        // 2. Validar Origin / Referer si existen (Protección CSRF)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $parsed = parse_url($origin);
            $originHost = strtolower($parsed['host'] ?? '');
            if (!in_array($originHost, $allowedHosts, true)) {
                http_response_code(403);
                echo json_encode(['error' => 'Acceso denegado: Origin externo no permitido.']);
                exit;
            }
        }
    }

    public static function requireAuth(): void
    {
        $token = self::getToken();
        if ($token === '') {
            http_response_code(500);
            echo json_encode(['error' => 'Token del servidor no configurado.']);
            exit;
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
        $queryToken = $_GET['token'] ?? '';

        $providedToken = '';
        if ($customHeader !== '') {
            $providedToken = trim($customHeader);
        } elseif (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $providedToken = trim($matches[1]);
        } elseif ($queryToken !== '') {
            $providedToken = trim((string)$queryToken);
        }

        if ($providedToken === '' || !hash_equals($token, $providedToken)) {
            http_response_code(401);
            echo json_encode([
                'error' => 'No autorizado: Token de AgileForge inválido o ausente.',
                'hint'  => 'Envía el token mediante el header "Authorization: Bearer <TOKEN>" o "X-AgileForge-Token: <TOKEN>".'
            ]);
            exit;
        }
    }

    public static function requireJsonBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (!str_contains(strtolower($contentType), 'application/json')) {
            http_response_code(415);
            echo json_encode(['error' => 'Content-Type no soportado. Se exige application/json']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $input = json_decode((string)$raw, true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload JSON malformado o vacío']);
            exit;
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

    public static function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
