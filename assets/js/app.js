/**
 * AgileForge v2 - Orquestador Principal (ES Module Entrypoint)
 * Integra módulos de Estado, UI, Kanban, Chat, Reuniones, Skills, Laboratorio UX y Multi-Proyectos.
 */

import {
    appData,
    setAppData,
    currentTab,
    setCurrentTab,
    activeConfigRoleId,
    setActiveConfigRoleId,
    escapeHtml,
    apiFetch
} from './state.js';

import {
    showToast,
    switchMainTab,
    selectTheme,
    applySavedTheme,
    toggleDayNightMode,
    openBeginnerManualModal,
    closeBeginnerManualModal,
    closeAllModals,
    initKeyboardShortcuts
} from './ui.js';

import {
    toggleLanguage,
    setLanguage,
    getLanguage,
    applyLanguageToDOM
} from './i18n.js';

import {
    renderProjectSelector,
    handleSwitchProject,
    openNewProjectModal,
    closeNewProjectModal,
    submitNewProject,
    openImportWorkspaceModal,
    closeImportWorkspaceModal,
    submitImportWorkspace
} from './projects.js';

import {
    renderKanban,
    renderActivityFeed,
    updateTaskStatus,
    moveTaskForward,
    moveTaskBackward,
    openNewTaskModal,
    closeNewTaskModal,
    submitNewTask,
    openTaskDetail,
    closeTaskDetail
} from './kanban.js';

import {
    toggleChatDrawer,
    quickChatPrompt,
    renderChat,
    handleSendChat
} from './chat.js';

import {
    renderMeetingsBadge,
    renderMeetingsView,
    renderActiveMeeting,
    renderMeetingsHistory,
    viewPastMeeting,
    toggleMeetingComposer,
    populateMeetingParticipantsSelector,
    launchPresetMeeting,
    submitCustomMeeting,
    convertMeetingToTasks,
    copyMeetingMinutes
} from './meetings.js';

import {
    openSkillsConfig,
    closeSkillsConfig,
    filterSkillsByCategory,
    filterSkillsCatalog,
    updateSkillsCategoryButtons,
    renderSkillsCards,
    updateSelectedSkillsCount,
    saveAgentSkills,
    openSkillDetail,
    closeSkillDetailModal,
    copySkillSnippet,
    openAddSkillModal,
    closeAddSkillModal,
    handleCreateCustomSkill
} from './skills.js';

import {
    runDomAccessibilityScan,
    runResponsiveViewportScan,
    renderUxHonestView,
    toggleHeuristicItem,
    saveHeuristicEvaluation,
    exportUxReport
} from './ux-lab.js';

import {
    runLegalWorkspaceAudit,
    renderLegalPillars,
    handleGenerateLegalDoc
} from './legal.js';

import {
    renderLocalAiControls,
    detectLocalAi
} from './local-ai.js';

// -------------------------------------------------------------
// 1. CARGA DE DATOS & SINCRONIZACIÓN CON BACKEND SQLITE
// -------------------------------------------------------------
export async function fetchTeamData() {
    let rawData;
    try {
        const res = await apiFetch('api/tasks.php');
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }
        rawData = await res.json();
    } catch (netErr) {
        console.error('Error de red al consultar api/tasks.php:', netErr);
        showToast('Sin conexión', 'No se pudo conectar con el backend local (api/tasks.php)', 'error');
        return;
    }

    try {
        setAppData(rawData);
        renderWorkspace();
        renderProjectSelector();
        renderJurisdictionBadge();
        renderAiEngineBadge();
        renderSprintBanner();
        renderRoles();
        renderKanban();
        renderChat();
        populateRoleSelects();
        renderUxHonestView();
        renderMeetingsBadge();
        renderActivityFeed();
        renderLocalAiControls();
        if (currentTab === 'meetings') renderMeetingsView();
    } catch (renderErr) {
        console.error('Error de renderizado en frontend:', renderErr);
        showToast('Error de Interfaz', 'Ocurrió un error al renderizar los componentes: ' + renderErr.message, 'warning');
    }
}

// -------------------------------------------------------------
// 2. SPRINT BANNER & MÉTRICAS
// -------------------------------------------------------------
export function renderSprintBanner() {
    const sprint = appData.sprint || {};
    const tasks = appData.tasks || [];
    const costs = appData.costs_summary || {};

    const numBadge = document.getElementById('sprint-number-badge');
    const nameEl = document.getElementById('sprint-name');
    const goalEl = document.getElementById('sprint-goal');
    const daysEl = document.getElementById('sprint-days-remaining');
    const velEl = document.getElementById('sprint-velocity');
    const doneEl = document.getElementById('sprint-points-done');
    const dodPctEl = document.getElementById('sprint-dod-pct');
    const bar = document.getElementById('sprint-progress-bar');
    const barText = document.getElementById('progress-bar-text');

    const costUsdEl = document.getElementById('sprint-total-cost');
    const tokensEl = document.getElementById('sprint-total-tokens');

    if (numBadge) numBadge.textContent = `Sprint ${sprint.number || 1}`;
    if (nameEl) nameEl.textContent = sprint.name || 'Sprint 1';
    if (goalEl) goalEl.textContent = sprint.goal || '';
    if (daysEl) daysEl.textContent = sprint.days_remaining || 0;

    const totalPoints = tasks.reduce((sum, t) => sum + (parseInt(t.story_points) || 1), 0);
    const completedTasks = tasks.filter(t => t.status === 'completada');
    const donePoints = completedTasks.reduce((sum, t) => sum + (parseInt(t.story_points) || 1), 0);
    const velocity = sprint.velocity_points || Math.max(totalPoints, 24);

    if (velEl) velEl.textContent = velocity;
    if (doneEl) doneEl.textContent = donePoints;

    const pct = totalPoints > 0 ? Math.round((donePoints / totalPoints) * 100) : 0;
    if (dodPctEl) dodPctEl.textContent = `${pct}%`;
    if (bar) bar.style.width = `${pct}%`;
    if (barText) barText.textContent = `${pct}% completado (${donePoints}/${totalPoints} pts)`;

    if (costUsdEl) {
        const totalCost = parseFloat(costs.total_cost_usd || 0);
        costUsdEl.textContent = `$${totalCost.toFixed(4)}`;
    }
    if (tokensEl) {
        const totalTok = parseInt(costs.total_tokens || 0);
        tokensEl.textContent = totalTok > 1000 ? `${(totalTok / 1000).toFixed(1)}k` : totalTok;
    }
}

export function openCostsModal() {
    const modal = document.getElementById('costs-modal');
    if (!modal) return;

    const costs = appData.costs_summary || {};
    const callsEl = document.getElementById('modal-costs-calls');
    const inTokEl = document.getElementById('modal-costs-input-tok');
    const outTokEl = document.getElementById('modal-costs-output-tok');
    const usdEl = document.getElementById('modal-costs-total-usd');
    const agentContainer = document.getElementById('modal-costs-by-agent');

    if (callsEl) callsEl.textContent = costs.total_calls || 0;
    if (inTokEl) inTokEl.textContent = (costs.total_input_tokens || 0).toLocaleString();
    if (outTokEl) outTokEl.textContent = (costs.total_output_tokens || 0).toLocaleString();
    if (usdEl) usdEl.textContent = `$${parseFloat(costs.total_cost_usd || 0).toFixed(4)}`;

    if (agentContainer) {
        const byAgent = costs.by_agent || [];
        if (byAgent.length === 0) {
            agentContainer.innerHTML = `
                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 text-slate-500 text-center">
                    Aún no se registran llamadas API facturables en este sprint. En Modo Plugin, la ejecución corre en la suscripción del host (Claude Code).
                </div>
            `;
        } else {
            agentContainer.innerHTML = byAgent.map(a => {
                const roleObj = (appData.roles || []).find(r => r.id === a.agent_role) || { name: a.agent_role, avatar: '🤖' };
                const costVal = parseFloat(a.cost_usd || 0).toFixed(4);
                const tokensVal = parseInt(a.tokens || 0).toLocaleString();
                return `
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800/80">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">${escapeHtml(roleObj.avatar || '🤖')}</span>
                            <div>
                                <span class="font-bold text-white text-xs block">${escapeHtml(roleObj.name || a.agent_role)}</span>
                                <span class="text-[10px] text-slate-400 font-mono">${a.calls} interacciones</span>
                            </div>
                        </div>
                        <div class="text-right font-mono">
                            <span class="text-xs text-emerald-400 font-bold block">$${costVal}</span>
                            <span class="text-[10px] text-slate-500">${tokensVal} tokens</span>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    modal.classList.remove('hidden');
}

export function closeCostsModal() {
    const modal = document.getElementById('costs-modal');
    if (modal) modal.classList.add('hidden');
}

// -------------------------------------------------------------
// 3. ESCUADRÓN DE AGENTES (ROLES)
// -------------------------------------------------------------
export function renderRoles() {
    const container = document.getElementById('roles-container');
    const navCount = document.getElementById('nav-team-count');
    const countEl = document.getElementById('role-count');
    if (!container || !appData.roles) return;

    if (countEl) countEl.textContent = `${appData.roles.length} agentes`;
    if (navCount) navCount.textContent = `${appData.roles.length}`;

    const providers = appData.ai_engine?.available_providers || [];
    const execMode = appData.ai_engine?.execution_mode || 'cli';
    const roleRouting = !!appData.ai_engine?.role_routing_enabled;

    container.innerHTML = appData.roles.map(r => {
        const assignedSkills = r.assigned_skills || [];
        const isLegal = r.id === 'legal_counsel';
        const isUx = r.id === 'frontend_master';
        const isDesigner = r.id === 'creative_director';

        let badgeGlow = 'border-slate-800';
        if (isLegal) badgeGlow = 'border-amber-500/30 bg-amber-950/10';
        if (isUx) badgeGlow = 'border-pink-500/30 bg-pink-950/10';
        if (isDesigner) badgeGlow = 'border-purple-500/30 bg-purple-950/10';

        const roleProvider = (roleRouting && r.ai_provider) ? r.ai_provider : (appData.ai_engine?.active_provider || 'anthropic');
        const roleModel = (roleRouting && r.ai_model) ? r.ai_model : (appData.ai_engine?.active_model || 'claude-sonnet-5-5');
        const provObj = providers.find(p => p.id === roleProvider);

        let engineBadge = '';
        if (execMode === 'cli') {
            engineBadge = `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 border border-indigo-500/30 text-indigo-300 flex items-center gap-1" title="Ejecutado por IA anfitriona (Claude Code u otra vía MCP)"><span>🔌</span> Modo Plugin (${escapeHtml(roleModel)})</span>`;
        } else if (provObj?.api_key_configured) {
            engineBadge = `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 border border-emerald-500/40 text-emerald-300 flex items-center gap-1" title="API Key configurada en .env"><span>🟢</span> Motor Activo: ${escapeHtml(roleModel)}</span>`;
        } else if (roleProvider === 'ollama') {
            engineBadge = `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 border border-amber-500/40 text-amber-300 flex items-center gap-1" title="Servicio local Ollama"><span>🦙</span> Ollama Local</span>`;
        } else {
            engineBadge = `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 border border-amber-500/40 text-amber-300 flex items-center gap-1" title="Sin clave en .env (opera en Modo Plugin)"><span>⚠️</span> Sin Motor (.env)</span>`;
        }

        return `
            <div class="glass-card rounded-3xl p-5 border ${badgeGlow} flex flex-col justify-between space-y-4 hover:border-indigo-500/40 transition">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="text-3xl p-2 rounded-2xl bg-slate-900 border border-slate-800">${escapeHtml(r.avatar)}</span>
                            <div>
                                <h4 class="font-extrabold text-white text-sm">${escapeHtml(r.name)}</h4>
                                <span class="text-[10px] text-slate-400 font-mono">${escapeHtml(r.alias)}</span>
                            </div>
                        </div>
                        ${engineBadge}
                    </div>

                    <p class="text-xs text-slate-300">${escapeHtml(r.specialty)}</p>

                    <!-- Chips de Skills Asignadas -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[10px] text-slate-400 uppercase font-bold tracking-wider">
                            <span>Skills Activas (${assignedSkills.length})</span>
                            <button onclick="openSkillsConfig('${escapeHtml(r.id)}')" class="text-indigo-400 hover:text-indigo-300 lowercase text-[10px] underline">editar</button>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            ${assignedSkills.map(s => `
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-slate-900 border border-slate-800 text-slate-300 flex items-center gap-1 cursor-pointer hover:border-indigo-500/40" onclick="openSkillDetail('${escapeHtml(s)}')">
                                    <span>⚡</span> <span>${escapeHtml(s)}</span>
                                </span>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
                    <button onclick="quickConsultAgent('${escapeHtml(r.id)}')" class="text-xs text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1">
                        <span>💬</span> Consultar en Chat
                    </button>
                    <button onclick="openSkillsConfig('${escapeHtml(r.id)}')" class="text-xs px-2.5 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 font-medium transition">
                        ⚙️ Configurar Skills
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

export function quickConsultAgent(roleId) {
    const sel = document.getElementById('chat-target-role');
    if (sel) sel.value = roleId;
    toggleChatDrawer();
}

// -------------------------------------------------------------
// 4. WORKSPACE & CONFIGURACIONES GLOBALES
// -------------------------------------------------------------
export function renderWorkspace() {
    const wsDisplay = document.getElementById('active-workspace-display');
    const ws = appData.current_project?.path || appData.active_workspace;
    if (wsDisplay) {
        wsDisplay.textContent = ws || 'D:\\xampp\\htdocs\\canil perruno';
    }
}

export function openChangeWorkspaceModal() {
    const modal = document.getElementById('change-workspace-modal');
    const input = document.getElementById('workspace-input');
    if (input) input.value = appData.current_project?.path || appData.active_workspace || '';
    if (modal) modal.classList.remove('hidden');
}

export function closeChangeWorkspaceModal() {
    const modal = document.getElementById('change-workspace-modal');
    if (modal) modal.classList.add('hidden');
}

export async function saveWorkspace() {
    const input = document.getElementById('workspace-input');
    const ws = input?.value?.trim();
    if (!ws) return;

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_workspace', workspace: ws })
        });
        const data = await res.json();
        if (data.success) {
            closeChangeWorkspaceModal();
            showToast('Workspace Actualizado', ws, 'success');
            await fetchTeamData();
        } else {
            showToast('Error', data.error || 'No se pudo actualizar workspace', 'error');
        }
    } catch (err) {
        showToast('Error', 'Fallo al actualizar workspace', 'error');
    }
}

export function renderJurisdictionBadge() {
    const juris = appData.jurisdiction || {};
    const badge = document.getElementById('jurisdiction-badge');
    if (badge) {
        badge.innerHTML = `<span>${juris.flag || '🇵🇪'}</span> <span>${juris.country_name || 'Perú'}</span>`;
    }
}

export function openJurisdictionModal() {
    const modal = document.getElementById('jurisdiction-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeJurisdictionModal() {
    const modal = document.getElementById('jurisdiction-modal');
    if (modal) modal.classList.add('hidden');
}

export async function saveJurisdiction() {
    const country = document.getElementById('jurisdiction-country-select')?.value || 'PE';
    const names = { PE: 'Perú', CO: 'Colombia', MX: 'México', CL: 'Chile', AR: 'Argentina', ES: 'España', US: 'Estados Unidos' };
    const flags = { PE: '🇵🇪', CO: '🇨🇴', MX: '🇲🇽', CL: '🇨🇱', AR: '🇦🇷', ES: '🇪🇸', US: '🇺🇸' };

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_jurisdiction',
                country_code: country,
                country_name: names[country] || 'Perú',
                flag: flags[country] || '🇵🇪'
            })
        });
        const data = await res.json();
        if (data.success) {
            closeJurisdictionModal();
            showToast('Jurisdicción Actualizada', `${flags[country]} ${names[country]}`, 'success');
            await fetchTeamData();
        }
    } catch (err) {
        showToast('Error', 'No se pudo actualizar jurisdicción', 'error');
    }
}

export function renderAiEngineBadge() {
    // El badge "Motor IA" de la barra superior (header-ai-icon / header-ai-model) lo pinta local-ai.js,
    // porque muestra el modelo local cuando la IA local está activada.
    renderLocalAiControls();
}

export function openAiEngineModal() {
    const modal = document.getElementById('ai-engine-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeAiEngineModal() {
    const modal = document.getElementById('ai-engine-modal');
    if (modal) modal.classList.add('hidden');
}

export async function saveAiEngineSettings() {
    const provider = document.getElementById('ai-provider-select')?.value || 'anthropic';
    const model = document.getElementById('ai-model-select')?.value || 'claude-sonnet-5-5';
    const mode = document.getElementById('ai-execution-mode')?.value || 'cli';
    const roleRouting = document.getElementById('ai-role-routing-toggle')?.checked !== false;

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_ai_engine',
                active_provider: provider,
                active_model: model,
                execution_mode: mode,
                role_routing_enabled: roleRouting
            })
        });
        const data = await res.json();
        if (data.success) {
            closeAiEngineModal();
            showToast('Motor IA Configurado', `${provider} (${model})`, 'magic');
            await fetchTeamData();
        }
    } catch (err) {
        showToast('Error', 'No se pudo actualizar motor IA', 'error');
    }
}

export function populateRoleSelects() {
    const taskRole = document.getElementById('task-role');
    const chatRole = document.getElementById('chat-target-role');
    const roles = appData.roles || [];

    if (taskRole && taskRole.options.length <= 1) {
        roles.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = `${r.avatar} ${r.name}`;
            taskRole.appendChild(opt);
        });
    }

    if (chatRole && chatRole.options.length <= 1) {
        roles.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = `${r.avatar} ${r.name} (${r.alias})`;
            chatRole.appendChild(opt);
        });
    }
}

// -------------------------------------------------------------
// 5. REGISTRO EN WINDOW PARA COMPATIBILIDAD CON TEMPLATES HTML
// -------------------------------------------------------------
window.appData = appData;
window.fetchTeamData = fetchTeamData;
window.showToast = showToast;
window.switchMainTab = switchMainTab;
window.selectTheme = selectTheme;
window.closeAllModals = closeAllModals;

// Proyectos
window.handleSwitchProject = handleSwitchProject;
window.openNewProjectModal = openNewProjectModal;
window.closeNewProjectModal = closeNewProjectModal;
window.submitNewProject = submitNewProject;
window.openImportWorkspaceModal = openImportWorkspaceModal;
window.closeImportWorkspaceModal = closeImportWorkspaceModal;
window.submitImportWorkspace = submitImportWorkspace;

// Kanban
window.renderKanban = renderKanban;
window.moveTaskForward = moveTaskForward;
window.moveTaskBackward = moveTaskBackward;
window.updateTaskStatus = updateTaskStatus;
window.openNewTaskModal = openNewTaskModal;
window.closeNewTaskModal = closeNewTaskModal;
window.submitNewTask = submitNewTask;
window.openTaskDetail = openTaskDetail;
window.closeTaskDetail = closeTaskDetail;

// Costos & Sprint
window.renderSprintBanner = renderSprintBanner;
window.openCostsModal = openCostsModal;
window.closeCostsModal = closeCostsModal;

// Roles & Chat
window.renderRoles = renderRoles;
window.quickConsultAgent = quickConsultAgent;
window.toggleChatDrawer = toggleChatDrawer;
window.quickChatPrompt = quickChatPrompt;
window.handleSendChat = handleSendChat;

// Meetings
window.renderMeetingsView = renderMeetingsView;
window.renderActiveMeeting = renderActiveMeeting;
window.viewPastMeeting = viewPastMeeting;
window.launchPresetMeeting = launchPresetMeeting;
window.toggleMeetingComposer = toggleMeetingComposer;
window.submitCustomMeeting = submitCustomMeeting;
window.convertMeetingToTasks = convertMeetingToTasks;
window.copyMeetingMinutes = copyMeetingMinutes;

// Skills
window.openSkillsConfig = openSkillsConfig;
window.closeSkillsConfig = closeSkillsConfig;
window.filterSkillsByCategory = filterSkillsByCategory;
window.filterSkillsCatalog = filterSkillsCatalog;
window.renderSkillsCards = renderSkillsCards;
window.updateSelectedSkillsCount = updateSelectedSkillsCount;
window.saveAgentSkills = saveAgentSkills;
window.openSkillDetail = openSkillDetail;
window.closeSkillDetailModal = closeSkillDetailModal;
window.copySkillSnippet = copySkillSnippet;
window.openAddSkillModal = openAddSkillModal;
window.closeAddSkillModal = closeAddSkillModal;
window.handleCreateCustomSkill = handleCreateCustomSkill;

// UX Lab
window.renderUxHonestView = renderUxHonestView;
window.runDomAccessibilityScan = runDomAccessibilityScan;
window.runResponsiveViewportScan = runResponsiveViewportScan;
window.toggleHeuristicItem = toggleHeuristicItem;
window.saveHeuristicEvaluation = saveHeuristicEvaluation;
window.exportUxReport = exportUxReport;

// Legal & Compliance
window.runLegalWorkspaceAudit = runLegalWorkspaceAudit;
window.renderLegalPillars = renderLegalPillars;
window.handleGenerateLegalDoc = handleGenerateLegalDoc;

// Modales globales & Preferencias (Temas / Idioma / Manual)
window.openChangeWorkspaceModal = openChangeWorkspaceModal;
window.closeChangeWorkspaceModal = closeChangeWorkspaceModal;
window.saveWorkspace = saveWorkspace;
window.openJurisdictionModal = openJurisdictionModal;
window.closeJurisdictionModal = closeJurisdictionModal;
window.saveJurisdiction = saveJurisdiction;
window.openAiEngineModal = openAiEngineModal;
window.closeAiEngineModal = closeAiEngineModal;
window.saveAiEngineSettings = saveAiEngineSettings;
window.toggleDayNightMode = toggleDayNightMode;
window.openBeginnerManualModal = openBeginnerManualModal;
window.closeBeginnerManualModal = closeBeginnerManualModal;
window.toggleLanguage = toggleLanguage;
window.setLanguage = setLanguage;

// -------------------------------------------------------------
// 6. INICIALIZACIÓN AL CARGAR EL DOM
// -------------------------------------------------------------
document.addEventListener('DOMContentLoaded', async () => {
    initKeyboardShortcuts();
    await fetchTeamData();
    applySavedTheme();
    applyLanguageToDOM();

    // Buscar IA local (Ollama) al cargar y cada 60 s, sin avisos si no cambia nada
    detectLocalAi({ silent: true });
    setInterval(() => {
        if (!document.hidden) detectLocalAi({ silent: true });
    }, 60000);

    // Event Delegation para el tablero Kanban
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const action = btn.getAttribute('data-action');
        const taskId = btn.getAttribute('data-task-id');
        const status = btn.getAttribute('data-task-status');

        if (action === 'move-task-forward') moveTaskForward(taskId, status);
        else if (action === 'move-task-backward') moveTaskBackward(taskId, status);
        else if (action === 'open-task-detail') openTaskDetail(taskId);
        else if (action === 'show-task-prompt') openTaskDetail(taskId);
        else if (action === 'request-task-review') openTaskDetail(taskId);
    });

    // Actualización de viewport al redimensionar ventana
    window.addEventListener('resize', () => {
        if (currentTab === 'ux') {
            renderUxHonestView();
        }
    });

    // Auto-refresco del feed de actividad cada 12 segundos
    setInterval(() => {
        if (currentTab === 'kanban') {
            renderActivityFeed();
        }
    }, 12000);
});
