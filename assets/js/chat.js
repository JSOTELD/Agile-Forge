/**
 * AgileForge v2 - Agile Chat & Consultation
 * Drawer de mensajería y consulta técnica directa con agentes especializados.
 */

import { appData, apiFetch, escapeHtml } from './state.js';
import { showToast } from './ui.js';
import { richText, actionChips, isPluginMode } from './director.js';
import { t } from './i18n.js';

export function toggleChatDrawer() {
    const drawer = document.getElementById('chat-drawer');
    if (!drawer) return;
    drawer.classList.toggle('translate-x-full');
}

export function quickChatPrompt(text) {
    const input = document.getElementById('chat-input');
    if (input) {
        input.value = text;
        input.focus();
    }
}

export function renderChat() {
    const container = document.getElementById('chat-messages');
    if (!container || !appData.chat_messages) return;

    container.innerHTML = appData.chat_messages.map(m => {
        const isUser = m.sender_role === 'user';
        const meta = m.meta || {};
        const isError = !isUser && (meta.error || String(m.text || '').startsWith('⚠️'));
        const target = isUser && meta.target ? (appData.roles || []).find(r => r.id === meta.target) : null;
        const consulted = !isUser && meta.consulted_by
            ? `<div class="text-[10px] text-amber-300/90 mb-1">👑 Consulta del Director: ${escapeHtml(String(meta.question || '').slice(0, 220))}</div>` : '';
        const aiModelTag = (!isUser && m.ai_model)
            ? `<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-slate-900 border border-purple-500/30 text-purple-300" title="Respuesta generada o gestionada por este modelo">🤖 ${escapeHtml(m.ai_model)}</span>`
            : '';

        return `
            <div class="flex flex-col ${isUser ? 'items-end' : 'items-start'} space-y-1">
                <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                    <span>${escapeHtml(m.avatar || (isUser ? '👤' : '🤖'))}</span>
                    <span class="font-bold ${isUser ? 'text-indigo-400' : 'text-slate-300'}">${escapeHtml(m.sender_name)}</span>
                    ${target ? `<span class="text-slate-500">→ ${escapeHtml(target.avatar)} ${escapeHtml(target.name.split(' ')[0])}</span>` : ''}
                    ${aiModelTag}
                    <span class="text-slate-500 font-mono">${escapeHtml(m.timestamp || '')}</span>
                </div>
                <div class="max-w-[88%] p-3 rounded-2xl text-xs leading-relaxed ${isUser ? 'bg-indigo-600 text-white rounded-tr-none' : (isError ? 'bg-amber-950/30 border border-amber-500/30 text-amber-100 rounded-tl-none' : 'bg-slate-950 border border-slate-800 text-slate-200 rounded-tl-none')}">
                    ${consulted}${isUser ? escapeHtml(m.text) : richText(m.text)}
                    ${!isUser ? actionChips(meta.actions) : ''}
                </div>
            </div>
        `;
    }).join('');

    container.scrollTop = container.scrollHeight;
}

export async function handleSendChat(e) {
    if (e && e.preventDefault) e.preventDefault();
    const input = document.getElementById('chat-input');
    const targetRoleSel = document.getElementById('chat-target-role');
    const targetRole = targetRoleSel?.value || 'director';
    const text = input?.value?.trim() || '';
    if (!text) return;

    // Modo plugin: sin motor en la web el mensaje neutro sustituye al error «sin motor»
    const targetRoleObj = (appData.roles || []).find(r => r.id === targetRole);
    if (isPluginMode() && !(targetRoleObj?.engine?.ready)) {
        showToast(t('plugin_mode_title'), t('plugin_mode_msg'), 'info', 7000);
        return;
    }

    if (input) input.value = '';

    // Indicador de "escribiendo…" (las respuestas de la IA local pueden tardar varios segundos)
    const container = document.getElementById('chat-messages');
    const submitBtn = document.querySelector('#chat-form button[type="submit"]');
    const targetObj = (appData.roles || []).find(r => r.id === targetRole);
    const engine = targetObj?.engine || {};
    const usingLocal = engine.kind === 'local';
    const pending = document.createElement('div');
    pending.className = 'flex flex-col items-start space-y-1';
    pending.innerHTML = `
        <div class="flex flex-col items-end space-y-1 self-end w-full">
            <div class="max-w-[88%] p-3 rounded-2xl text-xs leading-relaxed bg-indigo-600 text-white rounded-tr-none">${escapeHtml(text)}</div>
        </div>
        <div class="max-w-[88%] p-3 rounded-2xl text-xs bg-slate-950 border border-slate-800 text-slate-400 rounded-tl-none animate-pulse">
            ${targetRole === 'director' ? '👑 El Director está decidiendo y actuando sobre el tablero…' : (engine.ready ? escapeHtml(engine.label) + (usingLocal ? ' está pensando en tu PC…' : ' está respondiendo…') : 'Enviando…')}
        </div>`;
    if (container) {
        container.appendChild(pending);
        container.scrollTop = container.scrollHeight;
    }
    if (submitBtn) submitBtn.disabled = true;

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send_chat',
                target_role: targetRole,
                message: text
            })
        });
        let data;
        try {
            data = await res.json();
        } catch (parseErr) {
            data = { success: false, error: usingLocal
                ? `La IA local tardó demasiado en responder (HTTP ${res.status}). La primera respuesta tarda más mientras el modelo se carga; vuelve a intentarlo.`
                : `El servidor respondió HTTP ${res.status}.` };
            if (input && !input.value) input.value = text;
        }
        if (window.fetchTeamData) await window.fetchTeamData();
        if (!data.success) {
            if (isPluginMode()) showToast(t('plugin_mode_title'), t('plugin_mode_msg'), 'info', 7000);
            else showToast('El agente no pudo responder', data.error || 'No se pudo enviar la consulta', 'error', 6000);
        }
    } catch (err) {
        showToast('Error', 'Fallo al comunicar con el chat', 'error');
    } finally {
        pending.remove();
        if (submitBtn) submitBtn.disabled = false;
    }
}

