<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgileForge · Command Center Scrum & Multi-Skills de Agentes IA</title>
    <!-- Tailwind CSS CDN para renderizado instantáneo y dinámico -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        /* Scrollbar elegante para paneles y columnas */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0b0f19; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Topbar & Header Principal -->
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-40 px-4 sm:px-6 py-3.5 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-3xl p-2 bg-indigo-600/20 border border-indigo-500/30 rounded-2xl shadow-inner">⚡</span>
            <div>
                <h1 class="font-extrabold text-lg text-white tracking-tight flex items-center gap-2">
                    AgileForge <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">Scrum & PMBOK Engine</span>
                </h1>
                <p class="text-xs text-slate-400">Command Center de Agentes IA · Empresa de Software Ágil de Primer Nivel</p>
            </div>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <!-- Botón para alternar Drawer de Chat de Agentes -->
            <button onclick="toggleChatDrawer()" class="bg-slate-800 hover:bg-slate-700 text-indigo-300 border border-indigo-500/20 hover:border-indigo-500/40 font-semibold text-xs px-3.5 py-2 rounded-xl transition flex items-center gap-2 shadow-sm">
                <span>💬</span>
                <span>Consultar a Agentes</span>
                <span id="chat-badge" class="w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
            </button>

            <!-- Indicador & Selector de Motor IA Activo -->
            <button onclick="openAiEngineModal()" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/90 hover:bg-slate-700/90 border border-slate-700 hover:border-indigo-500/50 text-xs transition group shadow-sm" title="Cambiar Motor de IA (Claude, OpenAI, Gemini, DeepSeek, Ollama)">
                <span id="header-ai-icon" class="text-sm">🟣</span>
                <div class="text-left">
                    <span class="block text-[9px] uppercase tracking-wider text-slate-400 font-bold leading-none">Motor IA:</span>
                    <span id="header-ai-model" class="font-bold text-white group-hover:text-indigo-300 font-mono text-[11px] leading-tight">Claude 3.7 Sonnet</span>
                </div>
                <span class="text-[10px] text-slate-400 ml-0.5">⚙️</span>
            </button>

            <!-- Botón Crear Misión / User Story -->
            <button onclick="openNewTaskModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2 rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center gap-1.5">
                <span>+</span> Nueva Historia de Usuario
            </button>
        </div>
    </header>

    <!-- Barra de Workspace Activo & Jurisdicción Legal -->
    <div class="bg-slate-900 border-b border-slate-800/80 px-4 sm:px-6 py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-2 text-slate-300">
                <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Workspace:</span>
                <span id="active-workspace-display" class="font-mono bg-slate-950 border border-slate-800 text-indigo-300 px-2.5 py-1 rounded-lg select-all">
                    D:\xampp\htdocs\canil perruno
                </span>
                <button onclick="openChangeWorkspaceModal()" title="Cambiar ruta de trabajo" class="text-slate-400 hover:text-white underline hover:no-underline font-medium ml-1">
                    ✏️ Cambiar
                </button>
            </div>

            <div class="h-4 w-px bg-slate-800 hidden sm:block"></div>

            <!-- Jurisdicción y Abogado Legal -->
            <div class="flex items-center gap-2">
                <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Jurisdicción Legal:</span>
                <button onclick="openJurisdictionModal()" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800/80 border border-amber-500/30 text-amber-300 transition group shadow-sm" title="Configurar País y Normativas Legales">
                    <span id="jurisdiction-flag" class="text-sm">🇵🇪</span>
                    <span id="jurisdiction-name" class="font-bold text-slate-200 group-hover:text-amber-300">Perú</span>
                    <span id="jurisdiction-regs-count" class="text-[10px] bg-amber-500/10 text-amber-400 px-1.5 py-0.2 rounded font-mono border border-amber-500/20">5 leyes</span>
                    <span class="text-[11px] text-slate-400">⚖️ Configurar</span>
                </button>
            </div>
        </div>

        <div class="flex items-center gap-3 text-slate-400 text-[11px]">
            <span class="hidden md:inline">Metodología: <strong class="text-slate-200">Scrum + PMBOK</strong></span>
            <span>Legal: <strong class="text-amber-400">Abogado IA Online</strong></span>
            <span>DoD: <strong class="text-emerald-400">100% Blindado</strong></span>
        </div>
    </div>

    <!-- Sprint Banner (Métricas Scrum) -->
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 pt-5 pb-2">
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-800 rounded-3xl p-5 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span id="sprint-number-badge" class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-mono">Sprint 1</span>
                    <h2 id="sprint-name" class="font-extrabold text-white text-base sm:text-lg">Sprint 1: Experiencia Vareon & Movilidad Inteligente</h2>
                </div>
                <p id="sprint-goal" class="text-xs text-slate-300 max-w-2xl">
                    Desplegar el selector de distritos, cotizador de movilidad por km y refinamiento UX con DoD 100% verde.
                </p>
            </div>

            <!-- Métricas Clave del Sprint -->
            <div class="flex items-center gap-4 sm:gap-6 bg-slate-950/70 border border-slate-800/80 px-4 py-3 rounded-2xl">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Velocidad Sprint</span>
                    <div class="text-base font-extrabold text-indigo-400 font-mono flex items-baseline gap-1">
                        <span id="sprint-points-done">0</span>
                        <span class="text-xs text-slate-500">/ <span id="sprint-velocity">24</span> pts</span>
                    </div>
                </div>
                <div class="w-px h-8 bg-slate-800"></div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Tiempo Restante</span>
                    <div class="text-base font-extrabold text-amber-400 font-mono flex items-baseline gap-1">
                        <span id="sprint-days-remaining">4</span>
                        <span class="text-xs text-slate-500">días</span>
                    </div>
                </div>
                <div class="w-px h-8 bg-slate-800"></div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Cumplimiento DoD</span>
                    <div class="text-base font-extrabold text-emerald-400 font-mono" id="sprint-dod-pct">
                        0%
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal: Tablero Kanban Scrum (4 Columnas) -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 space-y-6">

        <!-- Barra de Filtro y Resumen -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
            <div>
                <h2 class="font-extrabold text-lg text-white flex items-center gap-2">
                    <span>📌</span> Tablero Kanban de Scrum
                </h2>
                <p class="text-xs text-slate-400">Flujo ágil con Historias de Usuario, Story Points, Criterios de Aceptación y DoD estricto.</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="toggleRolesSection()" id="toggle-team-btn" class="text-xs px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1.5">
                    <span>👥</span> <span id="toggle-team-text">Ver Escuadrón de Agentes (7)</span>
                </button>
            </div>
        </div>

        <!-- Sección Desplegable: Escuadrón de Agentes & Multi-Skills -->
        <section id="roles-section" class="hidden bg-slate-900/60 border border-slate-800 rounded-3xl p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                <div>
                    <h3 class="font-bold text-sm text-white flex items-center gap-2">
                        <span>🤖</span> Escuadrón Especialista de IA & Configuración Multi-Skills
                    </h3>
                    <p class="text-xs text-slate-400">Cada agente combina roles de ingeniería con paquetes de skills activadas en Claude Code.</p>
                </div>
                <span id="role-count" class="text-xs text-slate-400 bg-slate-800 px-2.5 py-0.5 rounded-full font-mono">7 agentes</span>
            </div>

            <div id="roles-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <!-- Se cargan dinámicamente con renderRoles() -->
            </div>
        </section>

        <!-- Columnas del Tablero Kanban -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">

            <!-- Columna 1: Product / Sprint Backlog -->
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                <div class="p-3.5 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-200">Backlog</h4>
                    </div>
                    <span id="col-count-backlog" class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">0 pts</span>
                </div>
                <div id="col-backlog" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                    <!-- Tarjetas dinámicas -->
                </div>
            </div>

            <!-- Columna 2: En Progreso -->
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                <div class="p-3.5 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-amber-300">En Progreso</h4>
                    </div>
                    <span id="col-count-en_progreso" class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-950/60 text-amber-300 border border-amber-500/20">0 pts</span>
                </div>
                <div id="col-en_progreso" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                    <!-- Tarjetas dinámicas -->
                </div>
            </div>

            <!-- Columna 3: En Revisión / Auditoría DoD -->
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                <div class="p-3.5 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-purple-300">En Revisión (DoD)</h4>
                    </div>
                    <span id="col-count-revision" class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-purple-950/60 text-purple-300 border border-purple-500/20">0 pts</span>
                </div>
                <div id="col-revision" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                    <!-- Tarjetas dinámicas -->
                </div>
            </div>

            <!-- Columna 4: Completadas -->
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl flex flex-col max-h-[750px]">
                <div class="p-3.5 border-b border-slate-800 flex items-center justify-between bg-slate-900/90 rounded-t-2xl">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-emerald-300">Completadas</h4>
                    </div>
                    <span id="col-count-completada" class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-300 border border-emerald-500/20">0 pts</span>
                </div>
                <div id="col-completada" class="p-3 space-y-3 overflow-y-auto flex-1 min-h-[350px]">
                    <!-- Tarjetas dinámicas -->
                </div>
            </div>

        </div>

    </main>

    <!-- Drawer Lateral: Chat de Consulta Ágil con los Agentes -->
    <div id="chat-drawer" class="fixed inset-y-0 right-0 w-full max-w-md bg-slate-900 border-l border-slate-800 shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <!-- Header del Chat -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/90">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">💬</span>
                <div>
                    <h3 class="font-extrabold text-sm text-white">Sala de Consulta Ágil</h3>
                    <p class="text-[11px] text-slate-400">Pregunta a tu PM, Director Creativo o Arquitecto</p>
                </div>
            </div>
            <button onclick="toggleChatDrawer()" class="text-slate-400 hover:text-white text-xl p-1">✕</button>
        </div>

        <!-- Selector de Agente Destino para la Consulta -->
        <div class="px-4 py-2.5 bg-slate-950 border-b border-slate-800 flex items-center gap-2 text-xs">
            <label class="text-slate-400 whitespace-nowrap">Consultar a:</label>
            <select id="chat-target-role" class="w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 outline-none focus:border-indigo-500">
                <!-- Opciones dinámicas -->
            </select>
        </div>

        <!-- Feed de Mensajes -->
        <div id="chat-messages" class="flex-1 p-4 space-y-3 overflow-y-auto">
            <!-- Mensajes dinámicos -->
        </div>

        <!-- Prompts de consulta rápida sugeridos -->
        <div class="px-4 py-1.5 border-t border-slate-800/80 bg-slate-950 flex gap-1.5 overflow-x-auto text-[10px]">
            <button onclick="quickChatPrompt('¿Qué normativas legales y términos de servicio debemos cumplir en nuestro país?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-amber-950/60 hover:bg-amber-900/60 text-amber-300 border border-amber-500/30">
                ⚖️ Dictamen Legal
            </button>
            <button onclick="quickChatPrompt('¿Cómo podemos refactorizar este sprint para reducir riesgos?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300">
                🛡️ Reducir riesgos
            </button>
            <button onclick="quickChatPrompt('¿Qué elemento innovador podemos sumar a la experiencia?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300">
                💡 Innovación creativa
            </button>
            <button onclick="quickChatPrompt('¿Cumplimos la Definition of Done en los tests?')" class="whitespace-nowrap px-2 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300">
                ✅ Verificar DoD
            </button>
        </div>

        <!-- Formulario de Envío de Mensaje -->
        <form id="chat-form" onsubmit="handleSendChat(event)" class="p-3.5 border-t border-slate-800 bg-slate-900/90 flex gap-2">
            <input type="text" id="chat-input" placeholder="Haz una consulta estratégica a este agente..."
                   class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-xs text-white placeholder-slate-500 outline-none focus:border-indigo-500">
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow flex items-center gap-1">
                <span>Enviar</span>
            </button>
        </form>
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

            <!-- Lista Editable de Normativas Legales a Buscar -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-slate-400 uppercase font-bold text-[10px]">Normativas Legales Activas a Auditar</label>
                    <span id="active-regs-badge" class="text-[10px] font-mono text-amber-300 bg-amber-950/70 px-2 py-0.5 rounded-full border border-amber-500/20">0 activas</span>
                </div>

                <div id="regulations-list" class="max-h-40 overflow-y-auto space-y-1.5 p-1">
                    <!-- Chips de normativas dinámicas con botón eliminar -->
                </div>

                <!-- Input para agregar nueva normativa manual -->
                <div class="flex gap-2 pt-1">
                    <input type="text" id="new-regulation-input" placeholder="Agregar ley o normativa (ej: Ley de Cookies o HIPAA)..."
                           onkeydown="if(event.key === 'Enter'){ event.preventDefault(); addCustomRegulation(); }"
                           class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-xs outline-none focus:border-amber-500">
                    <button type="button" onclick="addCustomRegulation()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-amber-300 font-bold text-xs rounded-xl border border-amber-500/30 transition">
                        + Agregar
                    </button>
                </div>

                <!-- Sugerencias rápidas de leyes comunes -->
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

            <!-- Banner Explicativo: "¿Cómo saber con qué IA estás desarrollando?" -->
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

            <!-- Configuración del Proveedor y Modelo -->
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
                            <!-- Opciones dinámicas según proveedor -->
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

                <!-- Toggle: Enrutamiento Inteligente por Rol -->
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

    <!-- Modal: Configurar Multi-Skills por Agente -->
    <div id="skills-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div>
                    <h3 class="font-bold text-base text-white">Configurar Toolkit de Skills</h3>
                    <p id="skills-modal-agent" class="text-xs text-indigo-400 font-mono">Agente</p>
                </div>
                <button onclick="closeSkillsConfig()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <p class="text-xs text-slate-300">
                Selecciona las skills globales de Claude Code que domina este agente. Al delegarle una tarea, <strong>AgileForge inyectará automáticamente la orden de invocar este paquete de skills</strong>.
            </p>

            <div id="skills-checkboxes" class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto p-1">
                <!-- Checkboxes dinámicos -->
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeSkillsConfig()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs">Cancelar</button>
                <button type="button" onclick="saveAgentSkills()" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30">Guardar Toolkit</button>
            </div>
        </div>
    </div>

    <!-- Modal: Asignar Nueva Historia de Usuario (Scrum) -->
    <div id="new-task-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div>
                    <h3 class="font-bold text-base text-white flex items-center gap-2">
                        <span>🎯</span> Crear Historia de Usuario (Scrum)
                    </h3>
                    <p class="text-xs text-slate-400">Inyecta rol, workspace, multi-skills y Definition of Done.</p>
                </div>
                <button onclick="closeNewTaskModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <form id="task-form" onsubmit="handleCreateTask(event)" class="space-y-3.5 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Título de la Misión / Historia *</label>
                    <input type="text" id="task-title" required placeholder="Ej: Implementar cotizador de movilidad por km y mapa"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs">
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Agente Asignado *</label>
                        <select id="task-role" class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none">
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

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Historia de Usuario (Formato Ágil)</label>
                    <input type="text" id="task-user-story" placeholder="Como cliente quiero ver el costo por km para presupuestar la estadía de mi perro"
                           class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs">
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Definition of Done (DoD - Criterios de Aceptación)</label>
                    <input type="text" id="task-dod" value="Lint 0 errores, tests automatizados en verde, sin romper arquitectura."
                           class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs">
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold text-[10px] mb-1">Detalles Técnicos y Archivos</label>
                    <textarea id="task-desc" rows="3" placeholder="Especificaciones de implementación, rutas de archivos a editar o precauciones..."
                              class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-xs"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-1">
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
                    <span id="prompt-modal-badge" class="text-xs px-2.5 py-1 rounded-full font-bold bg-indigo-500/20 text-indigo-400">Prompt de Misión</span>
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
                <p>Pega este prompt directamente en la terminal de <strong>Claude Code</strong> o <strong>VS Code</strong>.</p>
                <button onclick="closePromptModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold">Entendido</button>
            </div>
        </div>
    </div>

    <!-- Script de Control Frontend -->
    <script src="assets/js/app.js"></script>
</body>
</html>
