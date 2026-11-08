<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgileForge · Command Center de Agentes IA</title>
    <!-- Tailwind CSS CDN para renderizado instantáneo sin compilación pesada -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Topbar -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-40 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-3xl p-2 bg-indigo-600/20 border border-indigo-500/30 rounded-2xl">⚡</span>
            <div>
                <h1 class="font-extrabold text-lg text-white tracking-tight flex items-center gap-2">
                    AgileForge <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">Multi-Skills Engine</span>
                </h1>
                <p class="text-xs text-slate-400">Command Center de Agentes IA · Empresa de Software Virtual</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Director IA Online (Antigravity)
            </div>
            <button onclick="openNewTaskModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20 flex items-center gap-2">
                <span>+</span> Asignar Nueva Misión
            </button>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-6 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Columna Izquierda: Escuadrón de Agentes Especialistas (5 cols) -->
        <section class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-bold text-sm text-slate-300 uppercase tracking-wider flex items-center gap-2">
                    <span>👥</span> Agentes Especialistas & Multi-Skills
                </h2>
                <span id="role-count" class="text-xs text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full font-mono">0 agentes</span>
            </div>

            <div id="roles-container" class="space-y-3">
                <!-- Carga dinámica vía JS -->
            </div>
        </section>

        <!-- Columna Derecha: Tablero Kanban de Misiones y Generador de Prompts (7 cols) -->
        <section class="lg:col-span-7 space-y-6">

            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h2 class="font-extrabold text-xl text-white">Tablero de Operaciones</h2>
                    <p class="text-xs text-slate-400">Gestiona tareas, copia prompts con skills inyectadas y audita en 1 clic.</p>
                </div>
                <div class="flex gap-2 text-xs">
                    <button onclick="filterTasks('todas')" class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-medium filter-btn" data-f="todas">Todas</button>
                    <button onclick="filterTasks('en_progreso')" class="px-3 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-amber-400 font-medium filter-btn" data-f="en_progreso">En Progreso</button>
                    <button onclick="filterTasks('completada')" class="px-3 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-emerald-400 font-medium filter-btn" data-f="completada">Completadas</button>
                </div>
            </div>

            <!-- Lista de Tareas -->
            <div id="tasks-container" class="space-y-4">
                <!-- Carga dinámica vía JS -->
            </div>

        </section>

    </main>

    <!-- Modal: Configurar Multi-Skills por Agente -->
    <div id="skills-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <div>
                    <h3 class="font-bold text-lg text-white">Configurar Toolkit de Skills</h3>
                    <p id="skills-modal-agent" class="text-xs text-indigo-400 font-mono">Agente</p>
                </div>
                <button onclick="closeSkillsConfig()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <p class="text-xs text-slate-300">
                Selecciona las skills de tu carpeta global de Claude Code que este agente domina. Cuando delegues una tarea a este agente, <strong>AgileForge inyectará automáticamente la orden de invocar este paquete de skills</strong>.
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

    <!-- Modal: Asignar Nueva Tarea -->
    <div id="new-task-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <h3 class="font-bold text-lg text-white">Delegar Tarea a un Agente</h3>
                <button onclick="closeNewTaskModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
            </div>

            <form id="task-form" onsubmit="handleCreateTask(event)" class="space-y-4 text-xs">
                <div>
                    <label class="block text-slate-400 uppercase font-bold mb-1">Título de la Misión *</label>
                    <input type="text" id="task-title" required placeholder="Ej: Implementar cálculo de movilidad por km"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none text-sm">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 uppercase font-bold mb-1">Asignar al Agente *</label>
                        <select id="task-role" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none">
                            <!-- Opciones cargadas dinámicamente -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 uppercase font-bold mb-1">Prioridad</label>
                        <select id="task-priority" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none">
                            <option value="media">Media</option>
                            <option value="alta">Alta</option>
                            <option value="critica">Crítica (Bloqueante)</option>
                            <option value="baja">Baja</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 uppercase font-bold mb-1">Detalles y Especificaciones Técnicas</label>
                    <textarea id="task-desc" rows="4" placeholder="Describe los requisitos, archivos a tocar o reglas que debe respetar el agente..."
                              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 focus:border-indigo-500 outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeNewTaskModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-600/30">Generar Prompt con Multi-Skills</button>
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
                    <h3 id="prompt-modal-title" class="font-bold text-white text-base">Título</h3>
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

    <!-- Script de Control -->
    <script src="assets/js/app.js"></script>
</body>
</html>
