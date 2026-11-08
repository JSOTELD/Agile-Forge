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
    <title>AgileForge · Command Center Scrum & Multi-Skills de Agentes IA</title>
    <!-- Favicon Oficial AgileForge -->
    <link rel="icon" type="image/png" href="assets/brand/favicon.png">
    <link rel="apple-touch-icon" href="assets/brand/favicon.png">
    <!-- Tailwind CSS CDN para renderizado dinámico -->
    <script src="https://cdn.tailwindcss.com"></script>
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
            <div class="h-10 w-10 p-1 bg-gradient-to-br from-indigo-500/20 via-purple-600/15 to-pink-500/10 border border-indigo-500/30 rounded-2xl shadow-inner flex items-center justify-center shrink-0">
                <img src="assets/brand/symbol-agileforge-dark.png" alt="AgileForge Logo" class="h-full w-full object-contain filter drop-shadow">
            </div>
            <div>
                <h1 class="font-extrabold text-lg text-white tracking-tight flex items-center gap-2">
                    AgileForge <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">Scrum 3.0</span>
                </h1>
                <p class="text-[11px] text-slate-400">Command Center de Agentes IA · Suite de Ingeniería, Diseño UX y Compliance</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- Command Palette Rápido (Ctrl+K) -->
            <button onclick="openCommandPalette()" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 text-xs text-slate-300 transition group shadow-sm" title="Abrir Command Palette (Ctrl + K)">
                <span>🔍</span>
                <span class="hidden sm:inline text-[11px] text-slate-400">Buscar</span>
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
                    <span class="block text-[8px] uppercase tracking-wider text-slate-400 font-bold leading-none">Motor IA:</span>
                    <span id="header-ai-model" class="font-bold text-white group-hover:text-purple-300 font-mono text-[11px] leading-tight">Claude 3.7 Sonnet</span>
                </div>
                <span class="text-[10px] text-slate-500 ml-0.5">⚙️</span>
            </button>

            <!-- Selector de Jurisdicción Legal -->
            <button onclick="openJurisdictionModal()" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 hover:border-amber-500/40 text-xs transition group shadow-sm" title="Configurar País y Marco Legal">
                <span id="jurisdiction-flag" class="text-sm">🇵🇪</span>
                <div class="text-left">
                    <span class="block text-[8px] uppercase tracking-wider text-slate-400 font-bold leading-none">Jurisdicción:</span>
                    <span id="jurisdiction-name" class="font-bold text-slate-200 group-hover:text-amber-300 font-mono text-[11px] leading-tight">Perú</span>
                </div>
                <span id="jurisdiction-regs-count" class="text-[9px] bg-amber-500/10 text-amber-400 px-1.5 py-0.2 rounded font-mono border border-amber-500/20">5 leyes</span>
            </button>

            <!-- Botón de Chat Lateral -->
            <button onclick="toggleChatDrawer()" class="bg-slate-900 hover:bg-slate-800 text-indigo-300 border border-indigo-500/20 hover:border-indigo-500/40 font-semibold text-xs px-3 py-2 rounded-xl transition flex items-center gap-2 shadow-sm">
                <span>💬</span>
                <span>Consultar</span>
                <span id="chat-badge" class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            </button>

            <!-- Botón Crear Misión / User Story -->
            <button onclick="openNewTaskModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-3.5 py-2 rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center gap-1.5">
                <span>+</span> Nueva Historia
            </button>
        </div>
    </header>

    <!-- Barra de Navegación de Vistas Principales (Tabs) -->
    <nav class="bg-[#090d16] border-b border-slate-800/80 px-4 sm:px-6 py-2 flex items-center justify-between gap-3 text-xs overflow-x-auto">
        <div class="flex items-center gap-1.5 sm:gap-2">
            <button onclick="switchMainTab('kanban')" id="tab-btn-kanban" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-active">
                <span>📌</span> <span>Tablero Scrum</span>
            </button>
            <button onclick="switchMainTab('ux')" id="tab-btn-ux" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>🎨</span> <span>Laboratorio UX/UI & Diseño</span>
                <span id="nav-ux-score" class="text-[10px] px-1.5 py-0.2 rounded-full bg-pink-500/10 text-pink-400 border border-pink-500/20 font-mono">94%</span>
            </button>
            <button onclick="switchMainTab('legal')" id="tab-btn-legal" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>⚖️</span> <span>Auditoría Legal</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-mono">Compliance</span>
            </button>
            <button onclick="switchMainTab('meetings')" id="tab-btn-meetings" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>🎙️</span> <span>Sala de Reuniones & Consejos</span>
                <span id="nav-meetings-count" class="text-[10px] px-1.5 py-0.2 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-mono">0</span>
            </button>
            <button onclick="switchMainTab('team')" id="tab-btn-team" class="px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive">
                <span>👥</span> <span>Escuadrón & Multi-Skills</span>
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
                <span id="active-workspace-display" class="font-mono truncate max-w-xs text-slate-300">D:\xampp\htdocs\canil perruno</span>
                <button onclick="openChangeWorkspaceModal()" class="text-slate-400 hover:text-white underline text-[10px]" title="Cambiar ruta de trabajo">✏️</button>
            </div>
            <span class="hidden md:inline text-slate-500">|</span>
            <span class="hidden md:inline"><strong class="text-emerald-400">DoD Cero Deuda</strong></span>
        </div>
    </nav>

    <!-- Sprint Banner (Métricas Scrum & Barra de Progreso) -->
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 pt-5 pb-2">
        <div class="bg-gradient-to-r from-slate-900/90 via-indigo-950/40 to-slate-900/90 border border-slate-800/80 rounded-3xl p-5 shadow-lg space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span id="sprint-number-badge" class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-mono">Sprint 1</span>
                        <h2 id="sprint-name" class="font-extrabold text-white text-base sm:text-lg">Sprint 1: Experiencia Vareon & Movilidad Inteligente</h2>
                    </div>
                    <p id="sprint-goal" class="text-xs text-slate-300 max-w-2xl">
                        Desplegar el selector de distritos, cotizador de movilidad por km y refinamiento UX con DoD 100% verde.
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

        <!-- VISTA 1: TABLERO KANBAN SCRUM (4 Columnas) -->
        <section id="view-kanban" class="space-y-4">
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
                        <input type="text" id="legal-gen-name" value="Canil Perruno · Hotel & Guardería" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 outline-none">
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
                    <p class="text-xs text-slate-400">Cada agente combina un rol de ingeniería con un motor IA asignado y un paquete de skills activadas.</p>
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
                <img src="assets/brand/symbol-agileforge-dark.png" alt="AgileForge" class="h-7 w-auto object-contain">
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
                    <p class="text-[11px] text-slate-400">Pregunta a tu Diseñador, Especialista UX, PM o Abogado</p>
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
                            <span class="font-bold text-slate-200">Cyber Obsidian</span>
                        </button>
                        <button type="button" onclick="selectTheme('indigo')" class="p-2.5 rounded-xl border border-slate-700 hover:border-indigo-500 bg-[#050a18] text-left flex items-center gap-2 transition theme-option" id="theme-opt-indigo">
                            <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
                            <span class="font-bold text-slate-200">Electric Indigo</span>
                        </button>
                        <button type="button" onclick="selectTheme('emerald')" class="p-2.5 rounded-xl border border-slate-700 hover:border-emerald-500 bg-[#03100a] text-left flex items-center gap-2 transition theme-option" id="theme-opt-emerald">
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            <span class="font-bold text-slate-200">Neon Emerald</span>
                        </button>
                        <button type="button" onclick="selectTheme('violet')" class="p-2.5 rounded-xl border border-slate-700 hover:border-purple-500 bg-[#0c0718] text-left flex items-center gap-2 transition theme-option" id="theme-opt-violet">
                            <span class="w-3 h-3 rounded-full bg-purple-500"></span>
                            <span class="font-bold text-slate-200">Midnight Violet</span>
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
                <input type="text" id="workspace-input" placeholder="D:\xampp\htdocs\canil perruno"
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
                            <option value="ollama">🦙 Ollama Local (Llama 3.3, 100% On-Premise)</option>
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
                        <p class="text-[11px] text-slate-400">Permite que cada agente use su IA ideal (ej: Abogado y Arquitecto con Claude 3.7, Creativo con Gemini 2.5, QA con DeepSeek-R1).</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="ai-role-routing-toggle" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>
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
                    <input type="text" id="new-project-name" placeholder="Ej: Vareon Mobile App, Core Backend, Portal B2B"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Ruta en Servidor Local (Opcional)</label>
                    <input type="text" id="new-project-path" placeholder="Ej: D:\xampp\htdocs\vareon-mobile"
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
                    <input type="text" id="import-workspace-path" placeholder="Ej: D:\xampp\htdocs\canil perruno"
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
                    <input type="text" id="task-title" required placeholder="Ej: Implementar cotizador de movilidad por km y mapa"
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
                    <input type="text" id="task-user-story" placeholder="Como cliente quiero ver el costo por km para presupuestar la estadía de mi perro"
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
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Generar Prompt con Multi-Skills</button>
                </div>
            </form>
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
