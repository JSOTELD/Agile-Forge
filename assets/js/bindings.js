/**
 * AgileForge v2 - Enlaces de la interfaz
 * Funciones que llaman los botones del HTML (onclick / onchange) y que no existían en los módulos:
 * crear historia, detalle y prompts de historias, tema y densidad, paleta de comandos,
 * jurisdicción, carpeta de trabajo, Motor IA y claves de API.
 */

import { appData, apiFetch, escapeHtml, jsArg, localTime } from './state.js';
import { showToast, switchMainTab } from './ui.js';
import { getLanguage, t as tr } from './i18n.js';
import { roleLabel, statusLabel, normalizePriority } from './roles.js';

async function post(action, extra = {}) {
    const res = await apiFetch('api/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra })
    });
    let data = {};
    try { data = await res.json(); } catch (e) { data = { success: false, error: `El servidor respondió HTTP ${res.status} sin datos válidos.` }; }
    if (!res.ok && data.success === undefined) data.success = false;
    return data;
}

const show = id => document.getElementById(id)?.classList.remove('hidden');
const hide = id => document.getElementById(id)?.classList.add('hidden');
const roleById = id => (appData.roles || []).find(r => r.id === id);

// -------------------------------------------------------------
// Nueva historia
// -------------------------------------------------------------
let selectedTaskSkills = new Set();

export function populateTaskSkillsSelector(roleId) {
    const box = document.getElementById('task-skills-selector');
    if (!box) return;
    const role = roleById(roleId);
    const roleSkills = role?.assigned_skills || [];
    const catalog = (appData.skills_catalog || []).map(s => s.id);
    const all = [...new Set([...roleSkills, ...catalog])].slice(0, 40);
    selectedTaskSkills = new Set(roleSkills.slice(0, 3));

    box.innerHTML = all.map(s => `
        <button type="button" data-skill="${escapeHtml(s)}"
            class="task-skill-chip text-[10px] font-mono px-2 py-0.5 rounded-md border transition ${selectedTaskSkills.has(s) ? 'bg-indigo-600/30 border-indigo-500 text-indigo-200' : 'bg-slate-900 border-slate-800 text-slate-400'}">
            ⚡ ${escapeHtml(s)}
        </button>`).join('') || '<span class="text-[11px] text-slate-500">Este agente no tiene skills asignadas.</span>';

    box.querySelectorAll('.task-skill-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            const s = btn.dataset.skill;
            if (selectedTaskSkills.has(s)) selectedTaskSkills.delete(s); else selectedTaskSkills.add(s);
            btn.className = `task-skill-chip text-[10px] font-mono px-2 py-0.5 rounded-md border transition ${selectedTaskSkills.has(s) ? 'bg-indigo-600/30 border-indigo-500 text-indigo-200' : 'bg-slate-900 border-slate-800 text-slate-400'}`;
        });
    });
}

export async function handleCreateTask(e) {
    if (e?.preventDefault) e.preventDefault();
    const title = document.getElementById('task-title')?.value?.trim();
    if (!title) {
        showToast('Falta el título', 'Escribe el título de la historia', 'warning');
        return;
    }
    const btn = document.querySelector('#task-form button[type="submit"]');
    if (btn) btn.disabled = true;
    try {
        const data = await post('create_task', {
            title,
            assigned_to: document.getElementById('task-role')?.value || 'project_manager',
            story_points: parseInt(document.getElementById('task-points')?.value, 10) || 3,
            priority: document.getElementById('task-priority')?.value || 'media',
            user_story: document.getElementById('task-user-story')?.value?.trim() || '',
            dod: document.getElementById('task-dod')?.value?.trim() || '',
            description: document.getElementById('task-desc')?.value?.trim() || '',
            skills: [...selectedTaskSkills],
            client: 'web'
        });
        if (!data.success) {
            showToast('No se pudo crear', data.error || 'Error al crear la historia', 'error');
            return;
        }
        document.getElementById('task-form')?.reset();
        hide('new-task-modal');
        showToast('Historia creada', `${data.task.id} asignada a ${roleById(data.task.assigned_to)?.name || data.task.assigned_to}`, 'magic');
        if (window.fetchTeamData) await window.fetchTeamData();
        openPromptModal(`${data.task.id} · ${data.task.title}`, data.task.prompt_template || '', 'Prompt de Misión');
    } catch (err) {
        showToast('Error', 'Fallo al crear la historia', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// -------------------------------------------------------------
// Modal de prompt (misión / auditoría DoD)
// -------------------------------------------------------------
export function openPromptModal(title, text, badge = 'Prompt de Misión') {
    const t = document.getElementById('prompt-modal-title');
    const x = document.getElementById('prompt-modal-text');
    const b = document.getElementById('prompt-modal-badge');
    if (t) t.textContent = title;
    if (x) x.value = text || '(Sin prompt)';
    if (b) b.textContent = badge;
    const copy = document.getElementById('copy-btn-text');
    if (copy) copy.textContent = 'Copiar Prompt';
    show('prompt-modal');
}

export function closePromptModal() { hide('prompt-modal'); }

export async function copyPromptText() {
    const text = document.getElementById('prompt-modal-text')?.value || '';
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        const ta = document.getElementById('prompt-modal-text');
        ta?.select();
        document.execCommand && document.execCommand('copy');
    }
    const copy = document.getElementById('copy-btn-text');
    if (copy) copy.textContent = '¡Copiado!';
    showToast('Copiado', 'Pégalo en Claude Code o en tu terminal', 'success', 2000);
}

export function showTaskPrompt(taskId) {
    const t = (appData.tasks || []).find(x => x.id === taskId);
    if (!t) return;
    openPromptModal(`${t.id} · ${t.title}`, t.prompt_template || `Actúa como ${t.assigned_to}. Ejecuta: ${t.title}\nDoD: ${t.dod || ''}`, 'Prompt de Misión');
}

export async function showReviewPrompt(taskId) {
    const data = await post('generate_review_prompt', { task_id: taskId });
    if (!data.success) {
        showToast('Error', data.error || 'No se pudo generar el prompt de auditoría', 'error');
        return;
    }
    const t = (appData.tasks || []).find(x => x.id === taskId);
    openPromptModal(`${taskId} · ${t?.title || ''}`, data.review_prompt, 'Auditoría DoD');
}

// -------------------------------------------------------------
// Detalle y edición de una historia
// -------------------------------------------------------------
let detailTaskId = null;

export function openTaskDetail(taskId) {
    const t = (appData.tasks || []).find(x => x.id === taskId);
    if (!t) return;
    detailTaskId = taskId;
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('detail-task-id', t.id);
    set('detail-task-title', t.title);
    set('detail-task-status', statusLabel(t.status || 'backlog'));

    const roleSel = document.getElementById('detail-task-role');
    if (roleSel) {
        roleSel.innerHTML = (appData.roles || []).map(r => `<option value="${escapeHtml(r.id)}" ${r.id === t.assigned_to ? 'selected' : ''}>${escapeHtml(r.avatar)} ${escapeHtml(r.name)}</option>`).join('');
    }
    const prio = document.getElementById('detail-task-priority');
    if (prio) prio.value = normalizePriority(t.priority);
    const pts = document.getElementById('detail-task-points');
    if (pts) pts.value = String(t.story_points || 3);
    const story = document.getElementById('detail-task-story');
    if (story) story.value = t.user_story || '';
    const dod = document.getElementById('detail-task-dod');
    if (dod) dod.value = t.dod || '';
    const desc = document.getElementById('detail-task-desc');
    if (desc) desc.value = t.description || '';
    const skills = document.getElementById('detail-task-skills');
    if (skills) skills.innerHTML = (t.skills || []).map(s => `<span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-300">⚡ ${escapeHtml(s)}</span>`).join('') || '<span class="text-[11px] text-slate-500">Sin skills</span>';
    renderReviewBlock(t);
    show('task-detail-modal');
}

/**
 * Estado de la revisión en el detalle: qué entregó el agente, qué verificó QA y, si procede, el botón de firma.
 * Solo lectura: todo sale de review_evidence, que ya viene con la historia.
 */
function renderReviewBlock(task) {
    const box = document.getElementById('detail-review-block');
    if (!box) return;
    const ev = task.review_evidence || null;
    const inReview = task.status === 'revision';
    if (!inReview && !(task.status === 'completada' && ev)) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
    }
    const list = (items) => {
        const arr = (Array.isArray(items) ? items : []).filter(x => typeof x === 'string' && x.trim() !== '');
        return arr.length ? `<ul>${arr.map(x => `<li><code>${escapeHtml(x)}</code></li>`).join('')}</ul>` : '—';
    };
    const row = (label, html) => `<dt>${escapeHtml(label)}</dt><dd>${html}</dd>`;
    const qa = ev?.qa_approved || null;
    let html = `<h4>${escapeHtml(tr('review_title', 'Revisión'))}</h4>`;
    if (ev) {
        html += `<dl>
            ${row(tr('review_summary', 'Entrega'), escapeHtml(ev.summary || '—'))}
            ${row(tr('review_files', 'Archivos'), list(ev.files_changed))}
            ${row(tr('review_tests', 'Pruebas'), list(ev.tests_run))}
        </dl>`;
    } else {
        html += `<p class="review-wait">${escapeHtml(tr('review_no_delivery', 'Todavía no hay una entrega registrada.'))}</p>`;
    }
    if (qa) {
        const secs = Number(qa.review_duration_seconds);
        html += `<dl>
            ${row(tr('review_by', 'Aprobó'), `${escapeHtml(roleLabel(qa.reviewer))} · ${escapeHtml(localTime(qa.approved_at))}${Number.isFinite(secs) ? ` · ${escapeHtml(String(secs))} s` : ''}`)}
            ${row(tr('review_checklist', 'Checklist'), list(qa.checklist))}
            ${row(tr('review_commands', 'Verificó con'), list(qa.verification?.commands_run))}
            ${row(tr('review_notes', 'Notas de QA'), escapeHtml(qa.verification?.notes || '—'))}
        </dl>`;
    }
    if (inReview && qa) {
        html += `<button type="button" data-action="sign-and-complete" data-task-id="${escapeHtml(task.id)}" class="btn-sign"><span aria-hidden="true">✍️</span> ${escapeHtml(tr('sign_complete', 'Firmar y completar'))}</button>`;
    } else if (inReview) {
        html += `<p class="review-wait">${escapeHtml(tr('review_waiting', 'Esperando la revisión de QA.'))}</p>`;
    }
    box.innerHTML = html;
    box.classList.remove('hidden');
}

export function closeTaskDetail() { hide('task-detail-modal'); detailTaskId = null; }

export async function saveTaskDetail() {
    if (!detailTaskId) return;
    const data = await post('update_task', {
        task_id: detailTaskId,
        client: 'web',
        fields: {
            assigned_to: document.getElementById('detail-task-role')?.value,
            priority: document.getElementById('detail-task-priority')?.value,
            story_points: parseInt(document.getElementById('detail-task-points')?.value, 10),
            user_story: document.getElementById('detail-task-story')?.value || '',
            dod: document.getElementById('detail-task-dod')?.value || '',
            description: document.getElementById('detail-task-desc')?.value || ''
        }
    });
    if (!data.success) {
        showToast('No se guardó', data.error || 'Error al guardar', 'error');
        return;
    }
    showToast('Historia actualizada', detailTaskId, 'success', 2000);
    closeTaskDetail();
    if (window.fetchTeamData) await window.fetchTeamData();
}

export async function deleteTaskFromDetail() {
    if (!detailTaskId) return;
    if (!confirm(`¿Eliminar la historia ${detailTaskId}? Se borran también sus comentarios. No se puede deshacer.`)) return;
    const data = await post('delete_task', { task_id: detailTaskId, confirm: true, client: 'web' });
    if (!data.success) {
        showToast('No se eliminó', data.error || 'Error', 'error');
        return;
    }
    showToast('Historia eliminada', detailTaskId, 'info', 2500);
    closeTaskDetail();
    if (window.fetchTeamData) await window.fetchTeamData();
}

export function askDirectorAboutTask() {
    const t = (appData.tasks || []).find(x => x.id === detailTaskId);
    if (!t) return;
    closeTaskDetail();
    if (window.openDirectorWith) window.openDirectorWith(`Revisa la historia ${t.id} ("${t.title}") y decide cómo avanzarla: quién la hace, prioridad y qué falta para cumplir su DoD.`);
}

// -------------------------------------------------------------
// Tema y densidad
// -------------------------------------------------------------
export function openThemeModal() { show('theme-modal'); }
export function closeThemeModal() { hide('theme-modal'); }

export async function setDensity(density) {
    if (!['comfortable', 'compact'].includes(density)) return;
    document.documentElement.setAttribute('data-density', density);
    ['comfortable', 'compact'].forEach(d => {
        const b = document.getElementById(`density-btn-${d}`);
        if (b) b.className = `p-2.5 rounded-xl border bg-slate-950 font-bold text-center ${d === density ? 'border-indigo-500 text-white' : 'border-slate-700 text-slate-400'}`;
    });
    await post('save_ui_preferences', { density, theme: document.documentElement.getAttribute('data-theme') || 'obsidian' });
}

// -------------------------------------------------------------
// Paleta de comandos (Ctrl+K)
// -------------------------------------------------------------
const paletteCommands = [
    { label: '👑 Dar una instrucción al Director', run: () => window.openDirectorWith && window.openDirectorWith('') },
    { label: '+ Nueva historia', run: () => window.openNewTaskModal && window.openNewTaskModal() },
    { label: '💬 Abrir chat con los agentes', run: () => window.toggleChatDrawer && window.toggleChatDrawer() },
    { label: '🏛️ Ir a la Sala de Reuniones', run: () => switchMainTab('meetings') },
    { label: '🤖 Ir al Equipo de Agentes (motores por rol)', run: () => switchMainTab('team') },
    { label: '🧠 Configurar Motor IA y claves de API', run: () => window.openAiEngineModal && window.openAiEngineModal() },
    { label: '⚖️ Ir a Auditoría Legal', run: () => switchMainTab('legal') },
    { label: '🎨 Ir al Laboratorio UX', run: () => switchMainTab('ux') }
];

export function openCommandPalette() {
    show('command-palette-modal');
    const input = document.getElementById('palette-search-input');
    if (input) { input.value = ''; setTimeout(() => input.focus(), 30); }
    handlePaletteSearch();
}

export function handlePaletteSearch() {
    const q = (document.getElementById('palette-search-input')?.value || '').toLowerCase().trim();
    const box = document.getElementById('palette-results');
    if (!box) return;
    const cmds = paletteCommands.filter(c => !q || c.label.toLowerCase().includes(q));
    const tasks = (appData.tasks || []).filter(t => q && (`${t.id} ${t.title}`.toLowerCase().includes(q))).slice(0, 8);
    box.innerHTML = [
        ...cmds.map((c, i) => `<button type="button" data-cmd="${i}" class="palette-item w-full text-left px-3 py-2 rounded-xl hover:bg-slate-800 text-slate-200">${escapeHtml(c.label)}</button>`),
        ...tasks.map(t => `<button type="button" data-task="${escapeHtml(t.id)}" class="palette-item w-full text-left px-3 py-2 rounded-xl hover:bg-slate-800 text-slate-300"><span class="font-mono text-indigo-400">${escapeHtml(t.id)}</span> ${escapeHtml(t.title)} <span class="text-slate-500">· ${escapeHtml(t.status)}</span></button>`)
    ].join('') || '<p class="px-3 py-2 text-slate-500">Sin resultados</p>';
    box.querySelectorAll('.palette-item').forEach(b => b.addEventListener('click', () => {
        hide('command-palette-modal');
        if (b.dataset.cmd !== undefined) paletteCommands.filter(c => !q || c.label.toLowerCase().includes(q))[+b.dataset.cmd]?.run();
        else if (b.dataset.task) { switchMainTab('kanban'); openTaskDetail(b.dataset.task); }
    }));
}

// -------------------------------------------------------------
// Carpeta de trabajo
// -------------------------------------------------------------
export function openChangeWorkspaceModal() {
    const input = document.getElementById('workspace-input');
    if (input) input.value = appData.current_project?.path || appData.active_workspace || '';
    show('workspace-modal');
}
export function closeChangeWorkspaceModal() { hide('workspace-modal'); }

export async function saveWorkspaceChange() {
    const ws = document.getElementById('workspace-input')?.value?.trim();
    if (!ws) return;
    const data = await post('update_workspace', { workspace: ws });
    if (!data.success) {
        showToast('Ruta no válida', data.error || 'No se pudo cambiar la carpeta', 'error');
        return;
    }
    closeChangeWorkspaceModal();
    showToast('Carpeta actualizada', data.active_workspace, 'success');
    if (window.fetchTeamData) await window.fetchTeamData();
}

// -------------------------------------------------------------
// Jurisdicción
// -------------------------------------------------------------
const COUNTRY_PRESETS = {
    PE: { name: 'Perú', flag: '🇵🇪', regs: ['Ley N° 29733 (Protección de Datos Personales)', 'Ley N° 29571 (Código de Protección al Consumidor)', 'Libro de Reclamaciones Virtual Obligatorio', 'Normativa SUNAT de comprobantes electrónicos'] },
    ES: { name: 'España / UE', flag: '🇪🇸', regs: ['RGPD (Reglamento UE 2016/679)', 'LOPDGDD 3/2018', 'LSSI-CE 34/2002', 'Directiva de Cookies (ePrivacy)'] },
    MX: { name: 'México', flag: '🇲🇽', regs: ['LFPDPPP (Protección de Datos)', 'Ley Federal de Protección al Consumidor (PROFECO)', 'CFDI (SAT)'] },
    CO: { name: 'Colombia', flag: '🇨🇴', regs: ['Ley 1581 de 2012 (Habeas Data)', 'Ley 1480 de 2011 (Estatuto del Consumidor)', 'Decreto 1377 de 2013'] },
    CL: { name: 'Chile', flag: '🇨🇱', regs: ['Ley 19.628 (Protección de la Vida Privada)', 'Ley 19.496 (Derechos del Consumidor - SERNAC)'] },
    AR: { name: 'Argentina', flag: '🇦🇷', regs: ['Ley 25.326 (Habeas Data)', 'Ley 24.240 (Defensa del Consumidor)'] },
    US: { name: 'Estados Unidos', flag: '🇺🇸', regs: ['CCPA / CPRA (California)', 'ADA (Accesibilidad)', 'FTC Act Section 5', 'COPPA'] },
    GLOBAL: { name: 'Internacional', flag: '🌐', regs: ['RGPD (UE)', 'CCPA (EE. UU.)', 'WCAG 2.2 AA'] },
    CUSTOM: { name: '', flag: '🏳️', regs: [] }
};
let jurisRegs = [];

function renderRegulations() {
    const box = document.getElementById('regulations-list');
    const badge = document.getElementById('active-regs-badge');
    if (badge) badge.textContent = `${jurisRegs.length} activas`;
    if (!box) return;
    box.innerHTML = jurisRegs.map((r, i) => `
        <div class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-[11px] text-slate-200">
            <span>⚖️ ${escapeHtml(r)}</span>
            <button type="button" data-i="${i}" class="reg-remove text-slate-500 hover:text-rose-400" title="Quitar">✕</button>
        </div>`).join('') || '<p class="text-[11px] text-slate-500 px-1">Sin normativas activas.</p>';
    box.querySelectorAll('.reg-remove').forEach(b => b.addEventListener('click', () => { jurisRegs.splice(+b.dataset.i, 1); renderRegulations(); }));
}

export function openJurisdictionModal() {
    const j = appData.jurisdiction || {};
    const sel = document.getElementById('jurisdiction-select');
    if (sel) sel.value = COUNTRY_PRESETS[j.country_code] ? j.country_code : 'CUSTOM';
    const flag = document.getElementById('custom-flag-input');
    const name = document.getElementById('custom-country-input');
    if (flag) flag.value = j.flag || '';
    if (name) name.value = j.country_name || '';
    jurisRegs = [...(j.regulations || [])];
    renderRegulations();
    show('jurisdiction-modal');
}
export function closeJurisdictionModal() { hide('jurisdiction-modal'); }

export function handleCountryPresetChange(code) {
    const p = COUNTRY_PRESETS[code];
    if (!p) return;
    const flag = document.getElementById('custom-flag-input');
    const name = document.getElementById('custom-country-input');
    if (code !== 'CUSTOM') {
        if (flag) flag.value = p.flag;
        if (name) name.value = p.name;
        jurisRegs = [...p.regs];
    }
    renderRegulations();
}

export function autoDetectCountry() {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    const map = { 'America/Lima': 'PE', 'Europe/Madrid': 'ES', 'America/Mexico_City': 'MX', 'America/Bogota': 'CO', 'America/Santiago': 'CL', 'America/Argentina/Buenos_Aires': 'AR', 'America/New_York': 'US', 'America/Los_Angeles': 'US', 'America/Chicago': 'US' };
    const code = map[tz] || (tz.startsWith('Europe/') ? 'ES' : 'GLOBAL');
    const sel = document.getElementById('jurisdiction-select');
    if (sel) sel.value = code;
    handleCountryPresetChange(code);
    showToast('País detectado', `${COUNTRY_PRESETS[code].flag} ${COUNTRY_PRESETS[code].name || code} (zona horaria ${tz})`, 'info', 3000);
}

export function addCustomRegulation() {
    const input = document.getElementById('new-regulation-input');
    const v = input?.value?.trim();
    if (!v) return;
    if (!jurisRegs.includes(v)) jurisRegs.push(v);
    input.value = '';
    renderRegulations();
}

export function quickAddRegulation(text) {
    if (!jurisRegs.includes(text)) jurisRegs.push(text);
    renderRegulations();
}

export async function saveJurisdictionSettings() {
    const code = document.getElementById('jurisdiction-select')?.value || 'PE';
    const preset = COUNTRY_PRESETS[code] || COUNTRY_PRESETS.CUSTOM;
    const name = document.getElementById('custom-country-input')?.value?.trim() || preset.name || 'Personalizado';
    const flag = document.getElementById('custom-flag-input')?.value?.trim() || preset.flag;
    const data = await post('update_jurisdiction', { country_code: code, country_name: name, flag, regulations: jurisRegs });
    if (!data.success) {
        showToast('Error', data.error || 'No se pudo guardar la jurisdicción', 'error');
        return;
    }
    closeJurisdictionModal();
    showToast('Jurisdicción guardada', `${flag} ${name} · ${jurisRegs.length} normativas`, 'success');
    if (window.fetchTeamData) await window.fetchTeamData();
}

export function renderJurisdictionBadge() {
    const j = appData.jurisdiction || {};
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('jurisdiction-flag', j.flag || '🇵🇪');
    set('jurisdiction-name', j.country_name || 'Perú');
    const n = (j.regulations || []).length;
    set('jurisdiction-regs-count', `${n} ${n === 1 ? 'ley' : 'leyes'}`);
}

// -------------------------------------------------------------
// Motor IA: proveedor/modelo global y claves de API
// -------------------------------------------------------------
function providers() { return appData.ai_engine?.available_providers || []; }

export function handleProviderChange(providerId, selectedModel = null) {
    const sel = document.getElementById('ai-model-select');
    const p = providers().find(x => x.id === providerId);
    if (!sel) return;
    const models = p?.models || [];
    const current = selectedModel || p?.default_model;
    sel.innerHTML = models.map(m => `<option value="${escapeHtml(m.id)}" ${m.id === current ? 'selected' : ''}>${escapeHtml(m.name || m.id)}${m.input_cost_per_m !== undefined ? ` — $${m.input_cost_per_m}/$${m.output_cost_per_m} por M tokens` : ''}</option>`).join('');
    const ep = document.getElementById('ai-endpoint-input');
    if (ep) ep.value = p?.endpoint || '';
}

export function openAiEngineModal() {
    const ai = appData.ai_engine || {};
    const provSel = document.getElementById('ai-provider-select');
    if (provSel) {
        provSel.innerHTML = providers().map(p => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.icon || '')} ${escapeHtml(p.name)}${p.api_key_configured ? ' ✓' : ''}</option>`).join('');
        provSel.value = ai.active_provider || 'anthropic';
    }
    handleProviderChange(ai.active_provider || 'anthropic', ai.active_model);
    const mode = document.getElementById('ai-execution-mode');
    if (mode) mode.value = ai.execution_mode || 'api';
    const routing = document.getElementById('ai-role-routing-toggle');
    if (routing) routing.checked = ai.role_routing_enabled !== false;
    renderApiKeys();
    show('ai-engine-modal');
}

export function renderApiKeys() {
    const box = document.getElementById('api-keys-list');
    if (!box) return;
    const en = getLanguage() === 'en';
    const list = providers().filter(p => p.env_key);
    box.innerHTML = list.map(p => `
        <div class="p-2.5 rounded-xl bg-slate-900/70 border ${p.api_key_configured ? 'border-emerald-500/30' : 'border-slate-800'} space-y-1.5">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-white text-[11px]">${escapeHtml(p.icon || '')} ${escapeHtml(p.name)}</span>
                <span class="text-[10px] font-mono ${p.api_key_configured ? 'text-emerald-300' : 'text-slate-500'}">${p.api_key_configured ? '✓ ' + escapeHtml(p.key_hint || (en ? 'configured' : 'configurada')) : (en ? 'no key' : 'sin clave')}</span>
            </div>
            <div class="flex gap-1.5">
                <input type="password" id="api-key-input-${escapeHtml(p.id)}" autocomplete="off" spellcheck="false"
                    placeholder="${p.api_key_configured ? (en ? 'Paste a new key to replace it' : 'Pega una clave nueva para reemplazarla') : (en ? 'Paste your key here (' : 'Pega aquí tu clave (') + escapeHtml(p.env_key) + ')'}"
                    class="flex-1 min-w-0 px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-700 text-slate-100 font-mono text-[11px] outline-none focus:border-purple-500">
                <button type="button" onclick="saveApiKey(${jsArg(p.id)})" class="px-2.5 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-[11px]">${en ? 'Save' : 'Guardar'}</button>
                ${p.api_key_configured ? `
                    <button type="button" onclick="testApiProvider(${jsArg(p.id)})" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-[11px]">${en ? 'Test' : 'Probar'}</button>
                    <button type="button" onclick="removeApiKey(${jsArg(p.id)})" class="px-2 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-900/60 text-slate-400 hover:text-rose-200 text-[11px]" title="Quitar clave">✕</button>` : ''}
            </div>
            ${p.id === 'anthropic' ? `
                <div class="flex gap-1.5 items-center">
                    <input type="text" id="anthropic-workspace-input" value="${escapeHtml(p.workspace_id || '')}" autocomplete="off" spellcheck="false"
                        placeholder="${en ? 'Workspace ID (only if your key is not tied to a workspace): wrkspc_…' : 'ID de workspace (solo si tu clave no está asociada a uno): wrkspc_…'}"
                        class="flex-1 min-w-0 px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-slate-300 font-mono text-[10px] outline-none focus:border-purple-500">
                    <button type="button" onclick="saveAnthropicWorkspace()" class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold">${en ? 'Save' : 'Guardar'}</button>
                </div>` : ''}
            <p id="api-key-status-${escapeHtml(p.id)}" class="text-[10px] text-slate-400 min-h-[0.75rem]" aria-live="polite"></p>
        </div>`).join('');
}

async function refreshProviders(list) {
    if (list && appData.ai_engine) appData.ai_engine.available_providers = list;
    if (window.fetchTeamData) await window.fetchTeamData();
    renderApiKeys();
    const ai = appData.ai_engine || {};
    const provSel = document.getElementById('ai-provider-select');
    if (provSel) {
        const v = provSel.value;
        provSel.innerHTML = providers().map(p => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.icon || '')} ${escapeHtml(p.name)}${p.api_key_configured ? ' ✓' : ''}</option>`).join('');
        provSel.value = v || ai.active_provider;
    }
}

export async function saveApiKey(providerId) {
    const input = document.getElementById(`api-key-input-${providerId}`);
    const key = input?.value?.trim();
    if (!key) {
        showToast('Falta la clave', 'Pega tu clave de API en el campo', 'warning');
        return;
    }
    const data = await post('save_api_key', { provider: providerId, api_key: key });
    if (input) input.value = '';
    if (!data.success) {
        showToast('Clave no guardada', data.error || 'Error', 'error');
        return;
    }
    showToast('Clave guardada', 'Se guardó en el .env del servidor (no se muestra en el navegador). Probando conexión…', 'success');
    await refreshProviders(data.providers);
    testApiProvider(providerId);
}

export async function saveAnthropicWorkspace() {
    const ws = document.getElementById('anthropic-workspace-input')?.value?.trim() || '';
    const data = await post('save_anthropic_workspace', { workspace_id: ws });
    if (!data.success) {
        showToast('Workspace', data.error || 'Error', 'error');
        return;
    }
    showToast('Workspace', ws ? `Se usará ${ws} en cada petición a Claude` : 'Workspace eliminado', 'success', 3000);
    await refreshProviders(data.providers);
}

export async function removeApiKey(providerId) {
    if (!confirm('¿Quitar la clave de API de este proveedor? Los roles que lo usan pasarán a la IA local o al motor global.')) return;
    const data = await post('remove_api_key', { provider: providerId });
    if (!data.success) {
        showToast('Error', data.error || 'No se pudo quitar', 'error');
        return;
    }
    showToast('Clave eliminada', providerId, 'info');
    await refreshProviders(data.providers);
}

export async function testApiProvider(providerId) {
    const status = document.getElementById(`api-key-status-${providerId}`);
    if (status) status.textContent = 'Probando conexión…';
    const data = await post('test_provider', { provider: providerId });
    if (status) {
        status.textContent = data.success
            ? `✅ ${data.result.model} respondió en ${data.result.seconds}s: "${data.result.text}"`
            : `⚠️ ${data.error || 'No respondió'}`;
        status.className = `text-[10px] min-h-[0.75rem] ${data.success ? 'text-emerald-300' : 'text-amber-300'}`;
    }
}

// -------------------------------------------------------------
// Registro global para los atributos onclick del HTML
// -------------------------------------------------------------
Object.assign(window, {
    populateTaskSkillsSelector, handleCreateTask,
    openPromptModal, closePromptModal, copyPromptText, showTaskPrompt, showReviewPrompt,
    openTaskDetail, closeTaskDetail, saveTaskDetail, deleteTaskFromDetail, askDirectorAboutTask,
    openThemeModal, closeThemeModal, setDensity,
    openCommandPalette, handlePaletteSearch,
    openChangeWorkspaceModal, closeChangeWorkspaceModal, saveWorkspaceChange,
    openJurisdictionModal, closeJurisdictionModal, handleCountryPresetChange, autoDetectCountry,
    addCustomRegulation, quickAddRegulation, saveJurisdictionSettings,
    handleProviderChange, openAiEngineModal, renderApiKeys, saveApiKey, saveAnthropicWorkspace, removeApiKey, testApiProvider
});
