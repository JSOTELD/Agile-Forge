/**
 * AgileForge v2 - Agile Chat & Consultation
 * Drawer de mensajería y consulta técnica directa con agentes especializados.
 */

import { appData, apiFetch, escapeHtml } from './state.js';
import { showToast } from './ui.js';

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
        const aiModelTag = (!isUser && m.ai_model)
            ? `<span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-slate-900 border border-purple-500/30 text-purple-300" title="Respuesta generada o gestionada por este modelo">🤖 ${escapeHtml(m.ai_model)}</span>`
            : '';

        return `
            <div class="flex flex-col ${isUser ? 'items-end' : 'items-start'} space-y-1">
                <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                    <span>${escapeHtml(m.avatar || (isUser ? '👤' : '🤖'))}</span>
                    <span class="font-bold ${isUser ? 'text-indigo-400' : 'text-slate-300'}">${escapeHtml(m.sender_name)}</span>
                    ${aiModelTag}
                    <span class="text-slate-500 font-mono">${escapeHtml(m.timestamp || '')}</span>
                </div>
                <div class="max-w-[88%] p-3 rounded-2xl text-xs leading-relaxed ${isUser ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-slate-950 border border-slate-800 text-slate-200 rounded-tl-none'}">
                    ${escapeHtml(m.text)}
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
    const targetRole = targetRoleSel?.value || 'project_manager';
    const text = input?.value?.trim() || '';
    if (!text) return;

    if (input) input.value = '';

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
        const data = await res.json();
        if (data.success) {
            if (window.fetchTeamData) await window.fetchTeamData();
        } else {
            showToast('Error', data.error || 'No se pudo enviar la consulta', 'error');
        }
    } catch (err) {
        showToast('Error', 'Fallo al comunicar con el chat', 'error');
    }
}

