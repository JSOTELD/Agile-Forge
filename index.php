<?php
require_once __DIR__ . '/api/bootstrap.php';
\AgileForge\Bootstrap::loadEnv();
$apiToken = \AgileForge\Bootstrap::getToken();
?>
<!DOCTYPE html>
<html lang="es" class="theme-obsidian">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="agileforge-token" content="<?= htmlspecialchars($apiToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php
    // Cache-busting de los módulos ES: cada import "./x.js" se resuelve a "./x.js?v=<fecha de modificación>",
    // así el navegador nunca mezcla módulos viejos con nuevos tras una actualización.
    $afImports = [];
    foreach (glob(__DIR__ . '/assets/js/*.js') ?: [] as $afJs) {
        $afName = basename($afJs);
        $afImports['./assets/js/' . $afName] = './assets/js/' . $afName . '?v=' . filemtime($afJs);
    }
    ?>
    <script type="importmap"><?= json_encode(['imports' => $afImports], JSON_UNESCAPED_SLASHES) ?></script>
    <script>
        (function() {
            try {
                var t = localStorage.getItem('agileforge_theme');
                if (t) {
                    document.documentElement.className = 'theme-' + t;
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch(e) {}
        })();
    </script>
    <title>AgileForge · Command Center Scrum & Multi-Skills de Agentes IA</title>
    <!-- Favicon Oficial AgileForge -->
    <link rel="icon" type="image/png" href="assets/brand/favicon.png">
    <link rel="apple-touch-icon" href="assets/brand/favicon.png">
    <!-- Estilos compilados de Tailwind CSS (local, cero dependencia CDN) -->
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #070a12;
            --bg-surface: #0c101d;
            --bg-card: rgba(15, 23, 42, 0.75);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --accent-primary: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.25);
        }

        .theme-obsidian {
            --bg-base: #070a12;
            --bg-surface: #0b0f19;
            --bg-card: rgba(15, 23, 42, 0.85);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --accent-primary: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.25);
        }

        .theme-indigo {
            --bg-base: #050a18;
            --bg-surface: #0a1226;
            --bg-card: rgba(13, 23, 46, 0.85);
            --border-subtle: rgba(99, 102, 241, 0.18);
            --accent-primary: #4f46e5;
            --accent-glow: rgba(79, 70, 229, 0.35);
        }

        .theme-emerald {
            --bg-base: #03100a;
            --bg-surface: #061910;
            --bg-card: rgba(6, 32, 20, 0.85);
            --border-subtle: rgba(16, 185, 129, 0.2);
            --accent-primary: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.3);
        }

        .theme-violet {
            --bg-base: #0c0718;
            --bg-surface: #140d24;
            --bg-card: rgba(26, 16, 48, 0.85);
            --border-subtle: rgba(168, 85, 247, 0.2);
            --accent-primary: #a855f7;
            --accent-glow: rgba(168, 85, 247, 0.35);
        }

        .theme-light {
            --bg-base: #f1f5f9;
            --bg-surface: #ffffff;
            --bg-card: rgba(255, 255, 255, 0.95);
            --border-subtle: rgba(15, 23, 42, 0.12);
            --accent-primary: #4f46e5;
            --accent-glow: rgba(79, 70, 229, 0.18);
            color-scheme: light;
        }

        .theme-paper {
            --bg-base: #fbfbf9;
            --bg-surface: #f4f4f0;
            --bg-card: rgba(248, 248, 244, 0.95);
            --border-subtle: rgba(45, 55, 72, 0.12);
            --accent-primary: #d97706;
            --accent-glow: rgba(217, 119, 6, 0.18);
            color-scheme: light;
        }

        /* Aviso del modo plugin en modo día */
        html.theme-light #plugin-notice, html.theme-paper #plugin-notice { background: #e0f2fe; color: #075985; border-color: #0369a1; }
        html.theme-light #director-plugin-note, html.theme-paper #director-plugin-note { color: #075985; }

        /* Reglas de Alto Contraste para Modo Día (Light Mode) */
        html.theme-light body,
        html.theme-paper body {
            background-color: var(--bg-base);
            color: #0f172a;
        }

        html.theme-light .bg-slate-950,
        html.theme-paper .bg-slate-950,
        html.theme-light .bg-slate-900,
        html.theme-paper .bg-slate-900,
        html.theme-light .bg-slate-900\/90,
        html.theme-paper .bg-slate-900\/90,
        html.theme-light .bg-slate-900\/80,
        html.theme-paper .bg-slate-900\/80,
        html.theme-light .bg-\[\#090d16\],
        html.theme-paper .bg-\[\#090d16\],
        html.theme-light header,
        html.theme-paper header,
        html.theme-light nav,
        html.theme-paper nav {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            color: #0f172a !important;
        }

        html.theme-light .bg-slate-900\/60,
        html.theme-paper .bg-slate-900\/60,
        html.theme-light .bg-slate-800,
        html.theme-paper .bg-slate-800,
        html.theme-light .bg-slate-800\/60,
        html.theme-paper .bg-slate-800\/60 {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        html.theme-light .border-slate-800,
        html.theme-paper .border-slate-800,
        html.theme-light .border-slate-800\/80,
        html.theme-paper .border-slate-800\/80,
        html.theme-light .border-slate-700,
        html.theme-paper .border-slate-700 {
            border-color: #e2e8f0 !important;
        }

        html.theme-light .text-white,
        html.theme-paper .text-white {
            color: #0f172a !important;
        }

        html.theme-light .text-slate-200,
        html.theme-paper .text-slate-200,
        html.theme-light .text-slate-300,
        html.theme-paper .text-slate-300 {
            color: #334155 !important;
        }

        html.theme-light .text-slate-400,
        html.theme-paper .text-slate-400 {
            color: #64748b !important;
        }

        html.theme-light input,
        html.theme-paper input,
        html.theme-light select,
        html.theme-paper select,
        html.theme-light textarea,
        html.theme-paper textarea {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }

        /* 1. Adaptación del Logo para Modo Día: negro azabache de máximo contraste */
        html.theme-light .brand-logo-img,
        html.theme-paper .brand-logo-img {
            filter: brightness(0) contrast(200%) !important;
        }

        html.theme-light .brand-logo-container,
        html.theme-paper .brand-logo-container {
            background: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
        }

        /* 2. Tarjetas Kanban en Modo Día: Fondo blanco puro, texto oscuro legible */
        html.theme-light .kanban-card,
        html.theme-paper .kanban-card {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        }

        html.theme-light .kanban-card:hover,
        html.theme-paper .kanban-card:hover {
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08) !important;
        }

        html.theme-light .kanban-title,
        html.theme-paper .kanban-title {
            color: #0f172a !important;
        }

        html.theme-light .kanban-title:hover,
        html.theme-paper .kanban-title:hover {
            color: #4f46e5 !important;
        }

        html.theme-light .kanban-card .text-indigo-400,
        html.theme-paper .kanban-card .text-indigo-400 {
            color: #4338ca !important;
        }

        /* Chips y badges de prioridad dentro de las tarjetas */
        html.theme-light .kanban-card .bg-red-950\/80,
        html.theme-paper .kanban-card .bg-red-950\/80 {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #fca5a5 !important;
        }

        html.theme-light .kanban-card .bg-orange-950\/80,
        html.theme-paper .kanban-card .bg-orange-950\/80 {
            background-color: #ffedd5 !important;
            color: #9a3412 !important;
            border: 1px solid #fdba74 !important;
        }

        html.theme-light .kanban-card .bg-slate-800,
        html.theme-paper .kanban-card .bg-slate-800 {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
        }

        html.theme-light .kanban-card .bg-slate-900,
        html.theme-paper .kanban-card .bg-slate-900,
        html.theme-light .kanban-card .bg-slate-950,
        html.theme-paper .kanban-card .bg-slate-950 {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #334155 !important;
        }

        html.theme-light .kanban-card .bg-purple-950\/60,
        html.theme-paper .kanban-card .bg-purple-950\/60 {
            background-color: #f5f3ff !important;
            border-color: #ddd6fe !important;
            color: #6d28d9 !important;
        }

        html.theme-light .kanban-card .bg-indigo-950\/80,
        html.theme-paper .kanban-card .bg-indigo-950\/80 {
            background-color: #eef2ff !important;
            border-color: #c7d2fe !important;
            color: #4338ca !important;
        }

        html.theme-light .kanban-card .border-slate-900,
        html.theme-paper .kanban-card .border-slate-900 {
            border-color: #f1f5f9 !important;
        }

        html.theme-light .kanban-card .text-slate-300,
        html.theme-paper .kanban-card .text-slate-300,
        html.theme-light .kanban-card .text-slate-400,
        html.theme-paper .kanban-card .text-slate-400 {
            color: #475569 !important;
        }

        /* Botones de acción dentro de las tarjetas */
        html.theme-light .kanban-card button,
        html.theme-paper .kanban-card button {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #334155 !important;
        }

        html.theme-light .kanban-card button:hover,
        html.theme-paper .kanban-card button:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }

        html.theme-light .kanban-card button[data-action="show-task-prompt"],
        html.theme-paper .kanban-card button[data-action="show-task-prompt"] {
            background-color: #eef2ff !important;
            border-color: #c7d2fe !important;
            color: #4338ca !important;
        }

        html.theme-light .kanban-card button[data-action="show-task-prompt"]:hover,
        html.theme-paper .kanban-card button[data-action="show-task-prompt"]:hover {
            background-color: #e0e7ff !important;
            color: #3730a3 !important;
        }

        html.theme-light .kanban-card button[data-action="move-task-forward"],
        html.theme-paper .kanban-card button[data-action="move-task-forward"] {
            color: #4338ca !important;
            font-weight: 700 !important;
        }

        /* 3. Hero Banner de Sprint en Modo Día */
        html.theme-light #sprint-hero-banner,
        html.theme-paper #sprint-hero-banner {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.03) !important;
        }

        html.theme-light #sprint-hero-banner #sprint-name,
        html.theme-paper #sprint-hero-banner #sprint-name {
            color: #0f172a !important;
        }

        html.theme-light #sprint-hero-banner #sprint-goal,
        html.theme-paper #sprint-hero-banner #sprint-goal {
            color: #475569 !important;
        }

        html.theme-light #sprint-hero-banner .bg-slate-950\/80,
        html.theme-paper #sprint-hero-banner .bg-slate-950\/80 {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }

        html.theme-light #sprint-hero-banner .bg-indigo-500\/20,
        html.theme-paper #sprint-hero-banner .bg-indigo-500\/20 {
            background-color: #e0e7ff !important;
            color: #3730a3 !important;
            border-color: #c7d2fe !important;
        }

        html.theme-light #sprint-hero-banner .bg-slate-950\/90,
        html.theme-paper #sprint-hero-banner .bg-slate-950\/90 {
            background-color: #e2e8f0 !important;
            border-color: #cbd5e1 !important;
        }

        html.theme-light #sprint-hero-banner .bg-slate-800,
        html.theme-paper #sprint-hero-banner .bg-slate-800 {
            background-color: #e2e8f0 !important;
        }

        html.theme-light #sprint-hero-banner .text-slate-400,
        html.theme-paper #sprint-hero-banner .text-slate-400 {
            color: #64748b !important;
        }

        /* 4. Columnas Kanban y Encabezados con Alto Contraste */
        html.theme-light .kanban-col,
        html.theme-paper .kanban-col {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }

        html.theme-light .kanban-col > div:first-child,
        html.theme-paper .kanban-col > div:first-child {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
        }

        html.theme-light .kanban-col .text-amber-300,
        html.theme-paper .kanban-col .text-amber-300 {
            color: #b45309 !important;
        }

        html.theme-light .kanban-col .text-purple-300,
        html.theme-paper .kanban-col .text-purple-300 {
            color: #7e22ce !important;
        }

        html.theme-light .kanban-col .text-emerald-300,
        html.theme-paper .kanban-col .text-emerald-300 {
            color: #047857 !important;
        }

        html.theme-light .kanban-col .text-slate-200,
        html.theme-paper .kanban-col .text-slate-200 {
            color: #334155 !important;
        }

        html.theme-light .kanban-col #col-count-backlog,
        html.theme-paper .kanban-col #col-count-backlog {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }

        html.theme-light .kanban-col #col-count-en_progreso,
        html.theme-paper .kanban-col #col-count-en_progreso {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #fde68a !important;
        }

        html.theme-light .kanban-col #col-count-revision,
        html.theme-paper .kanban-col #col-count-revision {
            background-color: #f3e8ff !important;
            color: #6b21a8 !important;
            border: 1px solid #e9d5ff !important;
        }

        html.theme-light .kanban-col #col-count-completada,
        html.theme-paper .kanban-col #col-count-completada {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
            border: 1px solid #a7f3d0 !important;
        }

        /* 5. Barra de Filtros en Modo Día */
        html.theme-light #kanban-search,
        html.theme-paper #kanban-search,
        html.theme-light #kanban-filter-role,
        html.theme-paper #kanban-filter-role,
        html.theme-light #kanban-filter-priority,
        html.theme-paper #kanban-filter-priority,
        html.theme-light #kanban-filter-skill,
        html.theme-paper #kanban-filter-skill,
        html.theme-light #kanban-filter-count,
        html.theme-paper #kanban-filter-count {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }

        /* 6. Botones de Cabecera con bordes nítidos */
        html.theme-light header button,
        html.theme-paper header button {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        }

        html.theme-light header button:hover,
        html.theme-paper header button:hover {
            background-color: #f8fafc !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }

        html.theme-light header .text-slate-400,
        html.theme-paper header .text-slate-400 {
            color: #64748b !important;
        }

        html.theme-light header .text-slate-200,
        html.theme-paper header .text-slate-200 {
            color: #1e293b !important;
        }

        /* 7. Tabs de Navegación */
        html.theme-light .tab-inactive,
        html.theme-paper .tab-inactive {
            color: #475569 !important;
        }

        html.theme-light .tab-inactive:hover,
        html.theme-paper .tab-inactive:hover {
            color: #0f172a !important;
            background-color: #f1f5f9 !important;
        }

        /* 8. Barra de Proyecto */
        html.theme-light nav .bg-slate-900\/60,
        html.theme-paper nav .bg-slate-900\/60,
        html.theme-light #project-selector,
        html.theme-paper #project-selector {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }

        html.theme-light .kanban-col .text-amber-300,
        html.theme-paper .kanban-col .text-amber-300 {
            color: #b45309 !important;
        }

        html.theme-light .kanban-col .text-purple-300,
        html.theme-paper .kanban-col .text-purple-300 {
            color: #7e22ce !important;
        }

        html.theme-light .kanban-col .text-emerald-300,
        html.theme-paper .kanban-col .text-emerald-300 {
            color: #047857 !important;
        }

        html.theme-light .kanban-col .text-slate-200,
        html.theme-paper .kanban-col .text-slate-200 {
            color: #334155 !important;
        }

        html.theme-light .kanban-col #col-count-backlog,
        html.theme-paper .kanban-col #col-count-backlog {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }

        html.theme-light .kanban-col #col-count-en_progreso,
        html.theme-paper .kanban-col #col-count-en_progreso {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #fde68a !important;
        }

        html.theme-light .kanban-col #col-count-revision,
        html.theme-paper .kanban-col #col-count-revision {
            background-color: #f3e8ff !important;
            color: #6b21a8 !important;
            border: 1px solid #e9d5ff !important;
        }

        html.theme-light .kanban-col #col-count-completada,
        html.theme-paper .kanban-col #col-count-completada {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
            border: 1px solid #a7f3d0 !important;
        }

        /* 5. Barra de Filtros en Modo Día */
        html.theme-light #kanban-search,
        html.theme-paper #kanban-search,
        html.theme-light #kanban-filter-role,
        html.theme-paper #kanban-filter-role,
        html.theme-light #kanban-filter-priority,
        html.theme-paper #kanban-filter-priority,
        html.theme-light #kanban-filter-skill,
        html.theme-paper #kanban-filter-skill,
        html.theme-light #kanban-filter-count,
        html.theme-paper #kanban-filter-count {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }

        /* 6. Botones de Cabecera con bordes nítidos */
        html.theme-light header button,
        html.theme-paper header button {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        }

        html.theme-light header button:hover,
        html.theme-paper header button:hover {
            background-color: #f8fafc !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }

        html.theme-light header .text-slate-400,
        html.theme-paper header .text-slate-400 {
            color: #64748b !important;
        }

        html.theme-light header .text-slate-200,
        html.theme-paper header .text-slate-200 {
            color: #1e293b !important;
        }

        /* 7. Tabs de Navegación */
        html.theme-light .tab-inactive,
        html.theme-paper .tab-inactive {
            color: #475569 !important;
        }

        html.theme-light .tab-inactive:hover,
        html.theme-paper .tab-inactive:hover {
            color: #0f172a !important;
            background-color: #f1f5f9 !important;
        }

        /* 8. Barra de Proyecto */
        html.theme-light nav .bg-slate-900\/60,
        html.theme-paper nav .bg-slate-900\/60,
        html.theme-light #project-selector,
        html.theme-paper #project-selector {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }

        /* 9. Notificaciones Toast en Modo Día */
        html.theme-light .toast-card,
        html.theme-paper .toast-card {
            background-color: #ffffff !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08) !important;
        }

        html.theme-light .toast-card h5,
        html.theme-paper .toast-card h5 {
            color: #0f172a !important;
        }

        html.theme-light .toast-card p,
        html.theme-paper .toast-card p {
            color: #475569 !important;
        }

        html.theme-light .toast-card button,
        html.theme-paper .toast-card button {
            color: #94a3b8 !important;
        }

        html.theme-light .toast-card button:hover,
        html.theme-paper .toast-card button:hover {
            color: #0f172a !important;
        }

        html.theme-light .toast-card.toast-success,
        html.theme-paper .toast-card.toast-success {
            background-color: #ffffff !important;
            border-color: #a7f3d0 !important;
            border-left: 4px solid #10b981 !important;
        }
        html.theme-light .toast-card.toast-success > span:first-child,
        html.theme-paper .toast-card.toast-success > span:first-child {
            color: #059669 !important;
        }

        html.theme-light .toast-card.toast-error,
        html.theme-paper .toast-card.toast-error {
            background-color: #ffffff !important;
            border-color: #fecdd3 !important;
            border-left: 4px solid #f43f5e !important;
        }
        html.theme-light .toast-card.toast-error > span:first-child,
        html.theme-paper .toast-card.toast-error > span:first-child {
            color: #e11d48 !important;
        }

        html.theme-light .toast-card.toast-warning,
        html.theme-paper .toast-card.toast-warning {
            background-color: #ffffff !important;
            border-color: #fde68a !important;
            border-left: 4px solid #f59e0b !important;
        }
        html.theme-light .toast-card.toast-warning > span:first-child,
        html.theme-paper .toast-card.toast-warning > span:first-child {
            color: #d97706 !important;
        }

        html.theme-light .toast-card.toast-info,
        html.theme-paper .toast-card.toast-info {
            background-color: #ffffff !important;
            border-color: #c7d2fe !important;
            border-left: 4px solid #6366f1 !important;
        }
        html.theme-light .toast-card.toast-info > span:first-child,
        html.theme-paper .toast-card.toast-info > span:first-child {
            color: #4f46e5 !important;
        }

        html.theme-light .toast-card.toast-magic,
        html.theme-paper .toast-card.toast-magic {
            background-color: #ffffff !important;
            border-color: #ddd6fe !important;
            border-left: 4px solid #8b5cf6 !important;
        }
        html.theme-light .toast-card.toast-magic > span:first-child,
        html.theme-paper .toast-card.toast-magic > span:first-child {
            color: #7c3aed !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-base);
            color: #f8fafc;
        }

        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Scrollbars finos para paneles */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-base); }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }

        .glass-card {
            background: var(--bg-card);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-subtle);
        }

        .glow-fx {
            box-shadow: 0 0 25px var(--accent-glow);
        }

        .tab-active {
            background: var(--accent-primary);
            color: #ffffff;
            box-shadow: 0 4px 14px var(--accent-glow);
        }
        .tab-inactive { color: #94a3b8; }
        .tab-inactive:hover { color: #ffffff; background: #1e293b; }

        /* Modo de Densidad Compacta */
        .density-compact .kanban-card { padding: 0.65rem !important; }
        .density-compact .kanban-title { font-size: 0.8rem !important; line-height: 1.15rem !important; }
        .density-compact .kanban-col { max-height: 850px !important; }

        /* Animaciones para Toasts */
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .toast-enter { animation: slideInRight 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .toast-exit { animation: fadeOutRight 0.2s ease-in forwards; }

        /* Foco visible para accesibilidad WCAG */
        :focus-visible {
            outline: 2px solid #818cf8;
            outline-offset: 2px;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Topbar & Header Principal -->
    <header class="border-b border-slate-800/80 bg-[#0b0f19]/90 backdrop-blur-md sticky top-0 z-40 px-4 sm:px-6 py-2.5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="brand-logo-container h-10 w-10 p-1 bg-gradient-to-br from-indigo-500/20 via-purple-600/15 to-pink-500/10 border border-indigo-500/30 rounded-2xl shadow-inner flex items-center justify-center shrink-0">
                <img id="header-brand-logo" src="assets/brand/symbol-agileforge-dark.png" alt="AgileForge Logo" class="brand-logo-img h-full w-full object-contain filter drop-shadow">
            </div>
            <div>
                <h1 class="font-extrabold text-lg text-white tracking-tight flex items-center gap-2">
                    AgileForge <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">Scrum 3.0</span>
                </h1>
                <p class="text-[11px] text-slate-400">Command Center de Agentes IA · Suite de Ingeniería, Diseño UX y Compliance</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Alternar Modo Día / Noche Rápido -->
            <button onclick="toggleDayNightMode()" id="day-night-toggle" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-amber-500/40 text-xs transition group shadow-sm" title="Alternar Modo Día / Modo Noche">
                <span id="day-night-icon" class="text-sm">🌙</span>
                <span id="day-night-text" class="font-bold text-slate-200 group-hover:text-amber-300 font-mono text-[11px]" data-i18n="night_mode">Noche</span>
            </button>

            <!-- Selector de Idioma (Español / English) -->
            <button onclick="toggleLanguage()" id="lang-toggle-btn" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-sky-500/40 text-xs transition group shadow-sm" title="Cambiar Idioma / Switch Language">
                <span class="text-sm">🌐</span>
                <span id="lang-btn-text" class="font-bold text-sky-400 group-hover:text-sky-300 font-mono text-[11px]">ES</span>
            </button>

            <!-- AgileForge Vista: panel de solo lectura en otra pestaña -->
            <a href="vista.php" target="_blank" rel="noopener" id="vista-link" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-emerald-500/30 hover:border-emerald-400 text-xs text-emerald-300 transition group shadow-sm" data-i18n-title="view_btn_title" title="Abrir AgileForge Vista (solo lectura) en otra pestaña">
                <span>👁️</span>
                <span class="font-bold text-[11px]" data-i18n="view_btn">Vista</span>
            </a>

            <!-- Manual para Novatos -->
            <button onclick="openBeginnerManualModal()" id="manual-novatos-btn" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-indigo-500/30 hover:border-indigo-400 text-xs text-indigo-300 transition group shadow-sm" title="Abrir Manual de Inicio Rápido para Novatos (Alt+M)">
                <span>📖</span>
                <span class="hidden md:inline font-bold text-[11px]" data-i18n="manual_btn">Manual Novatos</span>
            </button>

            <!-- Command Palette Rápido (Ctrl+K) -->
            <button onclick="openCommandPalette()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 text-xs text-slate-300 transition group shadow-sm" title="Abrir Command Palette (Ctrl + K)">
                <span>🔍</span>
                <span class="hidden sm:inline text-[11px] text-slate-400" data-i18n="search_btn">Buscar</span>
                <kbd class="hidden md:inline text-[9px] bg-slate-950 px-1.5 py-0.5 rounded border border-slate-700 font-mono text-slate-400">Ctrl K</kbd>
            </button>

            <!-- Selector de Tema Visual & Densidad -->
            <button onclick="openThemeModal()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-pink-500/40 text-xs transition group shadow-sm" title="Personalizar Tema Visual y Ergonomía">
                <span id="theme-btn-icon" class="text-sm">🎨</span>
                <span id="theme-btn-text" class="font-bold text-slate-200 group-hover:text-pink-300 font-mono text-[11px]">Obsidian</span>
            </button>

            <!-- Selector de Motor IA Activo -->
            <button onclick="openAiEngineModal()" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-purple-500/40 text-xs transition group shadow-sm" title="Configurar Motor IA y Proveedores API">
                <span id="header-ai-icon" class="text-sm">🟣</span>
                <div class="text-left">
                    <span class="block text-[8px] uppercase tracking-wider text-slate-400 font-bold leading-none" data-i18n="ai_engine_lbl">Motor IA:</span>
                    <span id="header-ai-model" class="font-bold text-white group-hover:text-purple-300 font-mono text-[11px] leading-tight">Cargando…</span>
                </div>
                <span class="text-[10px] text-slate-500 ml-0.5">⚙️</span>
            </button>

            <!-- Interruptor de IA Local (Ollama): se activa solo si se detecta una IA local con modelos -->
            <button id="local-ai-toggle" type="button" role="switch" aria-checked="false" onclick="toggleLocalAi()"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-emerald-500/40 text-xs transition group shadow-sm"
                    title="Buscando IA local…">
                <span class="text-sm">🦙</span>
                <div class="text-left">
                    <span class="block text-[8px] uppercase tracking-wider text-slate-400 font-bold leading-none" data-i18n="local_ai_lbl">IA local:</span>
                    <span id="local-ai-label" class="font-bold font-mono text-[11px] leading-tight text-slate-400">Buscando…</span>
                </div>
                <span id="local-ai-track" class="relative inline-block w-8 h-[18px] rounded-full bg-slate-700 transition-colors" aria-hidden="true">
                    <span id="local-ai-knob" class="absolute top-[2px] left-[2px] w-[14px] h-[14px] rounded-full bg-white shadow transition-transform"></span>
                </span>
            </button>

            <!-- Selector de Jurisdicción Legal -->
            <button onclick="openJurisdictionModal()" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-amber-500/40 text-xs transition group shadow-sm" title="Configurar País y Marco Legal">
                <span id="jurisdiction-flag" class="text-sm">🇵🇪</span>
                <div class="text-left">
                    <span class="block text-[8px] uppercase tracking-wider text-slate-400 font-bold leading-none" data-i18n="jurisdiction_lbl">Jurisdicción:</span>
                    <span id="jurisdiction-name" class="font-bold text-slate-200 group-hover:text-amber-300 font-mono text-[11px] leading-tight">Perú</span>
                </div>
                <span id="jurisdiction-regs-count" class="text-[9px] bg-amber-500/10 text-amber-400 px-1.5 py-0.2 rounded font-mono border border-amber-500/20">5 leyes</span>
            </button>

            <!-- Botón de Chat Lateral -->
            <button onclick="toggleChatDrawer()" class="bg-slate-900 hover:bg-slate-800 text-indigo-300 border border-indigo-500/20 hover:border-indigo-500/40 font-semibold text-xs px-3 py-2 rounded-xl transition flex items-center gap-2 shadow-sm">
                <span>💬</span>
                <span data-i18n="consult_btn">Consultar</span>
                <span id="chat-badge" class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            </button>

            <!-- Botón Crear Misión / User Story -->
            <button onclick="openNewTaskModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center gap-1.5">
                <span>+</span> <span data-i18n="new_task_btn">Nueva Historia</span>
            </button>
        </div>
    </header>

    <!-- Barra de Navegación de Vistas Principales (Tabs) -->
    <nav class="bg-[#090d16] border-b border-slate-800/80 px-4 sm:px-6 py-2 flex items-center justify-between gap-3 text-xs overflow-x-auto">
        <div class="flex items-center gap-1.5 sm:gap-2">
            <button onclick="switchMainTab('kanban')" id="tab-btn-kanban" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-active">
                <span>📌</span> <span data-i18n="tab_kanban">Tablero Scrum</span>
            </button>
            <button onclick="switchMainTab('ux')" id="tab-btn-ux" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>🎨</span> <span data-i18n="tab_ux">Laboratorio UX/UI & Diseño</span>
                <span id="nav-ux-score" class="text-[10px] px-1.5 py-0.2 rounded-full bg-pink-500/10 text-pink-400 border border-pink-500/20 font-mono">94%</span>
            </button>
            <button onclick="switchMainTab('legal')" id="tab-btn-legal" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>⚖️</span> <span data-i18n="tab_legal">Auditoría Legal</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-mono">Compliance</span>
            </button>
            <button onclick="switchMainTab('meetings')" id="tab-btn-meetings" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>🎙️</span> <span data-i18n="tab_meetings">Sala de Reuniones & Consejos</span>
                <span id="nav-meetings-count" class="text-[10px] px-1.5 py-0.2 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-mono">0</span>
            </button>
            <button onclick="switchMainTab('team')" id="tab-btn-team" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>👥</span> <span data-i18n="tab_team">Escuadrón & Multi-Skills</span>
                <span id="nav-team-count" class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-300 font-mono">7</span>
            </button>
        </div>

        <div class="flex items-center gap-3 text-[11px] text-slate-400 whitespace-nowrap">
            <div class="flex items-center gap-1.5 text-slate-300">
                <span class="text-slate-500 font-bold uppercase text-[9px]">Proyecto:</span>
                <select id="project-selector-dropdown" onchange="handleSwitchProject(this.value)" class="font-mono bg-slate-950 border border-slate-800 text-indigo-300 px-2 py-0.5 rounded-lg text-[11px] outline-none cursor-pointer hover:border-indigo-500">
                    <option value="default">AgileForge Default</option>
                </select>
                <button onclick="openNewProjectModal()" class="text-xs px-2 py-0.5 rounded-lg bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 border border-indigo-500/30 font-bold transition" title="Crear nuevo proyecto">+</button>
                <button onclick="openImportWorkspaceModal()" class="text-xs px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 font-bold transition" title="Importar workspace existente con git log">📥</button>
            </div>
            <span class="hidden md:inline text-slate-500">|</span>
            <div class="hidden lg:flex items-center gap-1.5 text-slate-400 text-[10px]">
                <span class="text-slate-500 font-bold uppercase">Ruta:</span>
                <span id="active-workspace-display" class="font-mono truncate max-w-xs text-slate-300">—</span>
                <button onclick="openChangeWorkspaceModal()" class="text-slate-400 hover:text-white underline text-[10px]" title="Cambiar ruta de trabajo">✏️</button>
            </div>
            <span class="hidden md:inline text-slate-500">|</span>
            <span class="hidden md:inline"><strong class="text-emerald-400">DoD Cero Deuda</strong></span>
        </div>
    </nav>

    <!-- Sprint Banner (Métricas Scrum & Barra de Progreso) -->
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 pt-5 pb-2">
        <div id="sprint-hero-banner" class="sprint-banner bg-gradient-to-r from-slate-900/90 via-indigo-950/40 to-slate-900/90 border border-slate-800/80 rounded-3xl p-5 shadow-lg space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span id="sprint-number-badge" class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-mono">Sprint 1</span>
                        <h2 id="sprint-name" class="font-extrabold text-white text-base sm:text-lg">Cargando sprint…</h2>
                    </div>
                    <p id="sprint-goal" class="text-xs text-slate-300 max-w-2xl">

                    </p>
                </div>

                <!-- Métricas Numéricas del Sprint -->
                <div class="flex items-center gap-4 sm:gap-6 bg-slate-950/80 border border-slate-800/80 px-4 py-2.5 rounded-2xl">
                    <div>
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Velocidad</span>
                        <div class="text-base font-extrabold text-indigo-400 font-mono flex items-baseline gap-1">
                            <span id="sprint-points-done">0</span>
                            <span class="text-xs text-slate-500">/ <span id="sprint-velocity">24</span> pts</span>
                        </div>
                    </div>
                    <div class="w-px h-7 bg-slate-800"></div>
                    <div>
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Días Restantes</span>
                        <div class="text-base font-extrabold text-amber-400 font-mono flex items-baseline gap-1">
                            <span id="sprint-days-remaining">4</span>
                            <span class="text-xs text-slate-500">días</span>
                        </div>
                    </div>
                    <div class="w-px h-7 bg-slate-800"></div>
                    <div>
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Cumplimiento DoD</span>
                        <div class="text-base font-extrabold text-emerald-400 font-mono" id="sprint-dod-pct">
                            0%
                        </div>
                    </div>
                    <div class="w-px h-7 bg-slate-800"></div>
                    <div class="cursor-pointer hover:opacity-80 transition group" onclick="openCostsModal()" title="Ver auditoría contable de tokens y costos por agente">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider group-hover:text-purple-300">Inversión IA</span>
                        <div class="text-base font-extrabold text-purple-400 font-mono flex items-baseline gap-1">
                            <span id="sprint-total-cost">$0.0000</span>
                            <span class="text-xs text-slate-500 font-normal">(<span id="sprint-total-tokens">0</span> tok)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra Visual de Progreso del Sprint -->
            <div class="space-y-1">
                <div class="flex justify-between items-center text-[10px] text-slate-400 font-mono">
                    <span>Avance hacia la Definition of Done</span>
                    <span id="progress-bar-text">0% completado</span>
                </div>
                <div class="w-full bg-slate-950/90 rounded-full h-2.5 overflow-hidden border border-slate-800">
                    <div id="sprint-progress-bar" class="bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-400 h-2.5 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de Vistas Principales -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6">

        <!-- Aviso informativo del modo plugin (lo rellena app.js cuando no hay motor de IA en la web) -->
        <div id="plugin-notice" role="status" class="hidden mb-4 rounded-2xl border border-sky-500/30 bg-sky-950/30 px-4 py-2.5 text-xs text-sky-100"></div>

        <!-- VISTA 1: TABLERO KANBAN SCRUM (4 Columnas) -->
        <section id="view-kanban" class="space-y-4">
            <!-- 👑 Director General: recibe instrucciones, decide, delega y actualiza el tablero -->
            <div id="director-panel" class="hidden rounded-3xl border border-amber-500/30 bg-gradient-to-r from-amber-950/25 via-slate-950/90 to-slate-950/90 p-4 space-y-3 shadow-lg">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl p-1.5 rounded-2xl bg-amber-500/10 border border-amber-500/30">👑</span>
                        <div>
                            <h3 class="font-extrabold text-white text-sm" data-i18n="director_title">Director General · Jefe Máximo</h3>
                            <p class="text-[11px] text-slate-400" data-i18n="director_subtitle">Dale una instrucción: revisa el tablero, decide, delega en los agentes y te rinde cuentas.</p>
                        </div>
                    </div>
                    <button type="button" onclick="switchMainTab('team')" id="director-engine-badge" class="text-[10px] font-mono px-2 py-0.5 rounded-full border bg-slate-950 border-amber-500/40 text-amber-200">…</button>
                </div>
                <form id="director-form" onsubmit="sendDirectorInstruction(event)" class="flex flex-col sm:flex-row gap-2">
                    <textarea id="director-input" rows="2" maxlength="8000" data-i18n-placeholder="director_placeholder"
                              placeholder="Ej.: Prioriza lo necesario para lanzar reservas online esta semana y reparte el trabajo entre el equipo (Ctrl+Enter para enviar)"
                              class="flex-1 px-3.5 py-2.5 rounded-2xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-amber-500 resize-y min-h-[44px]"></textarea>
                    <button type="submit" id="director-send-btn" class="px-4 py-2 rounded-2xl bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/20 whitespace-nowrap" data-i18n="director_send">Dar instrucción</button>
                </form>
                <div class="flex flex-wrap gap-1.5 text-[10px]">
                    <span class="text-slate-500" data-i18n="director_quick">Rápidas:</span>
                    <button type="button" onclick="quickDirector('status')" class="px-2 py-0.5 rounded-md bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700" data-i18n="director_q_status">📊 Estado del sprint</button>
                    <button type="button" onclick="quickDirector('backlog')" class="px-2 py-0.5 rounded-md bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700" data-i18n="director_q_backlog">🧭 Ordenar backlog</button>
                    <button type="button" onclick="quickDirector('unblock')" class="px-2 py-0.5 rounded-md bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700" data-i18n="director_q_unblock">🚧 Desbloquear</button>
                </div>
                <div id="director-output" class="hidden p-3 rounded-2xl bg-slate-950/80 border border-slate-800"></div>
            </div>

            <!-- Barra de Filtros Rápidos del Tablero -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/60 border border-slate-800/80 p-3 rounded-2xl">
                <div class="flex items-center gap-2 flex-1">
                    <span class="text-slate-500 text-xs">🔍</span>
                    <input type="text" id="kanban-search" oninput="renderKanban()" placeholder="Filtrar historias por título, código o skill..."
                           class="bg-transparent text-xs text-slate-100 placeholder-slate-500 outline-none w-full sm:max-w-xs">
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <select id="kanban-filter-role" onchange="renderKanban()" class="bg-slate-950 border border-slate-700 text-slate-300 text-xs rounded-xl px-2.5 py-1.5 outline-none">
                        <option value="all">Todos los Agentes</option>
                        <!-- Dinámicos -->
                    </select>

                    <select id="kanban-filter-priority" onchange="renderKanban()" class="bg-slate-950 border border-slate-700 text-slate-300 text-xs rounded-xl px-2.5 py-1.5 outline-none">
                        <option value="all">Todas las Prioridades</option>
                        <option value="critica">Crítica (Bloqueante)</option>
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>

                    <select id="kanban-filter-skill" onchange="renderKanban()" class="bg-slate-950 border border-slate-700 text-slate-300 text-xs rounded-xl px-2.5 py-1.5 outline-none">
                        <option value="all">Todas las Skills</option>
                        <!-- Dinámicos -->
                    </select>

                    <span id="kanban-filter-count" class="text-[10px] font-mono text-slate-400 px-2.5 py-1 rounded-xl bg-slate-950 border border-slate-800 whitespace-nowrap">
                        Cargando...
                    </span>

                    <button onclick="openNewTaskModal()" class="text-xs px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold transition flex items-center gap-1 shadow-sm whitespace-nowrap">
                        <span>+</span> Nueva Historia
                    </button>
                </div>
            </div>

            <!-- Columnas del Tablero Kanban -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">

                <!-- Columna 1: Product / Sprint Backlog -->
                <div class="kanban-col bg-slate-900/60 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                    <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-200">Backlog</h4>
                        </div>
                        <span id="col-count-backlog" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">0 pts</span>
                    </div>
                    <div id="col-backlog" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                        <!-- Tarjetas dinámicas -->
                    </div>
                </div>

                <!-- Columna 2: En Progreso -->
                <div class="kanban-col bg-slate-900/60 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                    <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-amber-300">En Progreso</h4>
                        </div>
                        <span id="col-count-en_progreso" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-950/60 text-amber-300 border border-amber-500/20">0 pts</span>
                    </div>
                    <div id="col-en_progreso" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                        <!-- Tarjetas dinámicas -->
                    </div>
                </div>

                <!-- Columna 3: En Revisión / Auditoría DoD -->
                <div class="kanban-col bg-slate-900/60 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                    <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-purple-300">En Revisión (DoD)</h4>
                        </div>
                        <span id="col-count-revision" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-purple-950/60 text-purple-300 border border-purple-500/20">0 pts</span>
                    </div>
                    <div id="col-revision" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                        <!-- Tarjetas dinámicas -->
                    </div>
                </div>

                <!-- Columna 4: Completadas -->
                <div class="kanban-col bg-slate-900/60 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                    <div class="p-3 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-emerald-300">Completadas</h4>
                        </div>
                        <span id="col-count-completada" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-300 border border-emerald-500/20">0 pts</span>
                    </div>
                    <div id="col-completada" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                        <!-- Tarjetas dinámicas -->
                    </div>
                </div>

            </div>

            <!-- Feed de Actividad en Vivo de Agentes IA (Trazabilidad MCP) -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">📡</span>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-200">Feed de Actividad de Agentes IA (Trazabilidad MCP)</h4>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                        </span>
                        <span class="text-[10px] font-mono text-indigo-400 bg-indigo-950/60 px-2 py-0.5 rounded-full border border-indigo-500/30">
                            En vivo · SQLite WAL
                        </span>
                    </div>
                </div>
                <div id="mcp-activity-feed" class="space-y-2 max-h-48 overflow-y-auto pr-1 text-xs">
                    <!-- Eventos dinámicos de task_events -->
                </div>
            </div>
        </section>

        <!-- VISTA 2: LABORATORIO UX/UI & AUDITORÍA DE DISEÑO (Especialistas Evaluando y Actuando) -->
        <section id="view-ux" class="hidden space-y-6">
            <!-- Hero Banner con Evaluación Activa -->
            <div class="bg-gradient-to-r from-pink-950/40 via-purple-950/30 to-slate-900 border border-pink-500/30 rounded-3xl p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl p-2.5 bg-pink-500/10 border border-pink-500/30 rounded-2xl">🎨</span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-black text-xl text-white">Laboratorio Heurístico UX/UI & Auditoría en Vivo</h3>
                                <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-pink-500/10 text-pink-300 border border-pink-500/30 font-mono font-bold">10 Heurísticas de Nielsen</span>
                            </div>
                            <p class="text-xs text-pink-300/90">
                                Métricas reales calculadas sobre el DOM y checklist interactivo evaluado por <strong>Pixel Master</strong> y <strong>The Visionary</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Cumplimiento Heurístico</span>
                        <div id="ux-overall-score" class="text-3xl font-black text-pink-400 font-mono">92%</div>
                        <span class="text-[9px] text-slate-500 font-mono block">Persistido en SQLite</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button onclick="renderUxHonestView()" id="apply-ux-btn" class="px-4 py-2 bg-gradient-to-r from-pink-600 via-purple-600 to-indigo-600 hover:from-pink-500 hover:to-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2 whitespace-nowrap glow-fx">
                            <span>🔄</span> <span id="apply-ux-btn-text">Re-escanear DOM Actual</span>
                        </button>
                        <button onclick="exportUxReport()" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 text-[11px] rounded-xl transition flex items-center justify-center gap-1.5">
                            <span>📄</span> <span>Exportar Dictamen Honesto</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Consola de Acción y Mejoras Recomendadas en Vivo -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
                    <div>
                        <h4 class="font-bold text-sm text-white flex items-center gap-2">
                            <span>⚡</span> Consola de Optimización: Mejoras Aplicadas al Sistema en Tiempo Real
                        </h4>
                        <p class="text-xs text-slate-400">The Visionary y Pixel Master verifican y aplican estos principios de diseño directamente en la interfaz.</p>
                    </div>
                    <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-500/20">
                        ✓ 6 Optimizaciones Activas
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-start gap-2.5">
                        <span class="text-emerald-400 text-base">✓</span>
                        <div>
                            <strong class="text-white block">Contraste WCAG AAA</strong>
                            <p class="text-[11px] text-slate-400">Ratio de contraste de texto superior a 15:1 para eliminar fatiga visual.</p>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-start gap-2.5">
                        <span class="text-emerald-400 text-base">✓</span>
                        <div>
                            <strong class="text-white block">Micro-interacciones Fluidas</strong>
                            <p class="text-[11px] text-slate-400">Transiciones suaves de 200ms en botones, tabs y hover de tarjetas.</p>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-start gap-2.5">
                        <span class="text-emerald-400 text-base">✓</span>
                        <div>
                            <strong class="text-white block">Ergonomía Táctil Mobile-First</strong>
                            <p class="text-[11px] text-slate-400">Áreas de clic mínimas de 44x44px conforme a estándares de accesibilidad.</p>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-start gap-2.5">
                        <span class="text-emerald-400 text-base">✓</span>
                        <div>
                            <strong class="text-white block">Estética Anti-Slop</strong>
                            <p class="text-[11px] text-slate-400">Elegancia tipo obsidian dark, eliminando saturaciones innecesarias.</p>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-start gap-2.5">
                        <span class="text-emerald-400 text-base">✓</span>
                        <div>
                            <strong class="text-white block">Heurística #1 (Estado del Sistema)</strong>
                            <p class="text-[11px] text-slate-400">Banner de sprint en vivo, porcentaje de avance y badges de motor IA.</p>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-start gap-2.5">
                        <span class="text-emerald-400 text-base">✓</span>
                        <div>
                            <strong class="text-white block">Notificaciones Hápticas</strong>
                            <p class="text-[11px] text-slate-400">Toasts flotantes con retroalimentación no invasiva para cada acción.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Veredictos Formales del Diseñador y Especialista UX -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold text-white">
                            <span class="text-lg">🎨</span>
                            <span>Inspección de Accesibilidad & DOM (Pixel Master)</span>
                        </div>
                        <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-500/20">DOM en Vivo</span>
                    </div>
                    <p id="ux-specialist-notes" class="text-xs text-slate-300 leading-relaxed">
                        Cargando evaluación del DOM...
                    </p>
                </div>
                <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold text-white">
                            <span class="text-lg">💡</span>
                            <span>Métricas de Viewport & Ergonomía (The Visionary)</span>
                        </div>
                        <span class="text-[10px] font-mono text-pink-400 bg-pink-950/80 px-2 py-0.5 rounded border border-pink-500/20">Responsividad Real</span>
                    </div>
                    <p id="ux-designer-notes" class="text-xs text-slate-300 leading-relaxed">
                        Cargando métricas de ventana...
                    </p>
                </div>
            </div>

            <!-- Grid de las 5 Heurísticas Clave -->
            <div id="ux-pillars-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Se cargan dinámicamente con renderUxAudit() -->
            </div>
        </section>

        <!-- VISTA 3: AUDITORÍA LEGAL & COMPLIANCE (The Legal Shield) -->
        <section id="view-legal" class="hidden space-y-6">
            <div class="bg-gradient-to-r from-amber-950/30 via-slate-900 to-slate-900 border border-amber-500/20 rounded-3xl p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="text-3xl p-2 bg-amber-500/10 border border-amber-500/30 rounded-2xl">⚖️</span>
                        <div>
                            <h3 class="font-black text-xl text-white">Auditoría Legal del Workspace & Compliance</h3>
                            <p class="text-xs text-amber-300/90">
                                Certificación de Términos, Datos Personales (PII) y Libro de Reclamaciones por <strong>The Legal Shield</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-[9px] uppercase font-bold text-slate-400 block tracking-wider">Blindaje Legal</span>
                        <div id="legal-overall-score" class="text-2xl font-black text-amber-400 font-mono">Cargando...</div>
                    </div>
                    <button onclick="runLegalWorkspaceAudit(true)" id="run-legal-audit-btn" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-1.5 whitespace-nowrap">
                        <span>🔍</span> <span id="legal-audit-btn-text">Auditar Workspace Legal</span>
                    </button>
                </div>
            </div>

            <!-- Pilares Legales en el Workspace -->
            <div id="legal-pillars-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Se cargan dinámicamente -->
            </div>

            <!-- Generador de Documentos Jurídicos en Vivo -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div>
                        <h4 class="font-bold text-sm text-white flex items-center gap-2">
                            <span>📜</span> Generador Jurídico: Redactar Documento Oficial para el Proyecto
                        </h4>
                        <p class="text-xs text-slate-400">Genera términos o políticas adaptadas al país activo y guárdalas directamente en el workspace.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Tipo de Documento</label>
                        <select id="legal-gen-type" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none">
                            <option value="terminos">Términos y Condiciones de Servicio & Exención</option>
                            <option value="privacidad">Política de Privacidad y Protección de Datos</option>
                            <option value="consentimiento">Snippet HTML: Checkbox de Consentimiento Informado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Nombre Comercial del Proyecto</label>
                        <input type="text" id="legal-gen-name" value="" placeholder="Nombre comercial (por defecto, el del proyecto)" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none">
                    </div>
                    <div class="flex items-end gap-2">
                        <button onclick="handleGenerateLegalDoc(false)" class="flex-1 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-indigo-300 font-bold rounded-xl border border-indigo-500/30 transition text-xs">
                            👁️ Previsualizar
                        </button>
                        <button onclick="handleGenerateLegalDoc(true)" class="flex-1 px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow transition text-xs">
                            💾 Guardar en Workspace
                        </button>
                    </div>
                </div>

                <!-- Visor del documento generado -->
                <div id="legal-preview-container" class="hidden space-y-2 pt-2">
                    <div class="flex items-center justify-between text-xs">
                        <span id="legal-preview-filename" class="font-mono text-indigo-400 font-bold">archivo.php</span>
                        <span id="legal-preview-status" class="text-emerald-400 font-semibold">Listo</span>
                    </div>
                    <textarea id="legal-preview-code" rows="8" readonly class="w-full p-3 rounded-xl bg-slate-950 border border-slate-800 text-slate-300 font-mono text-xs outline-none resize-none"></textarea>
                </div>
            </div>
        </section>

        <!-- VISTA 4: SALA DE REUNIONES MULTI-AGENTE & CONSEJOS ÁGILES (Brainstorming & Toma de Decisiones) -->
        <section id="view-meetings" class="hidden space-y-6">
            <!-- Hero Banner -->
            <div class="bg-gradient-to-r from-purple-950/40 via-indigo-950/30 to-slate-900 border border-purple-500/30 rounded-3xl p-6 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl p-2.5 bg-purple-500/10 border border-purple-500/30 rounded-2xl">🎙️</span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-black text-xl text-white">Sala de Reuniones Multi-Agente & Consejos Ágiles</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/30 font-mono font-bold">Colaboración Sinérgica</span>
                            </div>
                            <p class="text-xs text-purple-300/90">
                                Reúne a 2 o más agentes especializados para <strong>sesiones de brainstorming e innovación</strong> o <strong>toma de decisiones de arquitectura (ADR)</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="toggleMeetingComposer()" id="toggle-meet-composer-btn" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg transition flex items-center gap-2 whitespace-nowrap glow-fx">
                        <span>+</span> <span>Convocar Reunión</span>
                    </button>
                </div>
            </div>

            <!-- Presets Rápidos de Convocatoria (1 Clic) -->
            <div class="space-y-2">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Convocatorias Rápidas Preconfiguradas:</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                    <button type="button" onclick="launchPresetMeeting('brainstorming_ux')" class="text-left p-3.5 rounded-2xl bg-slate-900/80 hover:bg-slate-800 border border-pink-500/20 hover:border-pink-500/40 transition group space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-lg">💡</span>
                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-pink-950/60 text-pink-300 border border-pink-500/20">Brainstorming</span>
                        </div>
                        <h5 class="font-bold text-white group-hover:text-pink-300 transition">Innovación & Deleite UX</h5>
                        <p class="text-[11px] text-slate-400 leading-tight">The Visionary + Pixel Master + The Orchestrator</p>
                    </button>

                    <button type="button" onclick="launchPresetMeeting('adr_architecture')" class="text-left p-3.5 rounded-2xl bg-slate-900/80 hover:bg-slate-800 border border-indigo-500/20 hover:border-indigo-500/40 transition group space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-lg">🏛️</span>
                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-indigo-950/60 text-indigo-300 border border-indigo-500/20">Decisión ADR</span>
                        </div>
                        <h5 class="font-bold text-white group-hover:text-indigo-300 transition">Consejo de Arquitectura</h5>
                        <p class="text-[11px] text-slate-400 leading-tight">Lead Architect + Backend Master + QA Sentinel</p>
                    </button>

                    <button type="button" onclick="launchPresetMeeting('legal_compliance')" class="text-left p-3.5 rounded-2xl bg-slate-900/80 hover:bg-slate-800 border border-amber-500/20 hover:border-amber-500/40 transition group space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-lg">⚖️</span>
                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-amber-950/60 text-amber-300 border border-amber-500/20">Compliance</span>
                        </div>
                        <h5 class="font-bold text-white group-hover:text-amber-300 transition">Comité de Blindaje Legal</h5>
                        <p class="text-[11px] text-slate-400 leading-tight">The Legal Shield + QA Guard + The Orchestrator</p>
                    </button>

                    <button type="button" onclick="launchPresetMeeting('full_squad')" class="text-left p-3.5 rounded-2xl bg-slate-900/80 hover:bg-slate-800 border border-purple-500/20 hover:border-purple-500/40 transition group space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-lg">🚀</span>
                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-purple-950/60 text-purple-300 border border-purple-500/20">Plenaria</span>
                        </div>
                        <h5 class="font-bold text-white group-hover:text-purple-300 transition">Reunión Plenaria de Escuadrón</h5>
                        <p class="text-[11px] text-slate-400 leading-tight">Los 7 Agentes Especialistas</p>
                    </button>
                </div>
            </div>

            <!-- Formulario Desplegable de Convocatoria Personalizada -->
            <div id="meeting-composer" class="hidden bg-slate-900/80 border border-slate-800 rounded-3xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h4 class="font-bold text-sm text-white flex items-center gap-2">
                        <span>⚙️</span> Configurar Mesa de Reunión Personalizada
                    </h4>
                    <button type="button" onclick="toggleMeetingComposer()" class="text-slate-400 hover:text-white text-xs">Cerrar ✕</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Propósito de la Sesión *</label>
                        <select id="meet-type-select" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none">
                            <option value="brainstorming">💡 Brainstorming e Innovación Radical</option>
                            <option value="decision">🏛️ Toma de Decisiones Técnicas & Arquitectura (ADR)</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Tema / Objetivo de Debate *</label>
                        <input type="text" id="meet-topic-input" placeholder="Ej: Definir la estrategia de persistencia transaccional y caching..."
                               class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- Selección de Participantes -->
                <div class="space-y-1.5 text-xs">
                    <label class="block text-slate-400 uppercase font-bold text-[10px]">Agentes Convocados (Mínimo 2) *</label>
                    <div id="meet-participants-selector" class="flex flex-wrap gap-2 p-2 bg-slate-950 border border-slate-800 rounded-2xl">
                        <!-- Chips dinámicos con checkboxes -->
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                    <button type="button" onclick="toggleMeetingComposer()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs">Cancelar</button>
                    <button type="button" onclick="submitCustomMeeting()" class="px-5 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow">
                        🎙️ Iniciar Debate Multi-Agente
                    </button>
                </div>
            </div>

            <!-- Escenario Activo de la Reunión (Live Roundtable Stage) -->
            <div id="meeting-stage-container" class="space-y-4">
                <!-- Se pobla dinámicamente con renderActiveMeeting() -->
            </div>

            <!-- Historial de Reuniones Pasadas -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h4 class="font-bold text-sm text-white flex items-center gap-2">
                        <span>📜</span> Archivo de Actas & Reuniones Pasadas
                    </h4>
                    <span id="meetings-history-count" class="text-[10px] font-mono text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full">0 actas</span>
                </div>

                <div id="meetings-history-list" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Lista dinámica -->
                </div>
            </div>
        </section>

        <!-- VISTA 5: ESCUADRÓN DE AGENTES & CONFIGURACIÓN MULTI-SKILLS -->
        <section id="view-team" class="hidden space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-3">
                <div>
                    <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                        <span>🤖</span> Escuadrón Especialista de IA & Configuración Multi-Skills
                    </h3>
                    <p class="text-xs text-slate-400" data-i18n="team_subtitle">Asigna a cada rol su motor de IA (API y modelo, o IA local) y sus skills. El Director General es el jefe máximo del equipo.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openAddSkillModal()" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-indigo-300 border border-indigo-500/30 font-bold text-xs flex items-center gap-1.5 transition">
                        <span>⚡</span> + Nueva Skill Personalizada
                    </button>
                    <span id="role-count" class="text-xs text-slate-400 bg-slate-800 px-2.5 py-1 rounded-full font-mono">7 agentes</span>
                </div>
            </div>

            <div id="roles-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Se cargan dinámicamente con renderRoles() -->
            </div>
        </section>

    </main>

    <!-- Footer Oficial AgileForge -->
    <footer class="border-t border-slate-800/80 bg-[#070a12]/90 px-6 py-6 mt-auto">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="brand-logo-container h-8 w-8 p-1 rounded-xl flex items-center justify-center shrink-0">
                    <img id="footer-brand-logo" src="assets/brand/symbol-agileforge-dark.png" alt="AgileForge" class="brand-logo-img h-full w-full object-contain">
                </div>
                <div>
                    <p class="font-extrabold text-sm text-white tracking-tight flex items-center gap-2">
                        AgileForge <span class="text-xs text-indigo-400 font-normal">· Command Center Scrum 3.0</span>
                    </p>
                    <p class="text-[11px] text-slate-500">Suite de Agentes Autónomos, Métricas Scrum & Compliance Legal</p>
                </div>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <span>Versión 3.0.0</span>
                <span>•</span>
                <span class="font-mono text-[11px]">AgileForge System</span>
            </div>
        </div>
    </footer>

    <!-- Drawer Lateral: Chat de Consulta Ágil con los Agentes -->
    <div id="chat-drawer" class="fixed inset-y-0 right-0 w-full max-w-md bg-slate-900 border-l border-slate-800 shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/90">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">💬</span>
                <div>
                    <h3 class="font-extrabold text-sm text-white">Sala de Consulta Ágil</h3>
                    <p class="text-[11px] text-slate-400" data-i18n="chat_subtitle">Da instrucciones al Director o consulta a cualquier agente</p>
                </div>
            </div>
            <button onclick="toggleChatDrawer()" class="text-slate-400 hover:text-white text-xl p-1">✕</button>
        </div>

        <div class="px-4 py-2.5 bg-slate-950 border-b border-slate-800 flex items-center gap-2 text-xs">
            <label class="text-slate-400 whitespace-nowrap">Consultar a:</label>
            <select id="chat-target-role" class="w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 outline-none focus:border-indigo-500">
                <!-- Opciones dinámicas -->
            </select>
        </div>

        <div id="chat-messages" class="flex-1 p-4 space-y-3 overflow-y-auto">
            <!-- Mensajes dinámicos -->
        </div>

        <div class="px-4 py-1.5 border-t border-slate-800/80 bg-slate-950 flex gap-1.5 overflow-x-auto text-[10px]">
            <button onclick="quickChatPrompt('¿Cómo podemos optimizar la accesibilidad WCAG y el contraste de colores?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-pink-950/60 hover:bg-pink-900/60 text-pink-300 border border-pink-500/30">
                🎨 Mejora UX/UI
            </button>
            <button onclick="quickChatPrompt('¿Qué normativas legales y cláusulas de responsabilidad debemos cumplir?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-amber-950/60 hover:bg-amber-900/60 text-amber-300 border border-amber-500/30">
                ⚖️ Dictamen Legal
            </button>
            <button onclick="quickChatPrompt('¿Cómo podemos refactorizar este sprint para reducir riesgos de arquitectura?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300">
                🛡️ Reducir riesgos
            </button>
            <button onclick="quickChatPrompt('¿Cumplimos la Definition of Done en los tests y lint?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300">
                ✅ Verificar DoD
            </button>
        </div>

        <form id="chat-form" onsubmit="handleSendChat(event)" class="p-3.5 border-t border-slate-800 bg-slate-900/90 flex gap-2">
            <input type="text" id="chat-input" placeholder="Haz una consulta estratégica a este agente..."
                   class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-xs text-white placeholder-slate-500 outline-none focus:border-indigo-500">
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow flex items-center gap-1">
                <span>Enviar</span>
            </button>
        </form>
    </div>

    <!-- Modal: Personalizador de Tema Visual & Densidad Ergonomica -->
    <div id="theme-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🎨</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Diseño & Ergonomía Visual</h3>
                        <p class="text-xs text-slate-400">Personaliza la estética, contraste y densidad del Command Center</p>
                    </div>
                </div>
                <button onclick="closeThemeModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1.5">Paleta de Diseño</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="selectTheme('obsidian')" class="p-2.5 rounded-xl border border-slate-700 hover:border-indigo-500 bg-[#070a12] text-left flex items-center gap-2 transition theme-option" id="theme-opt-obsidian">
                            <span class="w-3 h-3 rounded-full bg-slate-400"></span>
                            <span class="font-bold text-slate-200">Cyber Obsidian (Noche)</span>
                        </button>
                        <button type="button" onclick="selectTheme('indigo')" class="p-2.5 rounded-xl border border-slate-700 hover:border-indigo-500 bg-[#050a18] text-left flex items-center gap-2 transition theme-option" id="theme-opt-indigo">
                            <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
                            <span class="font-bold text-slate-200">Electric Indigo (Noche)</span>
                        </button>
                        <button type="button" onclick="selectTheme('emerald')" class="p-2.5 rounded-xl border border-slate-700 hover:border-emerald-500 bg-[#03100a] text-left flex items-center gap-2 transition theme-option" id="theme-opt-emerald">
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            <span class="font-bold text-slate-200">Neon Emerald (Noche)</span>
                        </button>
                        <button type="button" onclick="selectTheme('violet')" class="p-2.5 rounded-xl border border-slate-700 hover:border-purple-500 bg-[#0c0718] text-left flex items-center gap-2 transition theme-option" id="theme-opt-violet">
                            <span class="w-3 h-3 rounded-full bg-purple-500"></span>
                            <span class="font-bold text-slate-200">Midnight Violet (Noche)</span>
                        </button>
                        <button type="button" onclick="selectTheme('light')" class="p-2.5 rounded-xl border border-slate-700 hover:border-amber-500 bg-white text-left flex items-center gap-2 transition theme-option" id="theme-opt-light">
                            <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                            <span class="font-bold text-slate-800">☀️ Solar Day (Modo Día)</span>
                        </button>
                        <button type="button" onclick="selectTheme('paper')" class="p-2.5 rounded-xl border border-slate-700 hover:border-orange-500 bg-[#fbfbf9] text-left flex items-center gap-2 transition theme-option" id="theme-opt-paper">
                            <span class="w-3 h-3 rounded-full bg-orange-300"></span>
                            <span class="font-bold text-slate-800">📄 Clean Paper (Día Suave)</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1.5">Densidad de Información</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="setDensity('comfortable')" id="density-btn-comfortable" class="p-2.5 rounded-xl border border-indigo-500 bg-slate-950 font-bold text-white text-center">
                            👌 Cómodo (Default)
                        </button>
                        <button type="button" onclick="setDensity('compact')" id="density-btn-compact" class="p-2.5 rounded-xl border border-slate-700 bg-slate-950 text-slate-400 font-bold text-center">
                            📊 Compacto (Scrum Pro)
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeThemeModal()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow">Listo</button>
            </div>
        </div>
    </div>

    <!-- Modal: Manual de Inicio Rápido para Novatos -->
    <div id="beginner-manual-modal" class="fixed inset-0 bg-slate-950/85 backdrop-blur-md z-50 hidden flex items-center justify-center p-3 sm:p-5">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
            <!-- Cabecera del Manual -->
            <div class="p-5 sm:p-6 border-b border-slate-800 flex items-center justify-between gap-4 bg-slate-950/60">
                <div class="flex items-center gap-3">
                    <span class="text-3xl p-2 rounded-2xl bg-indigo-500/10 border border-indigo-500/20">📖</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-extrabold text-base sm:text-lg text-white tracking-tight" data-i18n="manual_title">Manual de Inicio Rápido para Novatos</h3>
                            <span class="text-[9px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">Paso a Paso</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5" data-i18n="manual_subtitle">Aprende a usar AgileForge y a colaborar con agentes de IA sin complicaciones.</p>
                    </div>
                </div>
                <button onclick="closeBeginnerManualModal()" class="text-slate-400 hover:text-white text-xl p-1.5 rounded-xl hover:bg-slate-800 transition">✕</button>
            </div>

            <!-- Contenido Scrollable del Manual -->
            <div class="p-5 sm:p-6 overflow-y-auto space-y-6 text-xs text-slate-300 leading-relaxed">
                <!-- Sección 1: ¿Qué es? -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-2">
                    <div class="flex items-center gap-2 text-indigo-400 font-bold text-sm">
                        <span>🔰</span> <h4>1. ¿Qué es AgileForge y para qué sirve?</h4>
                    </div>
                    <p class="text-slate-300">
                        AgileForge es tu <strong>centro de comando de desarrollo ágil (Scrum)</strong> impulsado por agentes de Inteligencia Artificial. En lugar de copiar y pegar prompts gigantescos, aquí tienes un escuadrón de <strong>7 especialistas</strong> (Project Manager, Diseñador UX/UI, Arquitecto, Frontend, Backend, QA/Seguridad y Abogado Legal) que leen el tablero, toman tareas y debaten soluciones contigo.
                    </p>
                </div>

                <!-- Sección 2: Colores y Modo Día / Noche -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-amber-500/20 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2 text-amber-400 font-bold text-sm">
                            <span>🌓</span> <h4>2. Colores: Modo Noche y Modo Día</h4>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="selectTheme('light')" class="px-2.5 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-300 font-bold text-[11px] transition">☀️ Probar Día</button>
                            <button type="button" onclick="selectTheme('obsidian')" class="px-2.5 py-1 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 font-bold text-[11px] transition">🌙 Probar Noche</button>
                        </div>
                    </div>
                    <p class="text-slate-300">
                        AgileForge cuenta con <strong>6 temas visuales calibrados</strong>. Puedes alternar al instante entre Modo Día y Modo Noche haciendo clic en el botón <strong>🌙 Noche / ☀️ Día</strong> en la barra superior.
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-slate-400 pl-1">
                        <li><strong>Modos Oscuros / Noche:</strong> <em>Cyber Obsidian</em> (predeterminado), <em>Electric Indigo</em>, <em>Neon Emerald</em> y <em>Midnight Violet</em>. Ideales para programar de noche con mínimo cansancio visual.</li>
                        <li><strong>Modos Claros / Día:</strong> <em>Solar Day</em> (fondo blanco de alto contraste) y <em>Clean Paper</em> (fondo cálido suave). Ideales para trabajar en ambientes iluminados o proyectores.</li>
                    </ul>
                </div>

                <!-- Sección 3: Idioma -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-sky-500/20 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2 text-sky-400 font-bold text-sm">
                            <span>🌐</span> <h4>3. Idioma: Español e Inglés</h4>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="setLanguage('es')" class="px-2.5 py-1 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 text-sky-300 font-bold text-[11px] transition">🇪🇸 Español</button>
                            <button type="button" onclick="setLanguage('en')" class="px-2.5 py-1 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 text-sky-300 font-bold text-[11px] transition">🇺🇸 English</button>
                        </div>
                    </div>
                    <p class="text-slate-300">
                        Puedes cambiar el idioma de la aplicación en cualquier momento con el botón <strong>🌐 ES | EN</strong> ubicado en la cabecera. La preferencia se guarda automáticamente en tu navegador.
                    </p>
                </div>

                <!-- Sección 4: Tablero Scrum -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-2">
                    <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm">
                        <span>📌</span> <h4>4. El Tablero Scrum (Cómo mover tus tareas)</h4>
                    </div>
                    <p class="text-slate-300">El trabajo se organiza en 4 columnas transparentes:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                        <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                            <strong class="text-indigo-300 block mb-1">1. Historias (Backlog):</strong>
                            Tareas pendientes. Pulsa el botón <strong>"+ Nueva Historia"</strong> para crear un requerimiento con sus criterios de aceptación (DoD).
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                            <strong class="text-amber-300 block mb-1">2. En Desarrollo:</strong>
                            Tareas activas. Un agente o tú la toman (bloqueo atómico para que dos personas no hagan lo mismo).
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                            <strong class="text-purple-300 block mb-1">3. Revisión DoD / QA:</strong>
                            El código o diseño está listo y aporta evidencia (tests pasados, archivos cambiados).
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                            <strong class="text-emerald-300 block mb-1">4. Aprobadas / Done:</strong>
                            Regla de oro: <em>Ningún agente puede aprobarse a sí mismo</em>. Solo tú como humano o el rol QA pueden marcarla como completada.
                        </div>
                    </div>
                </div>

                <!-- Sección 5: IA Local en tu PC -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-emerald-500/20 space-y-2">
                    <div class="flex items-center gap-2 text-emerald-400 font-bold text-sm">
                        <span>🦙</span> <h4>5. IA Local en tu PC (100% Gratis y Privada)</h4>
                    </div>
                    <p class="text-slate-300">
                        Si tienes instalado <strong>Ollama</strong> o <strong>Colibri</strong> en tu máquina, AgileForge lo detecta en segundo plano. Al activar el interruptor <strong>🦙 IA local</strong> en la cabecera:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-slate-400 pl-1">
                        <li>Todas las respuestas del chat y los debates de las reuniones correrán en tu propia tarjeta gráfica o RAM.</li>
                        <li><strong>Cero costo en dólares</strong> y privacidad total: ningún código ni dato confidencial sale a internet.</li>
                    </ul>
                </div>

                <!-- Sección 6: Salas de Reunión -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-purple-500/20 space-y-2">
                    <div class="flex items-center gap-2 text-purple-400 font-bold text-sm">
                        <span>🎙️</span> <h4>6. Salas de Reuniones Multi-Agente</h4>
                    </div>
                    <p class="text-slate-300">
                        Ve a la pestaña <strong>"Sala de Reuniones"</strong> para reunir a 2 o más agentes. Puedes convocar un <em>Brainstorming de Innovación</em> o un <em>Consejo de Arquitectura (ADR)</em>. Los agentes deliberarán por turnos y redactarán un acta formal con acuerdos y tareas que podrás enviar directo al Backlog con un solo clic.
                    </p>
                </div>

                <!-- Sección 7: Atajos -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-2">
                    <div class="flex items-center gap-2 text-slate-200 font-bold text-sm">
                        <span>⌨️</span> <h4>7. Atajos Rápidos de Teclado</h4>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-mono text-center">
                        <div class="p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-indigo-400 font-bold block text-xs">Ctrl + K</span>
                            <span class="text-slate-400 text-[10px]">Buscar historias</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-amber-400 font-bold block text-xs">Alt + M</span>
                            <span class="text-slate-400 text-[10px]">Abrir este manual</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-emerald-400 font-bold block text-xs">Alt + 1..5</span>
                            <span class="text-slate-400 text-[10px]">Cambiar pestañas</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-900 border border-slate-800">
                            <span class="text-rose-400 font-bold block text-xs">ESC</span>
                            <span class="text-slate-400 text-[10px]">Cerrar modales</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer del Modal -->
            <div class="p-4 sm:p-5 border-t border-slate-800 flex items-center justify-between gap-3 bg-slate-950/60">
                <span class="text-[11px] text-slate-400">¿Dudas? Consulta <code class="text-indigo-300">MANUAL_NOVATOS.md</code> (ES) o <code class="text-indigo-300">BEGINNERS_GUIDE.md</code> (EN) en el repositorio.</span>
                <button type="button" onclick="closeBeginnerManualModal()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg transition">¡Entendido, a trabajar!</button>
            </div>
        </div>
    </div>

    <!-- Modal: Command Palette (Ctrl + K) -->
    <div id="command-palette-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-start justify-center pt-20 p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl max-w-lg w-full p-4 space-y-3 shadow-2xl">
            <div class="flex items-center gap-3 border-b border-slate-800 pb-3 px-2">
                <span class="text-slate-400 text-lg">🔍</span>
                <input type="text" id="palette-search-input" oninput="handlePaletteSearch()" placeholder="Escribe un comando o busca una historia..."
                       class="w-full bg-transparent text-white font-medium text-sm outline-none placeholder-slate-500">
                <kbd class="text-[10px] bg-slate-950 px-2 py-0.5 rounded border border-slate-700 font-mono text-slate-400">ESC</kbd>
            </div>

            <div id="palette-results" class="max-h-80 overflow-y-auto space-y-1 text-xs">
                <!-- Se poblan dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Modal: Cambiar Carpeta de Trabajo (Workspace) -->
    <div id="workspace-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <h3 class="font-bold text-base text-white flex items-center gap-2">
                    <span>📂</span> Cambiar Carpeta de Trabajo
                </h3>
                <button onclick="closeChangeWorkspaceModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>
            <p class="text-xs text-slate-300">
                Ingresa la ruta absoluta de la carpeta sobre la cual operarán los agentes (ej: el repo del proyecto en desarrollo).
            </p>
            <div>
                <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Ruta del Proyecto</label>
                <input type="text" id="workspace-input" placeholder="Ej: D:\xampp\htdocs\mi-proyecto"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-mono text-xs focus:border-indigo-500 outline-none">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeChangeWorkspaceModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs">Cancelar</button>
                <button type="button" onclick="saveWorkspaceChange()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30">Actualizar Workspace</button>
            </div>
        </div>
    </div>

    <!-- Modal: Configurar Jurisdicción Legal & Normativas por País -->
    <div id="jurisdiction-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-amber-500/10 border border-amber-500/20 rounded-xl">⚖️</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Jurisdicción & Normativas Legales</h3>
                        <p class="text-xs text-amber-300/90">Especialización por país para el Abogado IA & Compliance Officer</p>
                    </div>
                </div>
                <button onclick="closeJurisdictionModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <!-- Botón de Detección Automática Inteligente -->
            <div class="bg-gradient-to-r from-amber-950/40 via-slate-950 to-slate-950 border border-amber-500/20 rounded-2xl p-3.5 flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-xs font-bold text-white flex items-center gap-1.5">
                        <span>⚡</span> Detección Automática de País
                    </h4>
                    <p class="text-[11px] text-slate-400">Detecta tu ubicación y marco legal mediante tu zona horaria y red local.</p>
                </div>
                <button type="button" onclick="autoDetectCountry()" class="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs rounded-xl shadow transition flex items-center gap-1.5 whitespace-nowrap">
                    <span>🌍</span> <span id="autodetect-btn-text">Detectar Ahora</span>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">País / Marco Jurídico *</label>
                    <select id="jurisdiction-select" onchange="handleCountryPresetChange(this.value)"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-medium text-xs focus:border-amber-500 outline-none">
                        <option value="PE">🇵🇪 Perú (LPDP, INDECOPI, SUNAT)</option>
                        <option value="ES">🇪🇸 España / UE (GDPR/RGPD, LSSI, Cookies)</option>
                        <option value="MX">🇲🇽 México (LFPDPPP, PROFECO, SAT)</option>
                        <option value="CO">🇨🇴 Colombia (Habeas Data Ley 1581, SIC)</option>
                        <option value="CL">🇨🇱 Chile (Ley 19.628, SERNAC, SII)</option>
                        <option value="AR">🇦🇷 Argentina (Habeas Data Ley 25.326, AFIP)</option>
                        <option value="US">🇺🇸 Estados Unidos (CCPA, ADA, FTC)</option>
                        <option value="GLOBAL">🌐 Internacional / Multirregional</option>
                        <option value="CUSTOM">⚙️ Personalizado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Nombre & Bandera Personalizados</label>
                    <div class="flex gap-2">
                        <input type="text" id="custom-flag-input" placeholder="Bandera (ej: 🇵🇪)" class="w-16 px-2.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-center text-sm outline-none focus:border-amber-500">
                        <input type="text" id="custom-country-input" placeholder="Nombre de país..." class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-amber-500">
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-slate-400 uppercase font-bold text-[10px]">Normativas Legales Activas a Auditar</label>
                    <span id="active-regs-badge" class="text-[10px] font-mono text-amber-300 bg-amber-950/70 px-2 py-0.5 rounded-full border border-amber-500/20">0 activas</span>
                </div>

                <div id="regulations-list" class="max-h-40 overflow-y-auto space-y-1.5 p-1">
                    <!-- Chips de normativas dinámicas -->
                </div>

                <div class="flex gap-2 pt-1">
                    <input type="text" id="new-regulation-input" placeholder="Agregar ley o normativa (ej: Ley de Cookies o HIPAA)..."
                           onkeydown="if(event.key === 'Enter'){ event.preventDefault(); addCustomRegulation(); }"
                           class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-amber-500">
                    <button type="button" onclick="addCustomRegulation()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-amber-300 font-bold text-xs rounded-xl border border-amber-500/30 transition">
                        + Agregar
                    </button>
                </div>

                <div class="flex items-center gap-1.5 flex-wrap pt-1 text-[10px]">
                    <span class="text-slate-500 font-medium">Sugerencias rápidas:</span>
                    <button type="button" onclick="quickAddRegulation('Libro de Reclamaciones Virtual Obligatorio')" class="px-2 py-0.5 rounded-md bg-slate-800/80 hover:bg-slate-700 text-slate-300 border border-slate-700">+ Libro de Reclamaciones</button>
                    <button type="button" onclick="quickAddRegulation('Política de Privacidad y Consentimiento Expreso (PII)')" class="px-2 py-0.5 rounded-md bg-slate-800/80 hover:bg-slate-700 text-slate-300 border border-slate-700">+ Privacidad PII</button>
                    <button type="button" onclick="quickAddRegulation('Términos de Servicio y Exoneración de Responsabilidad')" class="px-2 py-0.5 rounded-md bg-slate-800/80 hover:bg-slate-700 text-slate-300 border border-slate-700">+ Términos & Exoneración</button>
                    <button type="button" onclick="quickAddRegulation('Consentimiento de Cookies y Rastreadores')" class="px-2 py-0.5 rounded-md bg-slate-800/80 hover:bg-slate-700 text-slate-300 border border-slate-700">+ Banner de Cookies</button>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeJurisdictionModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs">Cancelar</button>
                <button type="button" onclick="saveJurisdictionSettings()" class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20">Guardar Jurisdicción</button>
            </div>
        </div>
    </div>

    <!-- Modal: Conector de Motores IA & Multi-LLM Hub -->
    <div id="ai-engine-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-purple-500/10 border border-purple-500/20 rounded-xl">🧠</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Multi-LLM Hub & Conectores de IA</h3>
                        <p class="text-xs text-slate-400">Alterna entre APIs y sabe en todo momento con qué modelo estás desarrollando.</p>
                    </div>
                </div>
                <button onclick="closeAiEngineModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <div class="bg-slate-950 border border-slate-800/80 rounded-2xl p-3.5 space-y-2">
                <h4 class="text-xs font-bold text-purple-300 flex items-center gap-1.5">
                    <span>🔍</span> Trazabilidad: ¿Cómo saber en todo momento qué IA está operando?
                </h4>
                <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300">
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <strong class="text-white block mb-0.5">1. En el Header:</strong>
                        Badge en vivo en la barra superior con icono y nombre del modelo.
                    </div>
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <strong class="text-white block mb-0.5">2. En el Tablero Kanban:</strong>
                        Cada Historia de Usuario lleva el pill de la IA asignada.
                    </div>
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <strong class="text-white block mb-0.5">3. En el Chat:</strong>
                        Cada respuesta del agente lleva la firma del modelo que la emitió.
                    </div>
                    <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <strong class="text-white block mb-0.5">4. En el Prompt:</strong>
                        Inyecta el comando CLI exacto (<code class="text-indigo-300">claude</code>, <code class="text-indigo-300">agy</code>, <code class="text-indigo-300">ollama</code>).
                    </div>
                </div>
            </div>

            <div class="space-y-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Proveedor de IA (Engine / API) *</label>
                        <select id="ai-provider-select" onchange="handleProviderChange(this.value)"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-medium text-xs focus:border-purple-500 outline-none">
                            <option value="anthropic">🟣 Anthropic Claude (Claude Code / API)</option>
                            <option value="openai">🟢 OpenAI (GPT-4o, o3-mini, API)</option>
                            <option value="google">✨ Google Gemini (Gemini 2.5 Pro, Antigravity)</option>
                            <option value="deepseek">🐳 DeepSeek (DeepSeek-R1 Reasoner / V3)</option>
                            <option value="ollama">🦙 Ollama Local (100% en tu PC)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Modelo Específico *</label>
                        <select id="ai-model-select"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-medium text-xs focus:border-purple-500 outline-none">
                            <!-- Opciones dinámicas -->
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Modo de Ejecución</label>
                        <select id="ai-execution-mode"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-medium text-xs focus:border-purple-500 outline-none">
                            <option value="cli">💻 Modo Terminal / CLI (Claude Code, agy, ollama)</option>
                            <option value="api">🌐 Modo API Directa (HTTP REST / JSON)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Endpoint / URL de Conexión</label>
                        <input type="text" id="ai-endpoint-input" placeholder="https://api.anthropic.com/v1"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-mono text-xs focus:border-purple-500 outline-none">
                    </div>
                </div>

                <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">
                    <div>
                        <span class="font-bold text-white block">🎯 Enrutamiento Especializado por Rol (Role Routing)</span>
                        <p class="text-[11px] text-slate-400">Cada agente usa el motor que le asignes en "Equipo de Agentes" (ej.: Director con Claude Opus 5.5, Creativo con Gemini, QA con IA local). Si a un rol le falta la clave, usa la IA local o el motor global.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="ai-role-routing-toggle" class="sr-only peer" checked onchange="toggleRoleRoutingImmediate(this.checked)">
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>
            </div>

            <!-- Claves de API: se guardan en el .env del servidor y nunca vuelven al navegador -->
            <div class="p-3.5 rounded-2xl bg-slate-950 border border-purple-500/20 space-y-2.5 text-xs">
                <div>
                    <span class="font-bold text-white block" data-i18n="api_keys_title">🔑 Claves de API</span>
                    <p class="text-[11px] text-slate-400" data-i18n="api_keys_desc">Pega tu clave para que los roles con ese proveedor respondan desde AgileForge. Se guarda en el archivo .env de esta PC; el navegador solo ve una pista (ej. sk-ant-…a1b2).</p>
                    <p class="text-[11px] mt-1">📘 <a href="https://github.com/JSOTELD/agile-forge/blob/main/docs/API_KEYS.es.md" target="_blank" rel="noopener" class="text-purple-300 hover:underline">Guía: cómo conseguir y agregar claves</a> · <a href="https://github.com/JSOTELD/agile-forge/blob/main/docs/API_KEYS.md" target="_blank" rel="noopener" class="text-purple-300 hover:underline">Guide: how to get and add API keys</a></p>
                </div>
                <div id="api-keys-list" class="space-y-2"></div>
            </div>

            <!-- IA local en esta PC (Ollama) -->
            <div class="p-3.5 rounded-2xl bg-slate-950 border border-emerald-500/20 space-y-2.5 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <span class="font-bold text-white block">🦙 IA local en esta PC</span>
                        <p class="text-[11px] text-slate-400">Si está activada, el chat y las reuniones las responde un modelo local (🦙 Ollama o 🐦 Colibri) en tu computadora. Nada sale a internet.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0" title="Activar o desactivar la IA local">
                        <input type="checkbox" id="local-ai-modal-switch" class="sr-only peer" onchange="setLocalAiEnabled(this.checked)">
                        <div class="w-11 h-6 bg-slate-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600 peer-disabled:opacity-40"></div>
                    </label>
                </div>
                <p id="local-ai-status-text" class="text-[11px] text-slate-300 font-mono">Buscando IA local…</p>
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto] gap-2 items-end">
                    <div>
                        <label for="local-ai-model-select" class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Modelo local</label>
                        <select id="local-ai-model-select" onchange="changeLocalAiModel(this.value)"
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 font-mono text-[11px] focus:border-emerald-500 outline-none"></select>
                    </div>
                    <button type="button" onclick="detectLocalAi()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-[11px]">🔍 Detectar</button>
                    <button type="button" onclick="testLocalAi()" class="px-3 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-white font-bold text-[11px]">⚡ Probar</button>
                </div>
                <p id="local-ai-test-output" class="text-[11px] text-slate-400 min-h-[1rem]" aria-live="polite"></p>
                <label class="flex items-start gap-2 pt-2 border-t border-slate-800 cursor-pointer">
                    <input type="checkbox" id="local-ai-colibri-control" onchange="setColibriControl(this.checked)"
                           class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-900 accent-sky-500">
                    <span>
                        <span class="font-bold text-slate-200 block">🐦 Permitir que AgileForge encienda y apague Colibri</span>
                        <span id="local-ai-colibri-control-note" class="text-[11px] text-slate-400 block">Solo cuando tú actives la IA local con Colibri.</span>
                    </span>
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeAiEngineModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs">Cancelar</button>
                <button type="button" onclick="saveAiEngineSettings()" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-lg shadow-purple-600/30">Aplicar Motor IA</button>
            </div>
        </div>
    </div>

    <!-- Modal: Auditoría de Costos y Tokens por Agente/Sprint -->
    <div id="costs-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl flex flex-col max-h-[85vh]">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-purple-500/10 border border-purple-500/20 rounded-xl">💰</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Auditoría Contable de Tokens & Inversión IA</h3>
                        <p class="text-xs text-slate-400">Consumo real medido por llamada, agente, modelo y sprint activo.</p>
                    </div>
                </div>
                <button onclick="closeCostsModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <!-- Resumen numérico -->
            <div class="grid grid-cols-4 gap-2 text-center text-xs">
                <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800/80">
                    <span class="text-[10px] text-slate-400 block uppercase font-bold">Llamadas API</span>
                    <span id="modal-costs-calls" class="text-base font-extrabold text-white font-mono">0</span>
                </div>
                <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800/80">
                    <span class="text-[10px] text-slate-400 block uppercase font-bold">Tokens Entrada</span>
                    <span id="modal-costs-input-tok" class="text-base font-extrabold text-indigo-400 font-mono">0</span>
                </div>
                <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800/80">
                    <span class="text-[10px] text-slate-400 block uppercase font-bold">Tokens Salida</span>
                    <span id="modal-costs-output-tok" class="text-base font-extrabold text-purple-400 font-mono">0</span>
                </div>
                <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800/80">
                    <span class="text-[10px] text-slate-400 block uppercase font-bold">Inversión (USD)</span>
                    <span id="modal-costs-total-usd" class="text-base font-extrabold text-emerald-400 font-mono">$0.0000</span>
                </div>
            </div>

            <!-- Presupuesto por Sprint (USD) -->
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-white uppercase tracking-wider block">Presupuesto del Sprint</span>
                        <span id="modal-costs-budget-status" class="text-[11px] text-slate-400 font-mono">$0.00 gastados de $0.00 límite</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-slate-400 font-bold">$</span>
                            <input type="number" id="modal-costs-budget-input" step="0.5" min="0" placeholder="5.00" class="w-20 px-2 py-1 rounded-lg bg-slate-900 border border-slate-700 text-white text-xs font-mono outline-none focus:border-purple-500">
                        </div>
                        <button type="button" onclick="saveSprintBudget()" class="px-3 py-1 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition">
                            Fijar
                        </button>
                    </div>
                </div>
                <!-- Barra de Progreso de Presupuesto -->
                <div class="w-full bg-slate-900 rounded-full h-2.5 overflow-hidden border border-slate-800">
                    <div id="modal-costs-budget-bar" class="h-full bg-emerald-500 transition-all duration-300" style="width: 0%"></div>
                </div>
                <!-- Alerta cuando se acerca o supera el límite -->
                <div id="modal-costs-budget-warning" class="hidden p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 text-xs flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-base">⚠️</span>
                        <span id="modal-costs-budget-warning-text">Te estás acercando al límite de tu presupuesto. Considera activar la IA local (Ollama) para llamadas sin costo de tokens.</span>
                    </div>
                    <button type="button" onclick="closeCostsModal(); openAiEngineModal();" class="shrink-0 px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 font-bold text-[11px] transition">
                        🦙 Ver IA local
                    </button>
                </div>
            </div>

            <!-- Desglose por agente -->
            <div class="space-y-2 flex-1 overflow-y-auto">
                <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Desglose por Agente</h4>
                <div id="modal-costs-by-agent" class="space-y-1.5 text-xs">
                    <!-- Dinámico -->
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-between items-center text-xs">
                <span class="text-slate-500 text-[11px]">Tarifas basadas en <code class="text-purple-300">config/models.json</code> por millón de tokens.</span>
                <button type="button" onclick="closeCostsModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal: Crear Nuevo Proyecto -->
    <div id="new-project-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-indigo-500/10 border border-indigo-500/20 rounded-xl">📁</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Nuevo Proyecto / Módulo</h3>
                        <p class="text-xs text-slate-400">Crea un espacio de trabajo aislado con su propio sprint y backlog en SQLite.</p>
                    </div>
                </div>
                <button onclick="closeNewProjectModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Nombre del Proyecto *</label>
                    <input type="text" id="new-project-name" placeholder="Ej: App móvil, Core Backend, Portal B2B"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Ruta en Servidor Local (Opcional)</label>
                    <input type="text" id="new-project-path" placeholder="Ej: D:\xampp\htdocs\mi-app"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-mono text-xs outline-none focus:border-indigo-500">
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Si se deja vacío, usará el directorio raíz de AgileForge.</span>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800 text-xs">
                <button type="button" onclick="closeNewProjectModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cancelar</button>
                <button type="button" onclick="submitNewProject()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Crear Proyecto</button>
            </div>
        </div>
    </div>

    <!-- Modal: Importar Workspace Existente (Git Log Scanner) -->
    <div id="import-workspace-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-purple-500/10 border border-purple-500/20 rounded-xl">📥</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Importar Workspace Existente</h3>
                        <p class="text-xs text-slate-400">Escanea repositorios locales y lee el <code class="text-purple-300">git log</code> para sugerir un backlog inicial.</p>
                    </div>
                </div>
                <button onclick="closeImportWorkspaceModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Ruta Absoluta del Directorio Local *</label>
                    <input type="text" id="import-workspace-path" placeholder="Ej: D:\xampp\htdocs\mi-proyecto"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 font-mono text-xs outline-none focus:border-purple-500">
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Debe ser un directorio existente dentro de localhost / htdocs.</span>
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Nombre del Proyecto (Opcional)</label>
                    <input type="text" id="import-workspace-name" placeholder="Se detectará automáticamente del nombre de la carpeta"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-purple-500">
                </div>
            </div>

            <div class="p-3 rounded-2xl bg-slate-950/60 border border-slate-800 text-[11px] text-slate-400 space-y-1">
                <strong class="text-purple-300 flex items-center gap-1"><span>🔍</span> Proceso de Onboarding Automatizado:</strong>
                <p>Si la carpeta contiene un repositorio <code class="text-slate-300">.git</code>, AgileForge leerá los últimos commits, calculará puntos de historia sugeridos y asignará agentes especialistas para poblar el Backlog inicial sin trabajo manual.</p>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800 text-xs">
                <button type="button" onclick="closeImportWorkspaceModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cancelar</button>
                <button type="button" onclick="submitImportWorkspace()" class="px-5 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold shadow-lg shadow-purple-600/30">
                    📥 Importar y Generar Backlog
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Configurar Multi-Skills por Agente con Buscador y Creación -->
    <div id="skills-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl flex flex-col max-h-[85vh]">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-indigo-500/10 border border-indigo-500/20 rounded-xl">⚡</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Catálogo de Skills & Toolkit</h3>
                        <p id="skills-modal-agent" class="text-xs text-indigo-400 font-mono">Agente</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openAddSkillModal()" class="px-2.5 py-1 rounded-lg bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 border border-indigo-500/30 font-bold text-xs flex items-center gap-1 transition">
                        <span>+</span> Crear Skill
                    </button>
                    <button onclick="closeSkillsConfig()" class="text-slate-400 hover:text-white text-xl">✕</button>
                </div>
            </div>

            <div class="space-y-2 text-xs">
                <div class="relative">
                    <input type="text" id="skills-search-input" oninput="filterSkillsCatalog()" placeholder="🔍 Buscar skill por nombre, ID o descripción (ej: responsive, wcag, clean code, sql)..."
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-indigo-500">
                </div>

                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px]" id="skills-category-chips">
                    <button type="button" onclick="filterSkillsByCategory('all')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold whitespace-nowrap">Todas</button>
                    <button type="button" onclick="filterSkillsByCategory('design')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap">🎨 Diseño & UX</button>
                    <button type="button" onclick="filterSkillsByCategory('product')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap">💡 Estrategia</button>
                    <button type="button" onclick="filterSkillsByCategory('agile')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap">📋 Scrum</button>
                    <button type="button" onclick="filterSkillsByCategory('engineering')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap">📐 Arquitectura</button>
                    <button type="button" onclick="filterSkillsByCategory('qa')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap">🧪 QA</button>
                    <button type="button" onclick="filterSkillsByCategory('legal')" class="skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap">⚖️ Legal</button>
                </div>
            </div>

            <div id="skills-cards-container" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 overflow-y-auto flex-1 p-1 max-h-80">
                <!-- Tarjetas dinámicas con renderSkillsCards() -->
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-800 text-xs">
                <span id="skills-selected-count" class="text-indigo-400 font-mono font-bold">0 skills activas</span>
                <div class="flex gap-2">
                    <button type="button" onclick="closeSkillsConfig()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cancelar</button>
                    <button type="button" onclick="saveAgentSkills()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Guardar Toolkit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Crear Nueva Skill Personalizada -->
    <div id="add-skill-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">⚡</span>
                    <h3 class="font-bold text-base text-white">Crear Nueva Skill Especializada</h3>
                </div>
                <button onclick="closeAddSkillModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <form id="add-skill-form" onsubmit="handleCreateCustomSkill(event)" class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Nombre de la Skill *</label>
                    <input type="text" id="new-skill-name" required placeholder="Ej: GraphQL Federation Master"
                           class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Categoría</label>
                        <select id="new-skill-category" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none">
                            <option value="design">🎨 Diseño & UI/UX</option>
                            <option value="product">💡 Estrategia</option>
                            <option value="agile">📋 Scrum</option>
                            <option value="engineering" selected>📐 Arquitectura & Backend</option>
                            <option value="qa">🧪 QA & Seguridad</option>
                            <option value="legal">⚖️ Legal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Icono Emoji</label>
                        <input type="text" id="new-skill-icon" value="⚡" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-center outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Descripción del Objetivo</label>
                    <input type="text" id="new-skill-desc" required placeholder="Ej: Orquestación de microservicios GraphQL con subgrafos Apollo."
                           class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Snippet de Instrucciones / Prompt</label>
                    <textarea id="new-skill-prompt" rows="3" placeholder="Instrucciones concretas que se inyectarán al agente al activar esta skill..."
                              class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs font-mono outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                    <button type="button" onclick="closeAddSkillModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow">Guardar Skill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Detalle / Ficha Técnica de Skill -->
    <div id="skill-detail-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span id="detail-skill-icon" class="text-2xl">⚡</span>
                    <div>
                        <h3 id="detail-skill-name" class="font-bold text-base text-white">Nombre de Skill</h3>
                        <span id="detail-skill-id" class="text-[10px] font-mono text-indigo-400">id-skill</span>
                    </div>
                </div>
                <button onclick="closeSkillDetailModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block mb-1">Descripción:</span>
                    <p id="detail-skill-desc" class="text-slate-200 leading-relaxed"></p>
                </div>

                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block mb-1">Prompt Inyectado / Instrucción del Agente:</span>
                    <textarea id="detail-skill-snippet" rows="4" readonly
                              class="w-full p-3 rounded-xl bg-slate-950 border border-slate-800 text-indigo-300 font-mono text-xs outline-none resize-none"></textarea>
                </div>

                <div>
                    <span class="text-slate-400 uppercase font-bold text-[10px] block mb-1">Roles Recomendados:</span>
                    <div id="detail-skill-roles" class="flex flex-wrap gap-1.5"></div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeSkillDetailModal()" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal: Memoria Nodular por Agente y Proyecto -->
    <div id="agent-memory-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl flex flex-col max-h-[85vh]">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl p-2 bg-purple-500/10 border border-purple-500/20 rounded-xl">🧠</span>
                    <div>
                        <h3 class="font-bold text-base text-white">Memoria Nodular Interna</h3>
                        <p class="text-xs text-slate-400">
                            <span id="agent-memory-role-name" class="text-purple-300 font-bold">Agente</span>
                            (<span id="agent-memory-role-id" class="font-mono text-slate-400">rol</span>) ·
                            Proyecto: <span id="agent-memory-project-name" class="text-indigo-300 font-mono">activo</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleAddMemoryForm()" class="px-3 py-1.5 rounded-xl bg-purple-600/30 hover:bg-purple-600/50 text-purple-200 border border-purple-500/40 text-xs font-bold flex items-center gap-1 transition">
                        <span>+</span> Crear Nodo
                    </button>
                    <button type="button" onclick="closeAgentMemoryModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
                </div>
            </div>

            <!-- Formulario Colapsable para Crear Nodo de Memoria -->
            <div id="agent-memory-create-form" class="hidden p-4 rounded-2xl bg-slate-950 border border-purple-500/30 space-y-3">
                <h4 class="text-xs font-bold text-purple-300 uppercase tracking-wider">Nuevo Nodo de Memoria</h4>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Tipo de Nodo</label>
                        <select id="new-memory-type" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-xs outline-none">
                            <option value="progress">📈 Avance / Entregable</option>
                            <option value="decision">⚖️ Decisión Clave</option>
                            <option value="learning" selected>💡 Aprendizaje</option>
                            <option value="scratchpad">📝 Nota Rápida</option>
                            <option value="domain">📐 Conocimiento de Dominio</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Importancia (1-10)</label>
                        <select id="new-memory-importance" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-xs outline-none font-mono">
                            <option value="10">10 (Crítico - Siempre en prompt)</option>
                            <option value="8">8 (Alta relevancia)</option>
                            <option value="5" selected>5 (Media / Estándar)</option>
                            <option value="3">3 (Baja prioridad)</option>
                            <option value="1">1 (Contextual)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Ámbito</label>
                        <select id="new-memory-scope" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-xs outline-none">
                            <option value="role" selected>Solo este agente</option>
                            <option value="all">Todo el equipo (Global)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Título del Concepto</label>
                    <input type="text" id="new-memory-title" placeholder="Ej: Regla de CORS y puertos en endpoints de pago"
                           class="w-full px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-xs outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Contenido / Síntesis de Conocimiento</label>
                    <textarea id="new-memory-content" rows="3" placeholder="Describe los datos, reglas o conclusiones aprendidas para que el agente no arranque desde cero..."
                              class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-200 text-xs outline-none focus:border-purple-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" onclick="toggleAddMemoryForm(false)" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold">Cancelar</button>
                    <button type="button" onclick="saveNewAgentMemory()" class="px-4 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition">Guardar Memoria</button>
                </div>
            </div>

            <!-- Filtros por tipo de nodo -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px]" id="memory-filter-chips">
                <button type="button" onclick="filterAgentMemories('all')" class="mem-filter-btn px-2.5 py-1 rounded-lg bg-purple-600 text-white font-bold whitespace-nowrap">Todas</button>
                <button type="button" onclick="filterAgentMemories('progress')" class="mem-filter-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white whitespace-nowrap">📈 Avances</button>
                <button type="button" onclick="filterAgentMemories('decision')" class="mem-filter-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white whitespace-nowrap">⚖️ Decisiones</button>
                <button type="button" onclick="filterAgentMemories('learning')" class="mem-filter-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white whitespace-nowrap">💡 Aprendizajes</button>
                <button type="button" onclick="filterAgentMemories('scratchpad')" class="mem-filter-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white whitespace-nowrap">📝 Notas</button>
                <button type="button" onclick="filterAgentMemories('domain')" class="mem-filter-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white whitespace-nowrap">📐 Dominio</button>
            </div>

            <!-- Lista de Nodos de Memoria -->
            <div id="agent-memory-list" class="space-y-2 flex-1 overflow-y-auto pr-1 text-xs">
                <!-- Se inyecta dinámicamente -->
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-between items-center text-xs">
                <span class="text-slate-500 text-[11px]">Las memorias activas se inyectan en el prompt del agente (hasta 1500 car.) priorizando mayor puntaje.</span>
                <button type="button" onclick="closeAgentMemoryModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal: Asignar Nueva Historia de Usuario (Scrum) con Selector de Skills -->
    <div id="new-task-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div>
                    <h3 class="font-bold text-base text-white flex items-center gap-2">
                        <span>🎯</span> Crear Historia de Usuario (Scrum)
                    </h3>
                    <p class="text-xs text-slate-400">Inyecta rol, workspace, multi-skills y Definition of Done.</p>
                </div>
                <button onclick="closeNewTaskModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <form id="task-form" onsubmit="handleCreateTask(event)" class="space-y-3.5 text-xs overflow-y-auto flex-1 pr-1">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Título de la Misión / Historia *</label>
                    <input type="text" id="task-title" required placeholder="Ej: Formulario de registro con validación"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs">
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Agente Asignado *</label>
                        <select id="task-role" onchange="populateTaskSkillsSelector(this.value)" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none">
                            <!-- Opciones cargadas dinámicamente -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Story Points (Fib)</label>
                        <select id="task-points" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none font-mono">
                            <option value="1">1 pt (Trivial)</option>
                            <option value="2">2 pts (Menor)</option>
                            <option value="3" selected>3 pts (Estándar)</option>
                            <option value="5">5 pts (Complejo)</option>
                            <option value="8">8 pts (Épica)</option>
                            <option value="13">13 pts (Mayor)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Prioridad</label>
                        <select id="task-priority" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none">
                            <option value="media">Media</option>
                            <option value="alta">Alta</option>
                            <option value="critica">Crítica (Bloqueante)</option>
                            <option value="baja">Baja</option>
                        </select>
                    </div>
                </div>

                <!-- Skills Específicas Asignadas a esta Historia -->
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Skills Requeridas para esta Misión</label>
                    <div id="task-skills-selector" class="flex flex-wrap gap-1.5 p-2 bg-slate-950 border border-slate-800 rounded-xl min-h-[42px]">
                        <!-- Chips dinámicos seleccionables -->
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Historia de Usuario (Formato Ágil)</label>
                    <input type="text" id="task-user-story" placeholder="Como cliente quiero crear mi cuenta en un paso para empezar a usar el servicio"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs">
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Definition of Done (DoD - Criterios de Aceptación)</label>
                    <input type="text" id="task-dod" value="Lint 0 errores, tests automatizados en verde, sin romper arquitectura."
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs">
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Detalles Técnicos y Archivos</label>
                    <textarea id="task-desc" rows="3" placeholder="Especificaciones de implementación, rutas de archivos a editar o precauciones..."
                              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                    <button type="button" onclick="closeNewTaskModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold shadow-lg shadow-indigo-600/30" data-i18n="task_create_btn">Crear historia y generar prompt</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Detalle y edición de una historia -->
    <div id="task-detail-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-start gap-3 border-b border-slate-800 pb-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[11px] font-mono">
                        <span id="detail-task-id" class="font-bold text-indigo-400">US-000</span>
                        <span id="detail-task-status" class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 uppercase">backlog</span>
                    </div>
                    <h3 id="detail-task-title" class="font-bold text-base text-white mt-1">Historia</h3>
                </div>
                <button onclick="closeTaskDetail()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>
            <div class="space-y-3 text-xs overflow-y-auto flex-1 pr-1">
                <div class="grid grid-cols-3 gap-2.5">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1" data-i18n="detail_agent">Agente</label>
                        <select id="detail-task-role" class="w-full px-2.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500"></select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1" data-i18n="detail_priority">Prioridad</label>
                        <select id="detail-task-priority" class="w-full px-2.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500">
                            <option value="critica">Crítica</option><option value="alta">Alta</option><option value="media">Media</option><option value="baja">Baja</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1" data-i18n="detail_points">Puntos</label>
                        <select id="detail-task-points" class="w-full px-2.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500 font-mono">
                            <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="5">5</option><option value="8">8</option><option value="13">13</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1" data-i18n="detail_story">Historia de usuario</label>
                    <textarea id="detail-task-story" rows="2" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500"></textarea>
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Definition of Done</label>
                    <textarea id="detail-task-dod" rows="2" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500"></textarea>
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1" data-i18n="detail_desc">Detalles técnicos</label>
                    <textarea id="detail-task-desc" rows="3" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none focus:border-indigo-500"></textarea>
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Skills</label>
                    <div id="detail-task-skills" class="flex flex-wrap gap-1.5"></div>
                </div>
            </div>
            <div class="flex flex-wrap justify-between gap-2 pt-2 border-t border-slate-800">
                <div class="flex gap-2">
                    <button type="button" onclick="askDirectorAboutTask()" class="px-3 py-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-200 border border-amber-500/30 font-bold text-xs" data-i18n="detail_ask_director">👑 Pedir decisión al Director</button>
                    <button type="button" onclick="showTaskPrompt(document.getElementById('detail-task-id').textContent)" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs">📋 Prompt</button>
                    <button type="button" onclick="deleteTaskFromDetail()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-rose-900/60 text-slate-400 hover:text-rose-200 font-bold text-xs" title="Eliminar historia">🗑️</button>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="closeTaskDetail()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs" data-i18n="btn_cancel">Cancelar</button>
                    <button type="button" onclick="saveTaskDetail()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30" data-i18n="detail_save">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Visor de Prompt Autogenerado -->
    <div id="prompt-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span id="prompt-modal-badge" class="text-xs px-2.5 py-1 rounded-full font-bold bg-indigo-500/20 text-indigo-400 font-mono">Prompt de Misión</span>
                    <h3 id="prompt-modal-title" class="font-bold text-white text-base truncate max-w-md">Título</h3>
                </div>
                <button onclick="closePromptModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <div class="relative">
                <textarea id="prompt-modal-text" rows="12" readonly
                          class="w-full p-4 rounded-2xl bg-slate-950 border border-slate-800 text-indigo-300 font-mono text-xs leading-relaxed outline-none resize-none"></textarea>
                <button onclick="copyPromptText()" class="absolute top-3 right-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow transition flex items-center gap-1.5">
                    <span>📋</span> <span id="copy-btn-text">Copiar Prompt</span>
                </button>
            </div>

            <div class="flex justify-between items-center pt-2 text-xs text-slate-400">
                <p>Pega este prompt directamente en la terminal de <strong>Claude Code</strong> o <strong>Antigravity CLI</strong>.</p>
                <button onclick="closePromptModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold">Entendido</button>
            </div>
        </div>
    </div>

    <!-- Contenedor Flotante de Toast Notifications -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none"></div>

    <!-- Script de Control Frontend con Cache-Busting y Módulos ES -->
    <script type="module" src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
