/**
 * AgileForge v2 - Kanban Scrum Board & Task Management
 * Renderizado del tablero con filtros combinables, lock de tareas, prompts y auditoría DoD.
 */

import { appData, apiFetch, escapeHtml, jsArg, localTime } from './state.js';
import { showToast } from './ui.js';
import { t as tr } from './i18n.js';
import { roleLabel, avatarFor, statusLabel, priorityLabel } from './roles.js';

const STATUS_ORDER = ['backlog', 'en_progreso', 'revision', 'completada'];

export function renderKanban() {
    const tasks = appData.tasks || [];
    const roles = appData.roles || [];

    const query = (document.getElementById('kanban-search')?.value || '').toLowerCase().trim();
    const roleFilter = document.getElementById('kanban-filter-role')?.value || 'all';
    const prioFilter = document.getElementById('kanban-filter-priority')?.value || 'all';
    const skillFilter = document.getElementById('kanban-filter-skill')?.value || 'all';

    // Poblar selector de roles en el filtro
    const roleSel = document.getElementById('kanban-filter-role');
    if (roleSel && roleSel.options.length !== roles.length + 1) {
        const keep = roleSel.value;
        roleSel.length = 1;
        setTimeout(() => { roleSel.value = [...roleSel.options].some(o => o.value === keep) ? keep : 'all'; }, 0);
        roles.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = roleLabel(r);
            opt.title = r.id;
            roleSel.appendChild(opt);
        });
    }

    // Poblar selector de skills en el filtro
    const skillSel = document.getElementById('kanban-filter-skill');
    if (skillSel && skillSel.options.length <= 1) {
        const skillsCatalog = appData.skills_catalog || [];
        skillsCatalog.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `⚡ ${s.name || s.id}`;
            skillSel.appendChild(opt);
        });
    }

    const cols = {
        backlog: document.getElementById('col-backlog'),
        en_progreso: document.getElementById('col-en_progreso'),
        revision: document.getElementById('col-revision'),
        completada: document.getElementById('col-completada')
    };

    // DocumentFragments para batching atómico y prevención de reflows
    const fragments = {
        backlog: document.createDocumentFragment(),
        en_progreso: document.createDocumentFragment(),
        revision: document.createDocumentFragment(),
        completada: document.createDocumentFragment()
    };

    const counts = { backlog: 0, en_progreso: 0, revision: 0, completada: 0 };

    // Prioridad: color e icono distintos por nivel (no depende solo del color)
    const priorityBadge = {
        critica: 'prio-badge prio-critica',
        alta:    'prio-badge prio-alta',
        media:   'prio-badge prio-media',
        baja:    'prio-badge prio-baja'
    };
    const priorityIcon = { critica: '▲▲', alta: '▲', media: '■', baja: '▼' };
    const globalModel = appData.ai_engine?.active_model || '';

    let renderedCount = 0;

    tasks.forEach(t => {
        // Filtros combinables
        if (roleFilter !== 'all' && t.assigned_to !== roleFilter) return;
        if (prioFilter !== 'all' && t.priority !== prioFilter) return;
        if (skillFilter !== 'all') {
            const hasSkill = (t.skills || []).includes(skillFilter);
            if (!hasSkill) return;
        }
        if (query) {
            const matchTitle = (t.title || '').toLowerCase().includes(query);
            const matchId = (t.id || '').toLowerCase().includes(query);
            const matchStory = (t.user_story || '').toLowerCase().includes(query);
            const matchSkills = (t.skills || []).some(s => s.toLowerCase().includes(query));
            if (!matchTitle && !matchId && !matchStory && !matchSkills) return;
        }

        renderedCount++;
        const pts = parseInt(t.story_points) || 1;
        if (counts[t.status] !== undefined) counts[t.status] += pts;

        const role = roles.find(r => r.id === t.assigned_to);
        const roleShort = roleLabel(role || t.assigned_to);
        const prevStatus = STATUS_ORDER[STATUS_ORDER.indexOf(t.status) - 1];
        const nextStatus = STATUS_ORDER[STATUS_ORDER.indexOf(t.status) + 1];
        const backLabel = prevStatus ? `${tr('move_to', 'Mover a')} ${statusLabel(prevStatus)}` : tr('move_none_back', 'No hay estado anterior');
        const fwdLabel = nextStatus ? `${tr('move_to', 'Mover a')} ${statusLabel(nextStatus)}` : tr('move_none_fwd', 'No hay estado siguiente');
        const isLegal = t.assigned_to === 'legal_counsel';
        const aiModel = t.ai_model || role?.ai_model || globalModel || '';
        // El chip de modelo solo aporta cuando esta historia NO usa el motor global
        const showModelChip = !!aiModel && aiModel !== globalModel;
        const isLocked = !!t.locked_by;
        const isQaApproved = (t.status === 'revision' && !!(t.review_evidence?.qa_approved));
        const reviewDuration = t.review_evidence?.qa_approved?.review_duration_seconds ?? t.review_evidence?.review_duration_seconds;
        const isQuickReview = (reviewDuration !== undefined && reviewDuration !== null && Number(reviewDuration) < 300);

        const taskSkills = t.skills || role?.assigned_skills?.slice(0, 2) || [];

        const card = document.createElement('div');
        card.className = `kanban-card p-3.5 rounded-2xl bg-slate-950/90 border ${isLegal ? 'border-amber-500/30' : (isLocked ? 'border-indigo-500/40' : (isQaApproved ? 'border-emerald-500/40' : 'border-slate-800'))} hover:border-slate-600 transition shadow-sm space-y-2.5`;
        card.innerHTML = `
            <div class="flex items-center justify-between gap-1 text-[10px]">
                <div class="flex items-center gap-1.5 font-mono">
                    <span class="font-bold text-indigo-400">${escapeHtml(t.id)}</span>
                    <span class="px-1.5 py-0.2 rounded bg-slate-900 border border-slate-800 font-bold text-slate-300">${pts} pts</span>
                </div>
                <div class="flex items-center gap-1">
                    ${isLocked ? `
                        <span class="px-1.5 py-0.2 rounded font-mono text-[9px] bg-indigo-950/80 text-indigo-300 border border-indigo-500/40 flex items-center gap-0.5" title="${escapeHtml(tr('lock_by', 'Tomada por'))} ${escapeHtml(t.locked_by)}">
                            <span aria-hidden="true">🔒</span> ${escapeHtml(roleLabel(t.locked_by))}
                        </span>
                    ` : ''}
                    <span class="${priorityBadge[t.priority] || priorityBadge.media}" title="${escapeHtml(tr('priority_lbl', 'Prioridad'))}: ${escapeHtml(priorityLabel(t.priority))}">
                        <span aria-hidden="true">${priorityIcon[t.priority] || priorityIcon.media}</span> ${escapeHtml(priorityLabel(t.priority))}
                    </span>
                </div>
            </div>

            <h5 class="kanban-title font-bold text-white text-xs leading-snug cursor-pointer hover:text-indigo-300 transition" data-action="open-task-detail" data-task-id="${escapeHtml(t.id)}">
                ${escapeHtml(t.title)}
            </h5>

            ${isQuickReview ? `
                <div class="quick-review-note" title="${escapeHtml(tr('quick_review_tip', 'QA aprobó muy pronto tras la entrega: revísala con cuidado antes de firmar'))} (${escapeHtml(String(reviewDuration))} s)">
                    <span aria-hidden="true">⚡</span> ${escapeHtml(tr('quick_review', 'Revisión rápida'))}
                </div>
            ` : ''}

            <!-- Insignia y Acción de Firma Humana -->
            ${isQaApproved ? `
                <div class="space-y-1.5 pt-1">
                    <div class="qa-signoff-banner">
                        <span class="flex items-center gap-1"><span aria-hidden="true">🛡️</span> ${escapeHtml(tr('qa_signoff_pending', 'Aprobada por QA · falta tu firma'))}</span>
                    </div>
                    <button data-action="sign-and-complete" data-task-id="${escapeHtml(t.id)}" class="btn-sign" title="Aprobar firma humana y marcar completada">
                        <span aria-hidden="true">✍️</span> ${escapeHtml(tr('sign_complete', 'Firmar y completar'))}
                    </button>
                </div>
            ` : ''}

            <!-- Motor IA y Agente Asignado -->
            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-900">
                <span class="flex items-center gap-1.5 font-medium text-slate-300" title="${escapeHtml(t.assigned_to || '')}">
                    ${avatarFor(role || t.assigned_to, 24)} <span>${escapeHtml(roleShort)}</span>
                </span>
                ${showModelChip ? `
                <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-purple-950/60 border border-purple-500/20 text-purple-300" title="${escapeHtml(tr('model_chip_tip', 'Esta historia usa un modelo distinto del motor global'))}">
                    <span aria-hidden="true">🤖</span> ${escapeHtml(aiModel)}
                </span>` : ''}
            </div>

            <!-- Chips de Skills Requeridas -->
            ${taskSkills.length > 0 ? `
                <div class="flex flex-wrap gap-1 pt-0.5">
                    ${taskSkills.slice(0, 3).map(s => `
                        <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-slate-900 text-slate-400 border border-slate-800 cursor-pointer hover:border-indigo-500/40" onclick="openSkillDetail(${jsArg(s)})" title="Ver ficha técnica de ${escapeHtml(s)}">
                            ⚡ ${escapeHtml(s)}
                        </span>
                    `).join('')}
                </div>
            ` : ''}

            <!-- Botones de Acción Scrum -->
            <div class="pt-2 border-t border-slate-900 flex items-center justify-between gap-1 text-xs">
                <div class="flex items-center gap-1">
                    <button data-action="move-task-backward" data-task-id="${escapeHtml(t.id)}" data-task-status="${escapeHtml(t.status)}" title="${escapeHtml(backLabel)}" aria-label="${escapeHtml(backLabel)}" ${prevStatus ? '' : 'disabled'} class="card-move-btn rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white transition">
                        <span aria-hidden="true">◀</span>
                    </button>
                    <button data-action="move-task-forward" data-task-id="${escapeHtml(t.id)}" data-task-status="${escapeHtml(t.status)}" title="${escapeHtml(fwdLabel)}" aria-label="${escapeHtml(fwdLabel)}" ${nextStatus ? '' : 'disabled'} class="card-move-btn rounded-lg bg-slate-900 hover:bg-slate-800 text-indigo-400 hover:text-indigo-300 font-bold transition">
                        <span aria-hidden="true">▶</span>
                    </button>
                </div>

                <div class="flex items-center gap-1">
                    <button data-action="show-task-prompt" data-task-id="${escapeHtml(t.id)}" title="Ver Prompt de la Misión" class="px-2 py-1 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 font-bold text-[10px] border border-indigo-500/30 transition flex items-center gap-1">
                        <span>📋</span> Prompt
                    </button>
                    <button data-action="request-task-review" data-task-id="${escapeHtml(t.id)}" title="Auditar DoD" class="px-2 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 text-[10px] border border-slate-800 transition">
                        <span>✓</span> DoD
                    </button>
                </div>
            </div>
        `;

        if (fragments[t.status]) fragments[t.status].appendChild(card);
    });

    // Inyección atómica al DOM (previene reflows múltiples y parpadeos)
    Object.keys(cols).forEach(status => {
        const col = cols[status];
        if (!col) return;
        if (!fragments[status].childNodes.length) {
            const empty = document.createElement('p');
            empty.className = 'kanban-empty';
            empty.textContent = tr('col_empty', 'Nada aquí');
            fragments[status].appendChild(empty);
        }
        col.replaceChildren(fragments[status]);
    });

    // Actualizar contador de historias filtradas
    const countBadge = document.getElementById('kanban-filter-count');
    if (countBadge) {
        countBadge.textContent = `${renderedCount} de ${tasks.length} historias`;
    }

    // Actualizar contadores de story points por columna
    Object.entries(counts).forEach(([status, total]) => {
        const el = document.getElementById(`col-count-${status}`);
        if (el) el.textContent = `${total} pts`;
    });

    if (window.renderSprintBanner) window.renderSprintBanner();
    renderActivityFeed();
}

export function renderActivityFeed() {
    const container = document.getElementById('mcp-activity-feed');
    if (!container) return;

    const events = appData.recent_events || [];
    if (events.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-slate-500 text-xs">
                <span>Esperando intervenciones de agentes IA vía MCP o panel web...</span>
            </div>
        `;
        return;
    }

    const eventIcons = {
        claimed: '🔒',
        submitted_for_review: '📤',
        task_reviewed: '🔍',
        qa_approved: '🛡️',
        task_created: '📋',
        status_change: '⚙️',
        comment_added: '💬'
    };

    container.innerHTML = events.map(e => {
        const icon = eventIcons[e.event_type] || '⚡';
        const clientBadge = e.client ? `<span class="px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 text-[9px] font-mono text-indigo-300">${escapeHtml(e.client)}</span>` : '';
        // Quién: el rol con su nombre corto; sin rol, quien actuó fue una persona desde el panel o un cliente externo
        const who = e.role_key
            ? `${avatarFor(e.role_key, 24)} <span class="font-bold text-slate-200" title="${escapeHtml(e.role_key)}">${escapeHtml(roleLabel(e.role_key))}</span>`
            : `<span class="font-bold text-slate-200">${escapeHtml(e.client === 'web' ? tr('feed_you', 'Tú (panel)') : tr('feed_agent', 'Un agente'))}</span>`;
        const taskTitle = e.task_title ? `<span class="text-slate-300 font-semibold">[${escapeHtml(e.task_id)}] ${escapeHtml(e.task_title)}</span>` : `<span class="text-indigo-400 font-mono">${escapeHtml(e.task_id || '')}</span>`;
        // created_at viene en UTC (SQLite): se muestra en la hora local
        const time = escapeHtml(localTime(e.created_at));

        const decision = e.payload?.decision;
        let desc = '';
        if (e.event_type === 'claimed') desc = tr('ev_claimed', 'tomó la historia');
        else if (e.event_type === 'submitted_for_review') desc = tr('ev_submitted', 'la entregó a revisión');
        else if (e.event_type === 'qa_approved') desc = tr('ev_qa_approved', 'la aprobó; falta tu firma');
        else if (e.event_type === 'qa_approval_revoked') desc = tr('ev_qa_revoked', 'retiró la aprobación de QA');
        else if (e.event_type === 'task_reviewed') desc = decision === 'approve' ? tr('ev_review_ok', 'la aprobó') : tr('ev_review_changes', 'pidió cambios');
        else if (e.event_type === 'status_change') desc = `${tr('ev_moved', 'la pasó a')} ${escapeHtml(statusLabel(e.payload?.status))}`;
        else if (e.event_type === 'task_created') desc = tr('ev_created', 'creó la historia');
        else if (e.event_type === 'comment_added') desc = tr('ev_comment', 'dejó un comentario');
        else desc = escapeHtml(String(e.event_type || '').replace(/_/g, ' '));

        return `
            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-950/70 border border-slate-900 hover:border-slate-800 transition">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm" aria-hidden="true">${icon}</span>
                    ${who}
                    <span class="text-slate-400">${desc}</span>
                    ${taskTitle}
                    ${clientBadge}
                </div>
                <span class="text-[10px] font-mono text-slate-500 whitespace-nowrap ml-2">${time}</span>
            </div>
        `;
    }).join('');
}

export async function updateTaskStatus(taskId, newStatus) {
    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_task_status',
                id: taskId,
                task_id: taskId,
                status: newStatus,
                client: 'web'
            })
        });
        const data = await res.json();
        if (data.success) {
            if (window.fetchTeamData) await window.fetchTeamData();
            return true;
        }
        showToast('Error', data.error || 'No se pudo actualizar el estado', 'error');
    } catch (err) {
        showToast('Error', 'Fallo al actualizar estado de la tarea', 'error');
    }
    return false;
}

export async function moveTaskForward(taskId, currentStatus) {
    const flow = { backlog: 'en_progreso', en_progreso: 'revision', revision: 'completada' };
    const next = flow[currentStatus];
    // El aviso de éxito solo aparece si el servidor aceptó el cambio (antes salía también tras un error)
    if (next && await updateTaskStatus(taskId, next)) {
        showToast('Estado Avanzado', `Historia movida a ${next.replace('_', ' ')}`, 'success', 2000);
    }
}

export async function moveTaskBackward(taskId, currentStatus) {
    const flow = { completada: 'revision', revision: 'en_progreso', en_progreso: 'backlog' };
    const prev = flow[currentStatus];
    if (prev && await updateTaskStatus(taskId, prev)) {
        showToast('Estado Modificado', `Historia movida a ${prev.replace('_', ' ')}`, 'info', 2000);
    }
}

export function openNewTaskModal() {
    const modal = document.getElementById('new-task-modal');
    if (!modal) return;
    if (window.populateRoleSelects) window.populateRoleSelects();
    const roleSel = document.getElementById('task-role');
    if (window.populateTaskSkillsSelector) window.populateTaskSkillsSelector(roleSel?.value);
    modal.classList.remove('hidden');
    setTimeout(() => document.getElementById('task-title')?.focus(), 30);
}

export function closeNewTaskModal() {
    const modal = document.getElementById('new-task-modal');
    if (modal) modal.classList.add('hidden');
}


