<?php
/**
 * AgileForge - router para el servidor integrado de PHP.
 *
 *     php -S localhost:8080 router.php
 *
 * El servidor integrado (`php -S`) IGNORA los .htaccess, así que sin este router sirve tal cual
 * `/.env` (tokens y claves de API), `/data/*.sqlite` (toda la base de datos), `/.git/…`, etc.
 * Este archivo hace de cortafuegos: devuelve 404 a
 *   - cualquier archivo o carpeta que empiece por punto (.env, .git, .htaccess, …),
 *   - data/, bin/, config/, mcp-server/ y scratch/,
 *   - *.sqlite* (bases y sus -wal/-shm), *.db y *.log,
 * y deja pasar el resto (return false: el servidor sirve el archivo estático o ejecuta el .php).
 * También emula las rutas limpias de api/.htaccess (<app>/api/<ruta> → api/index.php).
 *
 * Funciona con la app en la raíz del servidor o en una subcarpeta
 * (p. ej. `php -S localhost:8080 -t /ruta/htdocs /ruta/htdocs/agile-forge/router.php`).
 * Con Apache/XAMPP no hace falta: allí mandan los .htaccess.
 */

declare(strict_types=1);

return (static function () {
    $appRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
    $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');

    $notFound = static function (): bool {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo "404 Not Found\n";
        return true;
    };

    $uriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
    $decoded = str_replace('\\', '/', rawurldecode($uriPath));
    if (str_contains($decoded, "\0")) {
        return $notFound();
    }

    // Ruta de la petición normalizando "." y ".." como hace el servidor: ".." en la raíz no sube más
    // (así "/../.env", "/data/../.env" o "/x/../data/a.sqlite" no esquivan nada)
    $parts = [];
    foreach (explode('/', $decoded) as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $seg;
    }
    $full = $docRoot . ($parts ? '/' . implode('/', $parts) : '');
    if ($full !== $appRoot && !str_starts_with($full, $appRoot . '/')) {
        return false; // fuera de la app (otro sitio del mismo docroot): no es asunto de este router
    }
    $segs = $full === $appRoot ? [] : explode('/', substr($full, strlen($appRoot) + 1));

    // --- Cortafuegos -----------------------------------------------------------------------------------------
    $blockedTop = ['data', 'bin', 'config', 'mcp-server', 'scratch'];
    foreach ($segs as $i => $seg) {
        // Windows ignora puntos y espacios finales ("data. " == "data"); se compara sin ellos y sin mayúsculas
        $s = strtolower(rtrim($seg, '. '));
        if (str_starts_with($seg, '.') || $s === '') {
            return $notFound();
        }
        if ($i === 0 && in_array($s, $blockedTop, true)) {
            return $notFound();
        }
    }
    $last = strtolower(rtrim((string)end($segs), '. '));
    if ($last !== '' && (str_contains($last, '.sqlite') || preg_match('/\.(db|log)$/', $last))) {
        return $notFound();
    }

    // --- Lo que sí se sirve ------------------------------------------------------------------------------------
    $target = $appRoot . '/' . implode('/', $segs);
    if (is_file($target) || is_dir($target)) {
        return false; // estático o .php existente (o carpeta con su index): lo sirve el servidor integrado
    }

    // Rutas limpias de la API (equivale a api/.htaccess): <app>/api/<ruta> → api/index.php
    if (($segs[0] ?? '') === 'api' && count($segs) >= 2 && $segs[1] !== 'index.php') {
        $urlBase = substr($appRoot, strlen($docRoot)); // '' en la raíz, '/carpeta' en una subcarpeta
        $_SERVER['SCRIPT_NAME'] = $urlBase . '/api/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        $_SERVER['SCRIPT_FILENAME'] = $appRoot . '/api/index.php';
        chdir($appRoot . '/api');
        require $appRoot . '/api/index.php';
        return true;
    }

    return false; // el servidor integrado responde 404 (o resuelve PATH_INFO, p. ej. api/index.php/overview)
})();
