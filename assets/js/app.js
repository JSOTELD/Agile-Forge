/**
 * Controlador de Frontend para AgileForge · Open-to-code
 * Metodología Ágil (Scrum + PMBOK), Tablero Kanban 4 Columnas, Chat de Agentes y Multi-Skills
 */

let appData = {
    org_name: '',
    active_workspace: 'D:\\xampp\\htdocs\\canil perruno',
    sprint: { number: 1, name: '', goal: '', days_remaining: 0, velocity_points: 0 },
    roles: [],
    tasks: [],
    chat_messages: [],
    available_skills_pool: []
};

let activeConfigRoleId = null;

async function init() {
    await fetchTeamData();
}

async function fetchTeamData() {
    try {
        const res = await fetch('api/tasks.php');
        appData = await res.json();
        renderWorkspace();
        renderSprintBanner();
        renderRoles();
        renderKanban();
        renderChat();
        populateRoleSelects();
    } catch (err) {
        console.error('Error al cargar datos de AgileForge:', err);
    }
}

// 1. Workspace Picker
function renderWorkspace() {
    const wsDisplay = document.getElementById('active-workspace-display');
    if (wsDisplay) {
        wsDisplay.textContent = appData.active_workspace || 'D:\\xampp\\htdocs\\canil perruno';
    }
    const wsInput = document.getElementById('workspace-input');
    if (wsInput) {
        wsInput.value = appData.active_workspace || '';
    }
}

function openChangeWorkspaceModal() {
    document.getElementById('workspace-modal').classList.remove('hidden');
}

function closeChangeWorkspaceModal() {
    document.getElementById('workspace-modal').classList.add('hidden');
}

async function saveWorkspaceChange() {
    const wsInput = document.getElementById('workspace-input').value.trim();
    if (!wsInput) return;

    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_workspace', workspace: wsInput })
        });
        const result = await res.json();
        if (result.success) {
            appData.active_workspace = wsInput;
            renderWorkspace();
            closeChangeWorkspaceModal();
        }
    } catch (err) {
        alert('Error al actualizar carpeta de trabajo');
    }
}

// 2. Sprint Banner (Scrum Metrics)
function renderSprintBanner() {
    const sp = appData.sprint || {};
    const sprintBadge = document.getElementById('sprint-number-badge');
    const sprintName = document.getElementById('sprint-name');
    const sprintGoal = document.getElementById('sprint-goal');
    const sprintVelocity = document.getElementById('sprint-velocity');
    const sprintDays = document.getElementById('sprint-days-remaining');
    const sprintDone = document.getElementById('sprint-points-done');
    const sprintDod = document.getElementById('sprint-dod-pct');

    if (sprintBadge) sprintBadge.textContent = `Sprint ${sp.number || 1}`;
    if (sprintName) sprintName.textContent = sp.name || `Sprint ${sp.number || 1}`;
    if (sprintGoal) sprintGoal.textContent = sp.goal || 'Sin objetivo fijado';
    if (sprintVelocity) sprintVelocity.textContent = sp.velocity_points || 24;
    if (sprintDays) sprintDays.textContent = sp.days_remaining || 0;

    // Calcular puntos completados vs total
    const completedTasks = (appData.tasks || []).filter(t => t.status === 'completada');
    const completedPts = completedTasks.reduce((acc, t) => acc + (parseInt(t.story_points, 10) || 3), 0);
    const totalPts = (appData.tasks || []).reduce((acc, t) => acc + (parseInt(t.story_points, 10) || 3), 0) || 1;

    if (sprintDone) sprintDone.textContent = completedPts;
    const pct = Math.round((completedPts / totalPts) * 100);
    if (sprintDod) sprintDod.textContent = `${pct}%`;
}

// 3. Toggle Escuadrón de Agentes
function toggleRolesSection() {
    const sec = document.getElementById('roles-section');
    const txt = document.getElementById('toggle-team-text');
    if (!sec) return;
    const isHidden = sec.classList.contains('hidden');
    if (isHidden) {
        sec.classList.remove('hidden');
        if (txt) txt.textContent = 'Ocultar Escuadrón de Agentes';
    } else {
        sec.classList.add('hidden');
        if (txt) txt.textContent = `Ver Escuadrón de Agentes (${(appData.roles || []).length})`;
    }
}

// 4. Renderizar Agentes y sus Multi-Skills
function renderRoles() {
    const container = document.getElementById('roles-container');
    const countEl = document.getElementById('role-count');
    if (!container || !appData.roles) return;

    countEl.textContent = `${appData.roles.length} agentes`;
    container.innerHTML = appData.roles.map(r => {
        const skillsList = r.assigned_skills || [];
        return `
            <div class="bg-slate-950/80 border border-slate-800 hover:border-slate-700 p-4 rounded-2xl transition space-y-2.5 group shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">${r.avatar}</span>
                        <div>
                            <h4 class="font-bold text-xs text-white group-hover:text-indigo-400 transition">${r.name}</h4>
                            <span class="text-[10px] text-slate-400 font-mono">${r.alias}</span>
                        </div>
                    </div>
                    <button onclick="openSkillsConfig('${r.id}')" title="Configurar Skills del Agente"
                            class="text-[11px] px-2 py-1 rounded-lg bg-slate-900 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/20 transition">
                        ⚙️ Skills (${skillsList.length})
                    </button>
                </div>
                
                <p class="text-[11px] text-slate-300 leading-relaxed">${r.description}</p>
                
                <div class="space-y-1 pt-1">
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider block">Toolkit de Skills Asignadas:</span>
                    <div class="flex flex-wrap gap-1">
                        ${skillsList.map(s => `
                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-indigo-950/60 text-indigo-300 border border-indigo-500/20 font-mono">${s}</span>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// 5. Renderizar Tablero Kanban (4 Columnas Scrum)
function renderKanban() {
    const columns = {
        backlog: document.getElementById('col-backlog'),
        en_progreso: document.getElementById('col-en_progreso'),
        revision: document.getElementById('col-revision'),
        completada: document.getElementById('col-completada')
    };

    const counts = {
        backlog: 0,
        en_progreso: 0,
        revision: 0,
        completada: 0
    };

    // Limpiar columnas
    Object.values(columns).forEach(col => { if (col) col.innerHTML = ''; });

    const priorityBadge = {
        baja: 'bg-slate-800 text-slate-400',
        media: 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
        alta: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
        critica: 'bg-red-500/10 text-red-400 border border-red-500/20'
    };

    (appData.tasks || []).forEach(task => {
        const status = task.status || 'backlog';
        const targetCol = columns[status] || columns['backlog'];
        const pts = parseInt(task.story_points, 10) || 3;
        counts[status] = (counts[status] || 0) + pts;

        const role = (appData.roles || []).find(r => r.id === task.assigned_to) || { name: task.assigned_to, avatar: '🤖', assigned_skills: [] };

        const card = document.createElement('div');
        card.className = 'bg-slate-950 border border-slate-800 rounded-xl p-3.5 space-y-2.5 hover:border-slate-700 transition shadow-sm';
        card.innerHTML = `
            <div class="flex items-center justify-between gap-2 border-b border-slate-900 pb-2">
                <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[10px] font-bold text-slate-400">${task.id}</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${priorityBadge[task.priority] || ''}">${task.priority}</span>
                </div>
                <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-950/70 text-indigo-300 border border-indigo-500/30">
                    ${pts} SP
                </span>
            </div>

            <div>
                <h5 class="font-bold text-xs text-white leading-tight mb-1">${task.title}</h5>
                ${task.user_story ? `<p class="text-[11px] text-indigo-200/90 italic leading-snug mb-1">“${task.user_story}”</p>` : ''}
                <p class="text-[11px] text-slate-400 leading-relaxed line-clamp-2">${task.description || ''}</p>
            </div>

            <!-- Asignado y Skills -->
            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-900">
                <span class="inline-flex items-center gap-1 text-slate-300 font-medium truncate max-w-[150px]">
                    <span>${role.avatar}</span> <span>${role.name.split(' ')[0]}</span>
                </span>
                <span class="text-[9px] font-mono text-slate-500">${(role.assigned_skills || []).length} skills</span>
            </div>

            <!-- Botones de Acción Scrum -->
            <div class="flex items-center justify-between gap-1 pt-1">
                <div class="flex items-center gap-1">
                    <button onclick="showTaskPrompt('${task.id}')" title="Ver Prompt con Multi-Skills"
                            class="px-2 py-1 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 text-[10px] font-bold border border-indigo-500/30 transition">
                        📜 Prompt
                    </button>
                    <button onclick="requestTaskReview('${task.id}')" title="Auditar DoD"
                            class="px-2 py-1 rounded-lg bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 text-[10px] font-bold border border-purple-500/30 transition">
                        🛡️ DoD
                    </button>
                </div>

                <!-- Selector de Estado Ágil -->
                <select onchange="updateTaskStatus('${task.id}', this.value)"
                        class="bg-slate-900 border border-slate-700 text-slate-300 text-[10px] px-1.5 py-1 rounded-lg outline-none">
                    <option value="backlog" ${status === 'backlog' ? 'selected' : ''}>Backlog</option>
                    <option value="en_progreso" ${status === 'en_progreso' ? 'selected' : ''}>En Progreso</option>
                    <option value="revision" ${status === 'revision' ? 'selected' : ''}>En Revisión</option>
                    <option value="completada" ${status === 'completada' ? 'selected' : ''}>Completada</option>
                </select>
            </div>
        `;

        if (targetCol) targetCol.appendChild(card);
    });

    // Actualizar etiquetas de puntos por columna
    ['backlog', 'en_progreso', 'revision', 'completada'].forEach(col => {
        const el = document.getElementById(`col-count-${col}`);
        if (el) el.textContent = `${counts[col] || 0} pts`;
    });

    renderSprintBanner();
}

// 6. Chat de Consulta con Agentes
function toggleChatDrawer() {
    const drawer = document.getElementById('chat-drawer');
    if (!drawer) return;
    drawer.classList.toggle('translate-x-full');
}

function quickChatPrompt(text) {
    const input = document.getElementById('chat-input');
    if (input) {
        input.value = text;
        input.focus();
    }
}

function renderChat() {
    const container = document.getElementById('chat-messages');
    if (!container || !appData.chat_messages) return;

    container.innerHTML = appData.chat_messages.map(m => {
        const isUser = m.sender_role === 'user';
        return `
            <div class="flex flex-col ${isUser ? 'items-end' : 'items-start'} space-y-1">
                <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                    <span>${m.avatar}</span>
                    <span class="font-bold ${isUser ? 'text-indigo-400' : 'text-slate-300'}">${m.sender_name}</span>
                    <span class="text-slate-500 font-mono">${m.timestamp}</span>
                </div>
                <div class="max-w-[88%] p-3 rounded-2xl text-xs leading-relaxed ${isUser ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-slate-950 border border-slate-800 text-slate-200 rounded-tl-none'}">
                    ${m.text}
                </div>
            </div>
        `;
    }).join('');

    container.scrollTop = container.scrollHeight;
}

async function handleSendChat(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const targetRole = document.getElementById('chat-target-role').value;
    const text = input.value.trim();
    if (!text) return;

    input.value = '';

    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send_chat',
                target_role: targetRole,
                message: text
            })
        });
        const data = await res.json();
        if (data.success) {
            await fetchTeamData();
        }
    } catch (err) {
        console.error('Error al enviar chat:', err);
    }
}

// 7. Modales de Tareas y Prompts
function populateRoleSelects() {
    const taskRole = document.getElementById('task-role');
    const chatRole = document.getElementById('chat-target-role');
    if (!appData.roles) return;

    const options = appData.roles.map(r => `
        <option value="${r.id}">${r.avatar} ${r.name}</option>
    `).join('');

    if (taskRole) taskRole.innerHTML = options;
    if (chatRole) chatRole.innerHTML = options;
}

function openNewTaskModal() {
    document.getElementById('new-task-modal').classList.remove('hidden');
}

function closeNewTaskModal() {
    document.getElementById('new-task-modal').classList.add('hidden');
    document.getElementById('task-form').reset();
}

async function handleCreateTask(e) {
    e.preventDefault();
    const title = document.getElementById('task-title').value.trim();
    const assigned_to = document.getElementById('task-role').value;
    const story_points = document.getElementById('task-points').value;
    const priority = document.getElementById('task-priority').value;
    const user_story = document.getElementById('task-user-story').value.trim();
    const dod = document.getElementById('task-dod').value.trim();
    const description = document.getElementById('task-desc').value.trim();

    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_task',
                title,
                assigned_to,
                story_points,
                priority,
                user_story,
                dod,
                description
            })
        });
        const result = await res.json();
        if (result.success) {
            closeNewTaskModal();
            await fetchTeamData();
            showTaskPrompt(result.task.id);
        } else {
            alert('Error: ' + (result.error || 'No se pudo crear la historia'));
        }
    } catch (err) {
        alert('Error de conexión');
    }
}

async function updateTaskStatus(taskId, status) {
    try {
        await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', task_id: taskId, status })
        });
        await fetchTeamData();
    } catch (err) {
        console.error(err);
    }
}

function showTaskPrompt(taskId) {
    const task = (appData.tasks || []).find(t => t.id === taskId);
    if (!task) return;

    document.getElementById('prompt-modal-badge').textContent = `Misión: ${task.id}`;
    document.getElementById('prompt-modal-title').textContent = task.title;
    document.getElementById('prompt-modal-text').value = task.prompt_template || 'Sin prompt generado.';
    document.getElementById('prompt-modal').classList.remove('hidden');
    resetCopyBtn();
}

async function requestTaskReview(taskId) {
    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'generate_review_prompt', task_id: taskId })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('prompt-modal-badge').textContent = `Auditoría: ${taskId}`;
            document.getElementById('prompt-modal-title').textContent = `Auditoría DoD para ${taskId}`;
            document.getElementById('prompt-modal-text').value = data.review_prompt;
            document.getElementById('prompt-modal').classList.remove('hidden');
            resetCopyBtn();
        }
    } catch (err) {
        console.error(err);
    }
}

function closePromptModal() {
    document.getElementById('prompt-modal').classList.add('hidden');
}

function copyPromptText() {
    const txt = document.getElementById('prompt-modal-text');
    txt.select();
    navigator.clipboard.writeText(txt.value);
    const btnText = document.getElementById('copy-btn-text');
    btnText.textContent = '¡Copiado al Portapapeles!';
    setTimeout(() => {
        btnText.textContent = 'Copiar Prompt';
    }, 2500);
}

function resetCopyBtn() {
    const btnText = document.getElementById('copy-btn-text');
    if (btnText) btnText.textContent = 'Copiar Prompt';
}

// 8. Configuración Interactiva de Multi-Skills por Agente
function openSkillsConfig(roleId) {
    activeConfigRoleId = roleId;
    const role = (appData.roles || []).find(r => r.id === roleId);
    if (!role) return;

    document.getElementById('skills-modal-agent').textContent = `${role.avatar} ${role.name}`;
    const pool = appData.available_skills_pool || [];
    const assigned = new Set(role.assigned_skills || []);

    const container = document.getElementById('skills-checkboxes');
    container.innerHTML = pool.map(skill => `
        <label class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-950 border border-slate-800 hover:border-slate-700 cursor-pointer text-xs">
            <input type="checkbox" value="${skill}" ${assigned.has(skill) ? 'checked' : ''} class="w-4 h-4 accent-indigo-500 rounded">
            <span class="font-mono text-indigo-300">${skill}</span>
        </label>
    `).join('');

    document.getElementById('skills-modal').classList.remove('hidden');
}

function closeSkillsConfig() {
    document.getElementById('skills-modal').classList.add('hidden');
    activeConfigRoleId = null;
}

async function saveAgentSkills() {
    if (!activeConfigRoleId) return;
    const checked = Array.from(document.querySelectorAll('#skills-checkboxes input:checked')).map(i => i.value);

    try {
        await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_agent_skills', role_id: activeConfigRoleId, skills: checked })
        });
        closeSkillsConfig();
        await fetchTeamData();
    } catch (err) {
        alert('Error al guardar skills');
    }
}

// Iniciar al cargar la ventana
window.addEventListener('DOMContentLoaded', init);
