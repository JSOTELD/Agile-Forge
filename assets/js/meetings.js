/**
 * AgileForge v2 - Multi-Agent Meetings & Decision Councils
 * Convocatoria de brainstorming, actas ADR y conversión de acuerdos al Backlog.
 */

import { appData, apiFetch, escapeHtml } from './state.js';
import { showToast } from './ui.js';

export function renderMeetingsBadge() {
    const navBadge = document.getElementById('nav-meetings-count');
    const meetings = appData.meetings || [];
    if (navBadge) navBadge.textContent = `${meetings.length}`;
}

export function renderMeetingsView() {
    const meetings = appData.meetings || [];
    renderMeetingsBadge();

    const stage = document.getElementById('meeting-stage-container');
    if (stage && meetings.length > 0) {
        renderActiveMeeting(meetings[0]);
    }

    renderMeetingsHistory();
    populateMeetingParticipantsSelector();
}

export function renderActiveMeeting(meeting) {
    const stage = document.getElementById('meeting-stage-container');
    if (!stage || !meeting) return;

    const isBrainstorm = meeting.type === 'brainstorming';
    const typeLabel = isBrainstorm ? '💡 Brainstorming & Innovación' : '🏛️ Consejo ADR (Decisión Técnica)';
    const typeBadgeClass = isBrainstorm
        ? 'bg-pink-950/70 text-pink-300 border-pink-500/30'
        : 'bg-indigo-950/70 text-indigo-300 border-indigo-500/30';

    const turns = meeting.turns || [];
    const actionItems = meeting.action_items || [];
    const isConverted = meeting.converted === true;

    stage.innerHTML = `
        <div class="glass-card rounded-3xl p-6 border ${isBrainstorm ? 'border-pink-500/30' : 'border-indigo-500/30'} space-y-5 shadow-2xl">
            <!-- Header de la Sesión -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800 pb-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold font-mono border ${typeBadgeClass}">
                            ${typeLabel}
                        </span>
                        <span class="text-xs font-mono text-slate-400 font-bold">${escapeHtml(meeting.id)}</span>
                        <span class="text-slate-500 text-xs">· ${escapeHtml(meeting.created_at || 'Hoy')}</span>
                    </div>
                    <h3 class="font-extrabold text-lg text-white">${escapeHtml(meeting.title)}</h3>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="copyMeetingMinutes('${escapeHtml(meeting.id)}')" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-700 text-xs font-bold transition flex items-center gap-1.5">
                        <span>📋</span> Copiar Acta
                    </button>
                    ${!isConverted ? `
                        <button onclick="convertMeetingToTasks('${escapeHtml(meeting.id)}')" class="px-4 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow transition flex items-center gap-1.5 whitespace-nowrap">
                            <span>📥</span> Convertir al Backlog
                        </button>
                    ` : `
                        <span class="text-xs font-mono font-bold px-3 py-1.5 rounded-xl bg-emerald-950/80 text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                            ✓ Convertida al Backlog
                        </span>
                    `}
                </div>
            </div>

            <!-- Participantes en la Mesa Redonda -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
                <span class="text-slate-400 font-bold uppercase text-[10px] mr-1">Mesa Redonda:</span>
                ${(meeting.participants || []).map(pId => {
                    const r = (appData.roles || []).find(x => x.id === pId);
                    return `
                        <span class="px-2.5 py-1 rounded-xl bg-slate-950 border border-slate-800 text-slate-200 flex items-center gap-1.5 font-medium whitespace-nowrap">
                            <span>${escapeHtml(r ? r.avatar : '🤖')}</span>
                            <span>${escapeHtml(r ? r.name.split(' ')[0] : pId)}</span>
                            <span class="text-[9px] font-mono text-purple-300 bg-purple-950/60 px-1 py-0.2 rounded border border-purple-500/20">${escapeHtml((r?.ai_model || 'Claude').split(' ')[0])}</span>
                        </span>
                    `;
                }).join('')}
            </div>

            <!-- Intervenciones (Roundtable Dialogue) -->
            <div class="space-y-3 pt-2">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Debate e Intervenciones de los Agentes:</span>
                <div class="space-y-2.5">
                    ${turns.map(t => `
                        <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 space-y-2 hover:border-slate-700 transition">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">${escapeHtml(t.avatar)}</span>
                                    <div>
                                        <span class="font-bold text-white">${escapeHtml(t.name)}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">(${escapeHtml(t.alias)})</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-purple-950/70 border border-purple-500/30 text-purple-300">
                                        🤖 ${escapeHtml(t.ai_model)}
                                    </span>
                                    <span class="text-slate-500 font-mono text-[10px]">${escapeHtml(t.timestamp)}</span>
                                </div>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed pl-7 border-l-2 border-indigo-500/30">
                                ${escapeHtml(t.statement)}
                            </p>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Conclusiones y Acuerdos de la Reunión -->
            <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-950 via-indigo-950/30 to-slate-950 border border-indigo-500/30 space-y-3.5">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-xs text-indigo-300 uppercase tracking-wider flex items-center gap-1.5">
                        <span>📜</span> Acta de Acuerdos & Veredicto Consensuado
                    </h4>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-950/70 text-emerald-300 border border-emerald-500/30 font-bold">
                        Unánime
                    </span>
                </div>

                <p class="text-xs text-slate-200 leading-relaxed">
                    <strong>Resumen:</strong> ${escapeHtml(meeting.summary)}
                </p>

                <div class="p-3 rounded-xl bg-slate-900/90 border border-indigo-500/20 text-xs">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Resolución Adoptada:</span>
                    <span class="text-emerald-400 font-semibold">${escapeHtml(meeting.resolution)}</span>
                </div>

                <!-- Lista de Acciones / Historias acordadas -->
                <div class="space-y-2 pt-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Historias de Usuario Resultantes (${actionItems.length}):</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        ${actionItems.map(item => {
                            const r = (appData.roles || []).find(x => x.id === item.assigned_to);
                            return `
                                <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 space-y-1">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="font-bold text-indigo-400">${item.story_points || 3} pts</span>
                                        <span class="text-slate-400">${escapeHtml(r ? r.avatar : '🤖')} ${escapeHtml(r ? r.name.split(' ')[0] : item.assigned_to)}</span>
                                    </div>
                                    <strong class="text-white block text-xs leading-snug">${escapeHtml(item.title)}</strong>
                                    <p class="text-[10px] text-slate-400 leading-tight">${escapeHtml(item.dod || '')}</p>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
}

export function renderMeetingsHistory() {
    const container = document.getElementById('meetings-history-list');
    const countEl = document.getElementById('meetings-history-count');
    const meetings = appData.meetings || [];
    if (!container) return;

    if (countEl) countEl.textContent = `${meetings.length} actas`;

    if (meetings.length === 0) {
        container.innerHTML = `<p class="text-xs text-slate-500 italic p-3 text-center">No hay actas archivadas aún.</p>`;
        return;
    }

    container.innerHTML = meetings.map(m => {
        const isBrainstorm = m.type === 'brainstorming';
        const isConverted = m.converted === true;
        return `
            <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 hover:border-slate-700 flex items-center justify-between gap-3 text-xs transition">
                <div class="flex items-center gap-3 truncate">
                    <span class="text-xl">${isBrainstorm ? '💡' : '🏛️'}</span>
                    <div class="truncate">
                        <div class="flex items-center gap-2">
                            <strong class="text-white truncate">${escapeHtml(m.title)}</strong>
                            <span class="text-[9px] font-mono px-1.5 py-0.2 rounded ${isConverted ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/20' : 'bg-slate-900 text-slate-400 border border-slate-800'}">
                                ${isConverted ? '✓ En Backlog' : 'Pendiente'}
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-500 font-mono">${escapeHtml(m.id)} · ${escapeHtml(m.created_at || '')} · ${(m.participants || []).length} agentes</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="viewPastMeeting('${escapeHtml(m.id)}')" class="px-3 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-indigo-300 font-bold text-xs transition">
                        Ver Acta
                    </button>
                    ${!isConverted ? `
                        <button onclick="convertMeetingToTasks('${escapeHtml(m.id)}')" title="Convertir a tareas en el Backlog" class="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow transition">
                            + Backlog
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

export function viewPastMeeting(meetingId) {
    const m = (appData.meetings || []).find(x => x.id === meetingId);
    if (m) {
        renderActiveMeeting(m);
        document.getElementById('meeting-stage-container')?.scrollIntoView({ behavior: 'smooth' });
    }
}

export function toggleMeetingComposer() {
    const composer = document.getElementById('meeting-composer');
    if (!composer) return;
    composer.classList.toggle('hidden');
    populateMeetingParticipantsSelector();
}

export function populateMeetingParticipantsSelector() {
    const container = document.getElementById('meet-participants-selector');
    if (!container) return;

    const roles = appData.roles || [];
    container.innerHTML = roles.map(r => `
        <label class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer text-xs select-none hover:border-slate-700">
            <input type="checkbox" value="${escapeHtml(r.id)}" checked class="rounded border-slate-700 text-indigo-600 focus:ring-0">
            <span>${escapeHtml(r.avatar)}</span>
            <span class="font-medium text-slate-200">${escapeHtml(r.name.split(' ')[0])}</span>
        </label>
    `).join('');
}

export async function launchPresetMeeting(presetKey) {
    const presets = {
        brainstorming_ux: {
            meeting_type: 'brainstorming',
            topic: 'Innovación en micro-momentos visuales y cotizador interactivo',
            participants: ['creative_director', 'frontend_master', 'project_manager']
        },
        adr_architecture: {
            meeting_type: 'decision',
            topic: 'Arquitectura de persistencia transaccional con Patrón Repositorio y decoupling',
            participants: ['lead_architect', 'backend_architect', 'qa_security_guard']
        },
        legal_compliance: {
            meeting_type: 'decision',
            topic: 'Blindaje de términos de custodia canina, exención médica y política PII',
            participants: ['legal_counsel', 'project_manager', 'qa_security_guard']
        },
        full_squad: {
            meeting_type: 'brainstorming',
            topic: 'Alineación estratégica integral de Sprint y definición de excelencia en ingeniería',
            participants: ['project_manager', 'creative_director', 'lead_architect', 'frontend_master', 'backend_architect', 'qa_security_guard', 'legal_counsel']
        }
    };

    const p = presets[presetKey];
    if (!p) return;

    showToast('Convocando Reunión', `Iniciando debate con ${p.participants.length} agentes...`, 'magic', 2500);

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'run_agent_meeting',
                meeting_type: p.meeting_type,
                topic: p.topic,
                participants: p.participants
            })
        });
        const data = await res.json();
        if (data.success) {
            if (window.fetchTeamData) await window.fetchTeamData();
            renderActiveMeeting(data.meeting);
            showToast('Reunión Finalizada', `Acuerdos alcanzados en ${data.meeting.id}`, 'success', 3000);
        }
    } catch (err) {
        showToast('Error', 'No se pudo llevar a cabo la reunión', 'error');
    }
}

export async function submitCustomMeeting() {
    const meetingType = document.getElementById('meet-type-select').value;
    const topic = document.getElementById('meet-topic-input').value.trim();
    const checked = Array.from(document.querySelectorAll('#meet-participants-selector input:checked')).map(i => i.value);

    if (!topic) {
        showToast('Atención', 'Por favor ingresa el tema u objetivo de la reunión', 'warning');
        return;
    }

    if (checked.length < 2) {
        showToast('Atención', 'Debes seleccionar al menos 2 agentes participantes', 'warning');
        return;
    }

    showToast('Convocando Mesa', 'Generando debate colaborativo entre los agentes...', 'magic', 3000);

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'run_agent_meeting',
                meeting_type: meetingType,
                topic: topic,
                participants: checked
            })
        });
        const data = await res.json();
        if (data.success) {
            toggleMeetingComposer();
            if (window.fetchTeamData) await window.fetchTeamData();
            renderActiveMeeting(data.meeting);
            showToast('Debate Concluido', `Se emitieron ${data.meeting.action_items.length} acuerdos formales`, 'success');
        }
    } catch (err) {
        showToast('Error', 'Error al ejecutar la reunión', 'error');
    }
}

export async function convertMeetingToTasks(meetingId) {
    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'convert_meeting_to_tasks',
                meeting_id: meetingId
            })
        });
        const data = await res.json();
        if (data.success) {
            if (window.fetchTeamData) await window.fetchTeamData();
            showToast('¡Historias Creadas!', data.message, 'success', 3500);
            const m = (appData.meetings || []).find(x => x.id === meetingId);
            if (m) renderActiveMeeting(m);
        }
    } catch (err) {
        showToast('Error', 'No se pudieron convertir los acuerdos en historias', 'error');
    }
}

export function copyMeetingMinutes(meetingId) {
    const m = (appData.meetings || []).find(x => x.id === meetingId);
    if (!m) return;

    let md = `# Acta de Reunión: ${m.title}\n`;
    md += `**ID:** ${m.id} | **Tipo:** ${m.type} | **Fecha:** ${m.created_at || 'Hoy'}\n\n`;
    md += `## 1. Mesa Redonda de Participantes\n`;
    (m.participants || []).forEach(pId => {
        const r = (appData.roles || []).find(x => x.id === pId);
        md += `- ${r ? r.name : pId} (${r ? r.alias : ''}) [Motor IA: ${r?.ai_model || 'Default'}]\n`;
    });

    md += `\n## 2. Debate e Intervenciones\n`;
    (m.turns || []).forEach(t => {
        md += `### ${t.name} (${t.alias}) - ${t.timestamp} [${t.ai_model}]\n`;
        md += `${t.statement}\n\n`;
    });

    md += `## 3. Resumen y Acuerdos Consensuados\n`;
    md += `${m.summary}\n\n`;
    md += `**Resolución:** ${m.resolution}\n\n`;

    md += `## 4. Historias de Usuario / Acciones Acordadas\n`;
    (m.action_items || []).forEach((item, idx) => {
        md += `${idx + 1}. **${item.title}** (${item.story_points || 3} pts) - Asignado a: @${item.assigned_to}\n`;
        md += `   DoD: ${item.dod || 'N/A'}\n`;
    });

    navigator.clipboard.writeText(md).then(() => {
        showToast('Acta Copiada', 'Formato Markdown listo para compartir', 'success', 2500);
    }).catch(() => {
        showToast('Error', 'No se pudo acceder al portapapeles', 'error');
    });
}

