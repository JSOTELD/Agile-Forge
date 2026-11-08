/**
 * Controlador de Frontend para AgileForge · Open-to-code
 * Soporte de Multi-Skills por Agente y Gestión de Misiones
 */

let appData = { roles: [], tasks: [], available_skills_pool: [] };
let currentFilter = 'todas';

async function init() {
    await fetchTeamData();
}

async function fetchTeamData() {
    try {
        const res = await fetch('api/tasks.php');
        appData = await res.json();
        renderRoles();
        renderTasks();
        populateRoleSelect();
    } catch (err) {
        console.error('Error al cargar datos:', err);
    }
}

function renderRoles() {
    const container = document.getElementById('roles-container');
    const countEl = document.getElementById('role-count');
    if (!container || !appData.roles) return;

    countEl.textContent = `${appData.roles.length} agentes`;
    container.innerHTML = appData.roles.map(r => {
        const skillsList = r.assigned_skills || [];
        return `
        <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 p-4 rounded-2xl transition space-y-3 group shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">${r.avatar}</span>
                    <div>
                        <h4 class="font-bold text-sm text-white group-hover:text-indigo-400 transition">${r.name}</h4>
                        <span class="text-[11px] text-slate-400 font-mono">${r.alias}</span>
                    </div>
                </div>
                <button onclick="openSkillsConfig('${r.id}')" title="Configurar Skills del Agente" class="text-xs px-2 py-1 rounded-lg bg-slate-800 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/20 transition">
                    ⚙️ Skills (${skillsList.length})
                </button>
            </div>
            
            <p class="text-xs text-slate-300 leading-relaxed">${r.description}</p>
            
            <!-- Tags de Multi-Skills asignadas -->
            <div class="space-y-1.5 pt-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Skills en su Toolkit:</span>
                <div class="flex flex-wrap gap-1">
                    ${skillsList.map(s => `
                        <span class="text-[10px] px-2 py-0.5 rounded-md bg-indigo-950/60 text-indigo-300 border border-indigo-500/20 font-mono">${s}</span>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    }).join('');
}

function renderTasks() {
    const container = document.getElementById('tasks-container');
    if (!container || !appData.tasks) return;

    let filtered = appData.tasks;
    if (currentFilter !== 'todas') {
        filtered = appData.tasks.filter(t => t.status === currentFilter);
    }

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="p-12 text-center rounded-3xl border border-dashed border-slate-800 text-slate-500 text-sm">
                No hay misiones con este estado.
            </div>
        `;
        return;
    }

    const priorityBadge = {
        baja: 'bg-slate-800 text-slate-400',
        media: 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
        alta: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
        critica: 'bg-red-500/10 text-red-400 border border-red-500/20'
    };

    const statusBadge = {
        pendiente: 'bg-slate-800 text-slate-300',
        en_progreso: 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
        revision: 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
        completada: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
    };

    container.innerHTML = filtered.map(t => {
        const assignedRole = appData.roles.find(r => r.id === t.assigned_to) || { name: t.assigned_to, avatar: '🤖', assigned_skills: [] };
        return `
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 space-y-4 hover:border-slate-700 transition shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold text-slate-400">${t.id}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold uppercase ${priorityBadge[t.priority] || ''}">${t.priority}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold capitalize ${statusBadge[t.status] || ''}">${t.status.replace('_', ' ')}</span>
                    </div>
                    <span class="text-[11px] text-slate-500">${t.created_at}</span>
                </div>

                <div>
                    <h3 class="font-bold text-base text-white mb-1">${t.title}</h3>
                    <p class="text-xs text-slate-300 leading-relaxed">${t.description || 'Sin descripción adicional.'}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span>Asignado a:</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-800 text-slate-200 font-semibold border border-slate-700">
                            <span>${assignedRole.avatar}</span> ${assignedRole.name}
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">(${assignedRole.assigned_skills ? assignedRole.assigned_skills.length : 0} skills activas)</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Botón para ver y copiar el prompt autogenerado -->
                        <button onclick="showTaskPrompt('${t.id}')"
                                class="px-3.5 py-2 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 hover:text-white font-bold text-xs border border-indigo-500/30 transition flex items-center gap-1.5">
                            <span>📜</span> Ver Prompt
                        </button>

                        <!-- Botón para mandar a revisar / auditar -->
                        <button onclick="requestTaskReview('${t.id}')"
                                class="px-3.5 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 hover:text-white font-bold text-xs border border-purple-500/30 transition flex items-center gap-1.5">
                            <span>🛡️</span> Mandar a Auditar
                        </button>

                        <!-- Cambiador de Estado rápido -->
                        <select onchange="updateTaskStatus('${t.id}', this.value)"
                                class="bg-slate-950 border border-slate-700 text-slate-300 text-xs px-2.5 py-2 rounded-xl outline-none">
                            <option value="pendiente" ${t.status === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                            <option value="en_progreso" ${t.status === 'en_progreso' ? 'selected' : ''}>En progreso</option>
                            <option value="revision" ${t.status === 'revision' ? 'selected' : ''}>En revisión</option>
                            <option value="completada" ${t.status === 'completada' ? 'selected' : ''}>Completada</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function filterTasks(status) {
    currentFilter = status;
    document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.dataset.f === status) {
            btn.classList.add('bg-slate-800', 'text-white');
            btn.classList.remove('bg-slate-900');
        } else {
            btn.classList.remove('bg-slate-800', 'text-white');
            btn.classList.add('bg-slate-900');
        }
    });
    renderTasks();
}

function populateRoleSelect() {
    const sel = document.getElementById('task-role');
    if (!sel || !appData.roles) return;
    sel.innerHTML = appData.roles.map(r => `
        <option value="${r.id}">${r.avatar} ${r.name} (${(r.assigned_skills || []).length} skills)</option>
    `).join('');
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
    const title = document.getElementById('task-title').value;
    const assigned_to = document.getElementById('task-role').value;
    const priority = document.getElementById('task-priority').value;
    const description = document.getElementById('task-desc').value;

    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create_task', title, assigned_to, priority, description })
        });
        const result = await res.json();
        if (result.success) {
            closeNewTaskModal();
            await fetchTeamData();
            showTaskPrompt(result.task.id);
        } else {
            alert('Error: ' + (result.error || 'No se pudo crear la tarea'));
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
    const task = appData.tasks.find(t => t.id === taskId);
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
            document.getElementById('prompt-modal-title').textContent = `Prompt de Revisión para ${taskId}`;
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

// Configuración Interactiva de Multi-Skills por Agente
let activeConfigRoleId = null;
function openSkillsConfig(roleId) {
    activeConfigRoleId = roleId;
    const role = appData.roles.find(r => r.id === roleId);
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
