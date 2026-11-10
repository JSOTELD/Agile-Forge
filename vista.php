<?php
// AgileForge Vista: panel de solo lectura. Usa únicamente el token de vista (nunca el principal).
require_once __DIR__ . '/api/bootstrap.php';
\AgileForge\Bootstrap::loadEnv();
$viewToken = method_exists('\AgileForge\Bootstrap', 'viewToken') ? \AgileForge\Bootstrap::viewToken() : '';
$cssV = @filemtime(__DIR__ . '/assets/css/vista.css') ?: 1;
$jsV = @filemtime(__DIR__ . '/assets/js/vista.js') ?: 1;
$hasLogo = is_file(__DIR__ . '/assets/brand/symbol-agileforge-dark.png');
?>
<!DOCTYPE html>
<html lang="es" data-theme="night">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="agileforge-view-token" content="<?= htmlspecialchars($viewToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="color-scheme" content="dark light">
    <title>AgileForge Vista</title>
    <link rel="icon" type="image/png" href="assets/brand/favicon.png">
    <script>
        (function () {
            try {
                var l = localStorage.getItem('agileforge_lang');
                if (l === 'en' || l === 'es') document.documentElement.lang = l;
                var t = localStorage.getItem('agileforge_theme');
                document.documentElement.setAttribute('data-theme', (t === 'light' || t === 'paper') ? 'day' : 'night');
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/vista.css?v=<?= (int)$cssV ?>">
</head>
<body>
<a class="skip" href="#main" data-i="skip">Saltar al contenido</a>
<header class="top" role="banner">
    <div class="brand">
        <?php if ($hasLogo): ?><img src="assets/brand/symbol-agileforge-dark.png" alt="" width="32" height="32"><?php endif; ?>
        <div>
            <h1>AgileForge <span class="tag" data-i="title_tag">Vista</span></h1>
            <p class="proj" id="project-name"></p>
        </div>
    </div>
    <div class="tools">
        <label class="sel hidden" id="project-wrap"><span data-i="project">Proyecto</span>
            <select id="project-select"></select>
        </label>
        <span id="conn" class="badge off" role="status" aria-live="polite"><span class="ico" aria-hidden="true">●</span> <span id="conn-text">…</span></span>
        <span id="updated" class="muted"></span>
        <button type="button" id="lang-btn" class="btn" aria-label="Idioma / Language">ES</button>
        <button type="button" id="theme-btn" class="btn" aria-pressed="false"><span id="theme-ico" aria-hidden="true">🌙</span> <span id="theme-text" data-i="night">Noche</span></button>
    </div>
</header>

<main id="main" tabindex="-1">
    <div id="notice" class="notice hidden" role="status"></div>

    <section class="sprint" aria-labelledby="h-sprint">
        <h2 id="h-sprint" class="sr" data-i="sprint">Sprint</h2>
        <div class="sprint-head">
            <div>
                <p class="sprint-name" id="sprint-name">—</p>
                <p class="sprint-goal" id="sprint-goal"></p>
            </div>
            <div class="sprint-meta">
                <span class="pct" id="sprint-pct">0%</span>
                <span class="muted" id="sprint-days"></span>
            </div>
        </div>
        <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="sprint-bar" aria-labelledby="h-sprint"><span id="sprint-fill"></span></div>
        <p class="since" id="since"></p>
    </section>

    <section aria-labelledby="h-board">
        <h2 id="h-board" data-i="board">Tablero</h2>
        <p class="empty hidden" id="board-empty"></p>
        <div class="board" id="board"></div>
    </section>

    <section aria-labelledby="h-working">
        <h2 id="h-working" data-i="working">Quién trabaja en qué</h2>
        <ul class="list" id="working"></ul>
    </section>

    <section aria-labelledby="h-meetings">
        <h2 id="h-meetings" data-i="meetings">Reuniones</h2>
        <div id="meetings"></div>
    </section>

    <div class="two">
        <section aria-labelledby="h-decisions">
            <h2 id="h-decisions" data-i="decisions">Decisiones</h2>
            <ul class="list" id="decisions"></ul>
        </section>
        <section aria-labelledby="h-activity">
            <h2 id="h-activity" data-i="activity">Actividad</h2>
            <ul class="list" id="activity"></ul>
        </section>
    </div>
</main>
<footer class="foot muted" role="contentinfo"><span data-i="readonly">Solo lectura · sin claves ni acciones de escritura</span></footer>
<script type="module" src="assets/js/vista.js?v=<?= (int)$jsV ?>"></script>
</body>
</html>
