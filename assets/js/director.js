/**
 * AgileForge v2 - Director General (jefe máximo)
 * Panel del tablero para darle instrucciones al Director: decide, delega en los agentes y actualiza el tablero.
 */

import { appData, apiFetch, escapeHtml } from './state.js';
import { showToast, switchMainTab } from './ui.js';
import { getLanguage, t } from './i18n.js';
import { avatarFor } from './roles.js';

const TOOL_ICONS = {
    crear_historia: '📋', actualizar_historia: '✏️', comentar_historia: '💬', consultar_agente: '🗣️',
    registrar_decision: '🏛️', ajustar_sprint: '🏃', ver_tablero: '👀', ver_historia: '🔎'
};

let busy = false;

/** Texto con formato mínimo y seguro: **negrita**, listas con "-" y saltos de línea. */
export function richText(text) {
    let html = escapeHtml(text || '');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong class="text-white">$1</strong>');
    html = html.replace(/^#{1,4}\s*(.+)$/gm, '<strong class="text-white">$1</strong>');
    html = html.replace(/^\s*[-•]\s+(.+)$/gm, '<span class="block pl-3 -indent-2">• $1</span>');
    return html.replace(/\n/g, '<br>');
}

export function actionChips(actions) {
    if (!Array.isArray(actions) || !actions.length) return '';
    return `<div class="flex flex-wrap gap-1.5 mt-2">${actions.map(a => `
        <span class="text-[10px] px-2 py-0.5 rounded-lg border ${a.ok ? 'bg-emerald-950/40 border-emerald-500/30 text-emerald-200' : 'bg-rose-950/40 border-rose-500/30 text-rose-200'}" title="${escapeHtml(a.tool)}">
            ${TOOL_ICONS[a.tool] || '⚙️'} ${escapeHtml(a.summary)}
        </span>`).join('')}</div>`;
}

/** Modo plugin: ningún rol tiene motor listo y la IA local está apagada (el cerebro es Claude Code). */
export function isPluginMode() {
    const roles = appData.roles || [];
    return roles.length > 0 && roles.every(r => !(r.engine && r.engine.ready)) && !(appData.local_ai && appData.local_ai.enabled);
}

function director() {
    return (appData.roles || []).find(r => r.id === 'director');
}

export function renderDirectorPanel() {
    const panel = document.getElementById('director-panel');
    if (!panel) return;
    const d = director();
    panel.classList.toggle('hidden', !d);
    if (!d) return;

    const badge = document.getElementById('director-engine-badge');
    if (badge) {
        const e = d.engine || {};
        const plugin = isPluginMode();
        badge.textContent = e.ready ? `🤖 ${e.label}` : (plugin ? t('plugin_engine_badge') : '⚠️ sin motor de IA');
        badge.title = e.ready
            ? (e.source === 'role' ? 'Motor asignado al Director' : e.source === 'local' ? 'Usa la IA local (el motor asignado no tiene clave)' : 'Usa el motor global') + (e.reason ? ` — ${e.reason}` : '')
            : (plugin ? t('plugin_engine_hint') : (e.reason || 'Configura una clave de API en Motor IA o activa la IA local'));
        badge.className = `text-[10px] font-mono px-2 py-0.5 rounded-full border cursor-pointer ${e.ready ? 'bg-slate-950 border-amber-500/40 text-amber-200' : (plugin ? 'bg-slate-900 border-slate-600 text-slate-300' : 'bg-rose-950/40 border-rose-500/40 text-rose-200')}`;
    }
    let note = document.getElementById('director-plugin-note');
    if (isPluginMode()) {
        if (!note) {
            note = document.createElement('p');
            note.id = 'director-plugin-note';
            note.className = 'text-[11px] text-sky-200';
            const form = document.getElementById('director-form');
            if (form && form.parentNode) form.parentNode.insertBefore(note, form);
        }
        note.textContent = t('director_plugin_note');
    }
    // En modo plugin el panel web no puede ejecutar instrucciones: los controles se ven deshabilitados
    const pluginNow = isPluginMode();
    panel.querySelectorAll('#director-input, #director-send-btn, .director-body button[onclick^="quickDirector"]').forEach(el => {
        // Solo se toca lo que deshabilitó el modo plugin (el botón también se deshabilita mientras envía)
        if (pluginNow) { el.disabled = true; el.dataset.pluginDisabled = '1'; }
        else if (el.dataset.pluginDisabled) { el.disabled = false; delete el.dataset.pluginDisabled; }
    });
    const slot = document.getElementById('director-avatar-slot');
    if (slot && !slot.dataset.ready) {
        slot.innerHTML = avatarFor(d, 28);
        slot.dataset.ready = '1';
    }
    // Plegado por defecto; se recuerda si la persona lo dejó abierto
    if (!panel.dataset.stateBound) {
        panel.dataset.stateBound = '1';
        try { panel.open = localStorage.getItem('af_director_open') === '1'; } catch (_) { /* almacenamiento no disponible */ }
        panel.addEventListener('toggle', () => {
            try { localStorage.setItem('af_director_open', panel.open ? '1' : '0'); } catch (_) { /* sin almacenamiento */ }
        });
    }
    if (!isPluginMode() && note) {
        note.remove();
    }

    if (busy) return;
    const out = document.getElementById('director-output');
    const msgs = appData.chat_messages || [];
    const last = [...msgs].reverse().find(m => m.sender_role === 'director');
    if (!out || !last) return;
    const idx = msgs.lastIndexOf(last);
    const asked = [...msgs.slice(0, idx)].reverse().find(m => m.sender_role === 'user' && m.meta?.target === 'director');
    out.classList.remove('hidden');
    out.innerHTML = `
        ${asked ? `<p class="text-[11px] text-slate-400 mb-1.5"><span class="text-slate-500">Tú:</span> ${escapeHtml(asked.text.slice(0, 300))}</p>` : ''}
        <div class="text-xs text-slate-200 leading-relaxed">${richText(last.text)}</div>
        ${actionChips(last.meta?.actions)}
        <p class="text-[10px] text-slate-500 font-mono mt-2">${escapeHtml(last.timestamp || '')}${last.ai_model ? ' · ' + escapeHtml(last.ai_model) : ''}${last.meta?.cost_usd ? ' · $' + Number(last.meta.cost_usd).toFixed(4) : ''}</p>`;
}

export async function sendDirectorInstruction(e) {
    if (e?.preventDefault) e.preventDefault();
    if (busy) return;
    const input = document.getElementById('director-input');
    const text = input?.value?.trim();
    if (!text) {
        input?.focus();
        return;
    }
    const d = director();
    if (d && d.engine && !d.engine.ready && isPluginMode()) {
        showToast('Claude Code', t('director_plugin_toast'), 'info', 7000);
        return;
    }
    if (d && d.engine && !d.engine.ready) {
        showToast('El Director no tiene motor', d.engine.reason || 'Agrega una clave de API en Motor IA o activa la IA local', 'warning', 6000);
        if (window.openAiEngineModal) window.openAiEngineModal();
        return;
    }

    busy = true;
    const btn = document.getElementById('director-send-btn');
    const out = document.getElementById('director-output');
    if (btn) btn.disabled = true;
    const started = Date.now();
    const local = d?.engine?.kind === 'local';
    const tick = () => {
        if (!out) return;
        const s = Math.round((Date.now() - started) / 1000);
        out.classList.remove('hidden');
        out.innerHTML = `
            <p class="text-[11px] text-slate-400 mb-1.5"><span class="text-slate-500">Tú:</span> ${escapeHtml(text.slice(0, 300))}</p>
            <p class="text-xs text-amber-200 animate-pulse">👑 El Director está analizando el tablero y decidiendo… ${s}s</p>
            <p class="text-[10px] text-slate-500 mt-1">${local ? 'Con IA local puede tardar 1–3 minutos.' : 'Puede tardar hasta un minuto si consulta a otros agentes.'}</p>`;
    };
    tick();
    const timer = setInterval(tick, 1000);

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_chat', target_role: 'director', message: text })
        });
        let data;
        try { data = await res.json(); } catch (err) { data = { success: false, error: `El servidor respondió HTTP ${res.status} sin datos (¿se cortó por tiempo?).` }; }
        if (input && data.success) input.value = '';
        if (!data.success) showToast('El Director no pudo completar la instrucción', data.error || 'Error', 'error', 7000);
        const n = data.messages?.[1]?.meta?.actions?.filter(a => a.ok).length || 0;
        if (data.success && n) showToast('Instrucción ejecutada', `${n} acción(es) en el tablero`, 'magic', 3000);
    } catch (err) {
        showToast('Error de conexión', 'No se pudo hablar con el servidor', 'error');
    } finally {
        clearInterval(timer);
        busy = false;
        if (btn) btn.disabled = false;
        if (window.fetchTeamData) await window.fetchTeamData();
        renderDirectorPanel();
    }
}

export function openDirectorWith(text) {
    switchMainTab('kanban');
    const input = document.getElementById('director-input');
    if (input) {
        if (text) input.value = text;
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

const QUICK = {
    es: {
        status: 'Resume el estado del sprint: qué va bien, qué está bloqueado y qué decides priorizar.',
        backlog: 'Revisa el backlog, reasigna lo que esté mal asignado y ajusta prioridades según el objetivo del sprint.',
        unblock: '¿Qué historias están en revisión o en progreso sin avance? Decide los siguientes pasos y deja instrucciones en cada una.'
    },
    en: {
        status: 'Summarize the sprint status: what is going well, what is blocked and what you decide to prioritize.',
        backlog: 'Review the backlog, reassign anything that is assigned to the wrong role and adjust priorities to the sprint goal.',
        unblock: 'Which stories are in review or in progress without movement? Decide the next steps and leave instructions on each one.'
    }
};

export function quickDirector(key) {
    const input = document.getElementById('director-input');
    const lang = getLanguage() === 'en' ? 'en' : 'es';
    if (input) input.value = QUICK[lang][key] || key;
    sendDirectorInstruction();
}

document.addEventListener('keydown', (e) => {
    if (e.target?.id === 'director-input' && e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        sendDirectorInstruction();
    }
});

Object.assign(window, { sendDirectorInstruction, openDirectorWith, quickDirector, renderDirectorPanel });
