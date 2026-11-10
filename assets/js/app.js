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
    apiFetch,
    jsArg
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
    applyLanguageToDOM,
    t
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
    closeNewTaskModal
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

import {
    openTaskDetail,
    closeTaskDetail,
    showTaskPrompt,
    showReviewPrompt,
    populateTaskSkillsSelector,
    renderJurisdictionBadge,
    openChangeWorkspaceModal,
    closeChangeWorkspaceModal,
    saveWorkspaceChange,
    openJurisdictionModal,
    closeJurisdictionModal,
    openAiEngineModal
} from './bindings.js';

import { renderDirectorPanel, isPluginMode } from './director.js';

export function renderPluginNotice() {
    const el = document.getElementById('plugin-notice');
    if (!el) return;
    const on = isPluginMode();
    el.classList.toggle('hidden', !on);
    el.textContent = on ? 'ℹ️ ' + t('plugin_notice') : '';
}

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
        if (rawData && rawData.success === false) throw new Error(rawData.error || 'Respuesta de error del servidor');
    } catch (netErr) {
        console.error('Error de red al consultar api/tasks.php:', netErr);
        showToast('Sin conexión', 'No se pudo cargar el tablero: ' + (netErr.message || 'revisa que Apache esté encendido'), 'error', 6000);
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
        renderPluginNotice();
        renderKanban();
        renderChat();
        populateRoleSelects();
        renderUxHonestView();
        renderMeetingsBadge();
        renderActivityFeed();
        renderLocalAiControls();
        renderDirectorPanel();
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

    // Presupuesto de Sprint
    const sprint = appData.active_sprint || appData.sprint || {};
    const budgetUsd = parseFloat(sprint.budget_usd || 0.0);
    const totalCostUsd = parseFloat(costs.total_cost_usd || 0.0);

    const budgetInput = document.getElementById('modal-costs-budget-input');
    const budgetStatus = document.getElementById('modal-costs-budget-status');
    const budgetBar = document.getElementById('modal-costs-budget-bar');
    const budgetWarning = document.getElementById('modal-costs-budget-warning');
    const budgetWarningText = document.getElementById('modal-costs-budget-warning-text');

    if (budgetInput) budgetInput.value = budgetUsd > 0 ? budgetUsd.toFixed(2) : '';
    if (budgetStatus) {
        budgetStatus.textContent = budgetUsd > 0
            ? `$${totalCostUsd.toFixed(4)} gastados de $${budgetUsd.toFixed(2)} límite`
            : `$${totalCostUsd.toFixed(4)} gastados (sin límite definido)`;
    }

    if (budgetBar) {
        if (budgetUsd > 0) {
            const pct = Math.min(100, Math.round((totalCostUsd / budgetUsd) * 100));
            budgetBar.style.width = `${pct}%`;
            if (pct >= 90) {
                budgetBar.className = 'h-full bg-rose-500 transition-all duration-300';
            } else if (pct >= 70) {
                budgetBar.className = 'h-full bg-amber-500 transition-all duration-300';
            } else {
                budgetBar.className = 'h-full bg-emerald-500 transition-all duration-300';
            }
        } else {
            budgetBar.style.width = '0%';
        }
    }

    if (budgetWarning) {
        if (budgetUsd > 0 && totalCostUsd >= budgetUsd * 0.8) {
            budgetWarning.classList.remove('hidden');
            if (budgetWarningText) {
                const pct = Math.round((totalCostUsd / budgetUsd) * 100);
                budgetWarningText.textContent = totalCostUsd >= budgetUsd
                    ? `⚠️ Has alcanzado el 100% de tu presupuesto ($${totalCostUsd.toFixed(2)} de $${budgetUsd.toFixed(2)}). Considera pasar a la IA local (Ollama) para llamadas sin costo de tokens.`
                    : `⚠️ Te estás acercando al límite de tu presupuesto (${pct}% consumido). Considera pasar a la IA local (Ollama) para llamadas sin costo de tokens.`;
            }
        } else {
            budgetWarning.classList.add('hidden');
        }
    }

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

// POST de una acción a api/tasks.php; devuelve siempre un objeto JSON ({success:false,error} si falla)
async function postAction(body) {
    const res = await apiFetch('api/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    try {
        return await res.json();
    } catch (_) {
        return { success: false, error: `El servidor respondió HTTP ${res.status}.` };
    }
}

export async function saveSprintBudget() {
    const input = document.getElementById('modal-costs-budget-input');
    const val = parseFloat(input?.value || 0);
    const sprint = appData.active_sprint || appData.sprint || {};
    try {
        const res = await postAction({
            action: 'update_sprint_budget',
            sprint_id: sprint.id || '',
            budget_usd: val
        });
        if (res.success) {
            showToast('Presupuesto guardado', val > 0 ? `Límite del sprint: $${val.toFixed(2)} USD` : 'Sprint sin límite de gasto', 'success');
            if (sprint) sprint.budget_usd = val;
            openCostsModal();
        } else {
            showToast('Error', res.error || 'No se pudo guardar el presupuesto', 'error');
        }
    } catch (e) {
        showToast('Error', e.message, 'error');
    }
}

// -------------------------------------------------------------
// MEMORIA NODULAR DE AGENTES POR PROYECTO
// -------------------------------------------------------------
let currentMemoryRoleId = null;
let currentMemoryRoleName = '';
let currentMemoryFilter = 'all';
let currentLoadedMemories = [];

export async function openAgentMemoryModal(roleId, roleName) {
    currentMemoryRoleId = roleId;
    currentMemoryRoleName = roleName || roleId;
    currentMemoryFilter = 'all';

    const modal = document.getElementById('agent-memory-modal');
    const nameEl = document.getElementById('agent-memory-role-name');
    const idEl = document.getElementById('agent-memory-role-id');
    const projEl = document.getElementById('agent-memory-project-name');

    if (nameEl) nameEl.textContent = currentMemoryRoleName;
    if (idEl) idEl.textContent = currentMemoryRoleId;
    if (projEl) projEl.textContent = appData.current_project?.name || appData.active_workspace || 'default';

    toggleAddMemoryForm(false);
    if (modal) modal.classList.remove('hidden');

    await loadAgentMemories(roleId);
}

export function closeAgentMemoryModal() {
    const modal = document.getElementById('agent-memory-modal');
    if (modal) modal.classList.add('hidden');
    currentMemoryRoleId = null;
}

export function toggleAddMemoryForm(show) {
    const form = document.getElementById('agent-memory-create-form');
    if (!form) return;
    if (show === undefined) {
        form.classList.toggle('hidden');
    } else if (show) {
        form.classList.remove('hidden');
    } else {
        form.classList.add('hidden');
    }
}

export async function loadAgentMemories(roleId) {
    const listEl = document.getElementById('agent-memory-list');
    if (!listEl) return;
    listEl.innerHTML = '<div class="p-4 text-center text-slate-500 font-mono">Cargando memorias nodulares...</div>';

    try {
        const projId = appData.current_project?.id || 'default';
        const res = await postAction({
            action: 'list_memories',
            project: projId,
            role: roleId,
            limit: 100
        });

        if (res.success === false) throw new Error(res.error || 'No se pudieron cargar las memorias');
        currentLoadedMemories = res.memories || [];
        renderAgentMemoryItems();
    } catch (e) {
        listEl.innerHTML = `<div class="p-4 text-center text-rose-400">Error cargando memorias: ${escapeHtml(e.message)}</div>`;
    }
}

export function filterAgentMemories(type) {
    currentMemoryFilter = type;
    document.querySelectorAll('.mem-filter-btn').forEach(btn => {
        btn.classList.remove('bg-purple-600', 'text-white', 'font-bold');
        btn.classList.add('bg-slate-800', 'text-slate-300');
    });
    const clicked = event?.target;
    if (clicked && clicked.classList) {
        clicked.classList.remove('bg-slate-800', 'text-slate-300');
        clicked.classList.add('bg-purple-600', 'text-white', 'font-bold');
    }
    renderAgentMemoryItems();
}

function renderAgentMemoryItems() {
    const listEl = document.getElementById('agent-memory-list');
    if (!listEl) return;

    let items = currentLoadedMemories;
    if (currentMemoryFilter !== 'all') {
        items = items.filter(m => m.node_type === currentMemoryFilter);
    }

    if (!items || items.length === 0) {
        listEl.innerHTML = `
            <div class="p-6 text-center rounded-2xl bg-slate-950/40 border border-slate-800/80 text-slate-500 space-y-2">
                <span class="text-3xl block">🧠</span>
                <p>No hay nodos de memoria registrados para este agente en el proyecto actual.</p>
                <p class="text-[11px] text-slate-600">Al completar historias de usuario, se auto-consolidan lecciones y avances deterministas aquí.</p>
            </div>
        `;
        return;
    }

    const typeIcons = {
        progress: '📈 Avance',
        decision: '⚖️ Decisión',
        learning: '💡 Aprendizaje',
        scratchpad: '📝 Nota',
        domain: '📐 Dominio'
    };

    const typeColors = {
        progress: 'border-emerald-500/30 text-emerald-300 bg-emerald-950/20',
        decision: 'border-blue-500/30 text-blue-300 bg-blue-950/20',
        learning: 'border-amber-500/30 text-amber-300 bg-amber-950/20',
        scratchpad: 'border-slate-500/30 text-slate-300 bg-slate-950/20',
        domain: 'border-purple-500/30 text-purple-300 bg-purple-950/20'
    };

    listEl.innerHTML = items.map(m => {
        const badgeClass = typeColors[m.node_type] || 'border-slate-500/30 text-slate-300 bg-slate-950/20';
        const typeLabel = typeIcons[m.node_type] || m.node_type;
        const isActive = !!m.is_active;
        const opacityClass = isActive ? '' : 'opacity-50 grayscale';

        return `
            <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800/80 hover:border-slate-700 transition space-y-2 ${opacityClass}">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-full border ${badgeClass}">
                            ${escapeHtml(typeLabel)}
                        </span>
                        <span class="text-[10px] font-mono text-purple-300 bg-purple-950/40 px-1.5 py-0.5 rounded border border-purple-500/20">
                            ⭐ ${m.importance_score}/10
                        </span>
                        ${m.role_id === 'all' ? '<span class="text-[9px] font-mono text-indigo-300 bg-indigo-950/30 px-1 rounded border border-indigo-500/20">Global Equipo</span>' : ''}
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button onclick="toggleAgentMemory(${jsArg(m.id)}, ${!isActive})" class="text-[11px] px-2 py-0.5 rounded-lg border transition font-mono ${isActive ? 'bg-slate-900 border-slate-700 text-slate-400 hover:text-amber-300' : 'bg-emerald-950/40 border-emerald-500/30 text-emerald-300 hover:bg-emerald-900/40'}">
                            ${isActive ? '⏸️ Desactivar' : '▶️ Activar'}
                        </button>
                        <button onclick="deleteAgentMemory(${jsArg(m.id)})" class="text-[11px] px-2 py-0.5 rounded-lg bg-rose-950/30 hover:bg-rose-900/50 border border-rose-500/30 text-rose-300 transition" title="Eliminar nodo">
                            🗑️
                        </button>
                    </div>
                </div>
                <div>
                    <h5 class="text-xs font-bold text-white mb-0.5">${escapeHtml(m.title)}</h5>
                    <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line font-mono bg-slate-900/60 p-2 rounded-xl border border-slate-800/60">${escapeHtml(m.content_text)}</p>
                </div>
            </div>
        `;
    }).join('');
}

export async function saveNewAgentMemory() {
    const typeEl = document.getElementById('new-memory-type');
    const impEl = document.getElementById('new-memory-importance');
    const scopeEl = document.getElementById('new-memory-scope');
    const titleEl = document.getElementById('new-memory-title');
    const contentEl = document.getElementById('new-memory-content');

    const title = titleEl?.value.trim() || '';
    const content = contentEl?.value.trim() || '';
    if (!title || !content) {
        showToast('Faltan datos', 'El título y el contenido son obligatorios', 'error');
        return;
    }

    const projId = appData.current_project?.id || 'default';
    const roleId = scopeEl?.value === 'all' ? 'all' : (currentMemoryRoleId || 'all');
    const nodeType = typeEl?.value || 'learning';
    const importance = parseInt(impEl?.value || '5', 10);

    try {
        const res = await postAction({
            action: 'save_memory',
            project_id: projId,
            role_id: roleId,
            node_type: nodeType,
            title: title,
            content_text: content,
            importance_score: importance,
            is_active: 1
        });

        if (res.success) {
            showToast('Memoria', 'Nodo de memoria guardado', 'success');
            if (titleEl) titleEl.value = '';
            if (contentEl) contentEl.value = '';
            toggleAddMemoryForm(false);
            await loadAgentMemories(currentMemoryRoleId);
        } else {
            showToast('Error', res.error || 'Error al guardar memoria', 'error');
        }
    } catch (e) {
        showToast('Error', e.message, 'error');
    }
}

export async function toggleAgentMemory(id, newState) {
    try {
        const res = await postAction({
            action: 'toggle_memory',
            memory_id: id,
            is_active: newState ? 1 : 0
        });
        if (res.success) {
            showToast('Memoria', newState ? 'Memoria activada' : 'Memoria desactivada', 'success');
            await loadAgentMemories(currentMemoryRoleId);
        } else {
            showToast('Error', res.error || 'No se pudo cambiar la memoria', 'error');
        }
    } catch (e) {
        showToast('Error', e.message, 'error');
    }
}

export async function deleteAgentMemory(id) {
    if (!confirm('¿Eliminar permanentemente este nodo de memoria?')) return;
    try {
        const res = await postAction({
            action: 'delete_memory',
            memory_id: id
        });
        if (res.success) {
            showToast('Memoria', 'Nodo de memoria eliminado', 'success');
            await loadAgentMemories(currentMemoryRoleId);
        } else {
            showToast('Error', res.error || 'No se pudo eliminar la memoria', 'error');
        }
    } catch (e) {
        showToast('Error', e.message, 'error');
    }
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
    const pluginMode = isPluginMode();

    container.innerHTML = appData.roles.map(r => {
        const assignedSkills = r.assigned_skills || [];
        const isLegal = r.id === 'legal_counsel';
        const isUx = r.id === 'frontend_master';
        const isDesigner = r.id === 'creative_director';

        let badgeGlow = 'border-slate-800';
        if (isLegal) badgeGlow = 'border-amber-500/30 bg-amber-950/10';
        if (isUx) badgeGlow = 'border-pink-500/30 bg-pink-950/10';
        if (isDesigner) badgeGlow = 'border-purple-500/30 bg-purple-950/10';

        const eng = r.engine || {};
        const sourceText = { role: 'motor propio del rol', local: 'IA local', global: 'motor global', none: 'sin motor' }[eng.source] || '';
        const engineBadge = eng.ready
            ? `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 border ${eng.kind === 'local' ? 'border-emerald-500/40 text-emerald-300' : 'border-purple-500/40 text-purple-300'} flex items-center gap-1" title="Responde con ${escapeHtml(eng.label)} (${sourceText})${eng.reason ? ' — ' + escapeHtml(eng.reason) : ''}">🤖 ${escapeHtml(eng.label)}</span>`
            : pluginMode
            ? `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-900 border border-slate-600 text-slate-300 flex items-center gap-1" title="${escapeHtml(t('plugin_engine_hint'))}">${escapeHtml(t('plugin_engine_badge'))}</span>`
            : `<span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-950 border border-amber-500/40 text-amber-300 flex items-center gap-1" title="${escapeHtml(eng.reason || '')}">⚠️ Sin motor</span>`;
        const currentVal = r.ai_provider === 'local' ? 'local::' : (r.ai_provider ? `${r.ai_provider}::${r.ai_model || ''}` : '');
        const engineSelect = `
            <div class="space-y-1">
                <div class="flex items-center justify-between">
                    <label class="block text-[10px] uppercase text-slate-400 font-bold tracking-wider">Motor de IA de este rol</label>
                    ${!roleRouting ? `<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20" title="Actívalo en Motor IA → Enrutamiento Especializado por Rol">Global activo</span>` : ''}
                </div>
                <select onchange="changeRoleEngine(${jsArg(r.id)}, this.value)" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-[11px] font-mono outline-none focus:border-purple-500 ${!roleRouting ? 'opacity-70' : ''}">
                    <option value="" ${currentVal === '' ? 'selected' : ''}>Heredar (motor global o IA local)</option>
                    <option value="local::" ${currentVal === 'local::' ? 'selected' : ''}>🏠 IA local activa (Ollama / Colibri)</option>
                    ${providers.map(p => `
                        <optgroup label="${escapeHtml(p.icon || '')} ${escapeHtml(p.name)}${p.api_key_configured ? ' ✓' : (p.env_key ? ' — sin clave' : '')}">
                            ${(p.models || []).map(m => `<option value="${escapeHtml(p.id)}::${escapeHtml(m.id)}" ${currentVal === `${p.id}::${m.id}` ? 'selected' : ''}>${escapeHtml(m.name || m.id)}</option>`).join('')}
                        </optgroup>`).join('')}
                </select>
                ${!roleRouting ? `<p class="text-[10px] text-indigo-300">ℹ️ Enrutamiento desactivado: este rol usa el motor global <strong>${escapeHtml(appData.ai_engine?.provider_name || 'Global')}</strong>.</p>` : `
                <p class="text-[10px] ${eng.ready || pluginMode ? 'text-slate-400' : 'text-amber-300'}">${eng.ready ? `Responde ahora: <span class="font-mono text-slate-200">${escapeHtml(eng.label)}</span> (${sourceText})` : (pluginMode ? escapeHtml(t('plugin_engine_hint')) : escapeHtml(eng.reason || 'Sin motor disponible'))}${eng.ready && eng.reason ? `<br><span class="text-amber-300/80">${escapeHtml(eng.reason)}</span>` : ''}</p>`}
            </div>`;
        const isDirector = r.id === 'director';
        if (isDirector) badgeGlow = 'border-amber-500/50 bg-gradient-to-br from-amber-950/30 to-slate-950 md:col-span-2 lg:col-span-3';

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
                    ${isDirector ? `<p class="text-[11px] text-amber-200/90">${escapeHtml(r.description || '')}</p>` : ''}
                    ${engineSelect}

                    <!-- Chips de Skills Asignadas -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[10px] text-slate-400 uppercase font-bold tracking-wider">
                            <span>Skills Activas (${assignedSkills.length})</span>
                            <button onclick="openSkillsConfig(${jsArg(r.id)})" class="text-indigo-400 hover:text-indigo-300 lowercase text-[10px] underline">editar</button>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            ${assignedSkills.map(s => `
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-slate-900 border border-slate-800 text-slate-300 flex items-center gap-1 cursor-pointer hover:border-indigo-500/40" onclick="openSkillDetail(${jsArg(s)})">
                                    <span>⚡</span> <span>${escapeHtml(s)}</span>
                                </span>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between gap-1 flex-wrap">
                    <button onclick="quickConsultAgent(${jsArg(r.id)})" class="text-xs text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1">
                        <span>💬</span> Chat
                    </button>
                    <button onclick="openAgentMemoryModal(${jsArg(r.id)}, ${jsArg(r.name)})" class="text-xs px-2 py-1 rounded-xl bg-purple-950/40 hover:bg-purple-900/60 border border-purple-500/30 text-purple-200 font-medium transition flex items-center gap-1">
                        <span>🧠</span> Memoria
                    </button>
                    <button onclick="openSkillsConfig(${jsArg(r.id)})" class="text-xs px-2.5 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 font-medium transition">
                        ⚙️ Skills
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
        wsDisplay.textContent = ws || '—';
    }
}

export function renderAiEngineBadge() {
    // El badge "Motor IA" de la barra superior (header-ai-icon / header-ai-model) lo pinta local-ai.js,
    // porque muestra el modelo local cuando la IA local está activada.
    renderLocalAiControls();
}

export function closeAiEngineModal() {
    const modal = document.getElementById('ai-engine-modal');
    if (modal) modal.classList.add('hidden');
}

export async function saveAiEngineSettings() {
    const provider = document.getElementById('ai-provider-select')?.value || 'anthropic';
    const model = document.getElementById('ai-model-select')?.value || 'claude-sonnet-5-5';
    const mode = document.getElementById('ai-execution-mode')?.value || 'api';
    const roleRouting = document.getElementById('ai-role-routing-toggle')?.checked !== false;
    const endpoint = document.getElementById('ai-endpoint-input')?.value?.trim() || '';

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_ai_engine',
                active_provider: provider,
                active_model: model,
                execution_mode: mode,
                role_routing_enabled: roleRouting,
                endpoint: endpoint
            })
        });
        const data = await res.json();
        if (data.success) {
            closeAiEngineModal();
            showToast('Motor IA Configurado', `${provider} (${model})`, 'magic');
            await fetchTeamData();
        } else {
            showToast('No se guardó', data.error || 'No se pudo actualizar el motor IA', 'error');
        }
    } catch (err) {
        showToast('Error', 'No se pudo actualizar motor IA', 'error');
    }
}

export async function toggleRoleRoutingImmediate(enabled) {
    try {
        const ai = appData.ai_engine || {};
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_ai_engine',
                active_provider: ai.active_provider || 'anthropic',
                active_model: ai.active_model || 'claude-sonnet-5-5',
                execution_mode: ai.execution_mode || 'api',
                role_routing_enabled: enabled,
                endpoint: ai.endpoint || ''
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(
                enabled ? 'Enrutamiento por Rol Activado' : 'Enrutamiento por Rol Desactivado',
                enabled ? 'Cada agente responderá con su motor asignado en Equipo de Agentes.' : 'Todos los agentes responderán con el motor global.',
                'info',
                3000
            );
            await fetchTeamData();
        } else {
            showToast('No se guardó', data.error || 'No se pudo cambiar el enrutamiento por rol', 'error');
        }
    } catch (err) {
        console.error('Error toggling role routing:', err);
        showToast('Error', 'No se pudo cambiar el enrutamiento por rol', 'error');
    }
}

export function populateRoleSelects() {
    const roles = appData.roles || [];
    const fill = (sel, label, fallback) => {
        if (!sel) return;
        const prev = sel.value;
        sel.innerHTML = roles.map(r => `<option value="${escapeHtml(r.id)}">${escapeHtml(label(r))}</option>`).join('');
        sel.value = roles.some(r => r.id === prev) ? prev : fallback;
    };
    fill(document.getElementById('task-role'), r => `${r.avatar} ${r.name}`, roles.find(r => r.id === 'project_manager') ? 'project_manager' : roles[0]?.id);
    fill(document.getElementById('chat-target-role'), r => `${r.avatar} ${r.name}${r.alias ? ' (' + r.alias + ')' : ''}`, 'director');
}

export async function changeRoleEngine(roleId, value) {
    const [provider, model] = String(value || '').split('::');
    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_role_engine', role_id: roleId, ai_provider: provider || '', ai_model: model || '' })
        });
        const data = await res.json();
        if (!data.success) {
            showToast('No se cambió el motor', data.error || 'Error', 'error');
        } else {
            const e = data.engine || {};
            showToast('Motor asignado', e.ready ? `${roleId} responderá con ${e.label}` : `${roleId}: ${e.reason || 'sin motor disponible'}`, e.ready ? 'success' : 'warning', 4000);
        }
    } catch (err) {
        showToast('Error', 'No se pudo cambiar el motor del rol', 'error');
    }
    await fetchTeamData();
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
window.openTaskDetail = openTaskDetail;
window.closeTaskDetail = closeTaskDetail;
window.changeRoleEngine = changeRoleEngine;
window.populateRoleSelects = populateRoleSelects;

// Costos & Sprint
window.renderSprintBanner = renderSprintBanner;
window.openCostsModal = openCostsModal;
window.closeCostsModal = closeCostsModal;
window.saveSprintBudget = saveSprintBudget;

// Memoria Nodular de Agentes
window.openAgentMemoryModal = openAgentMemoryModal;
window.closeAgentMemoryModal = closeAgentMemoryModal;
window.toggleAddMemoryForm = toggleAddMemoryForm;
window.loadAgentMemories = loadAgentMemories;
window.filterAgentMemories = filterAgentMemories;
window.saveNewAgentMemory = saveNewAgentMemory;
window.toggleAgentMemory = toggleAgentMemory;
window.deleteAgentMemory = deleteAgentMemory;

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
window.saveWorkspace = saveWorkspaceChange;
window.openJurisdictionModal = openJurisdictionModal;
window.closeJurisdictionModal = closeJurisdictionModal;
window.openAiEngineModal = openAiEngineModal;
window.closeAiEngineModal = closeAiEngineModal;
window.saveAiEngineSettings = saveAiEngineSettings;
window.toggleRoleRoutingImmediate = toggleRoleRoutingImmediate;
window.toggleDayNightMode = toggleDayNightMode;
window.openBeginnerManualModal = openBeginnerManualModal;
window.closeBeginnerManualModal = closeBeginnerManualModal;
window.toggleLanguage = toggleLanguage;
window.renderPluginNotice = renderPluginNotice;
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
        else if (action === 'show-task-prompt') showTaskPrompt(taskId);
        else if (action === 'request-task-review') showReviewPrompt(taskId);
        else if (action === 'sign-and-complete') {
            updateTaskStatus(taskId, 'completada').then(ok => {
                if (ok) showToast('Firma Humana Registrada', 'Historia completada con éxito', 'success', 2500);
            });
        }
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
