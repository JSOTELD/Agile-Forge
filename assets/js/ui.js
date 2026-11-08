/**
 * AgileForge v2 - UI & Notifications Manager
 * Notificaciones toast, navegación entre pestañas, temas y atajos de teclado.
 */

import { appData, currentTab, setCurrentTab, escapeHtml, apiFetch } from './state.js';

export function showToast(title, message, type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const toastId = 'toast-' + Date.now();
    toast.id = toastId;

    const colors = {
        success: 'border-emerald-500/40 bg-slate-900/95 text-emerald-300 shadow-emerald-950/40',
        error:   'border-rose-500/40 bg-slate-900/95 text-rose-300 shadow-rose-950/40',
        warning: 'border-amber-500/40 bg-slate-900/95 text-amber-300 shadow-amber-950/40',
        info:    'border-indigo-500/40 bg-slate-900/95 text-indigo-300 shadow-indigo-950/40',
        magic:   'border-purple-500/40 bg-slate-900/95 text-purple-300 shadow-purple-950/40'
    };

    const icons = {
        success: '✓',
        error:   '✕',
        warning: '⚠️',
        info:    'ℹ️',
        magic:   '⚡'
    };

    const colorClass = colors[type] || colors.info;
    const icon = icons[type] || 'ℹ️';

    toast.className = `border rounded-2xl p-4 shadow-xl backdrop-blur-md flex items-start gap-3 transition-all duration-300 transform translate-y-2 opacity-0 max-w-sm w-full ${colorClass}`;
    toast.innerHTML = `
        <span class="text-base font-bold select-none">${icon}</span>
        <div class="flex-1 min-w-0">
            <h5 class="text-xs font-bold text-white tracking-wide">${escapeHtml(title)}</h5>
            <p class="text-[11px] text-slate-300 mt-0.5 leading-relaxed">${escapeHtml(message)}</p>
        </div>
        <button onclick="document.getElementById('${toastId}')?.remove()" class="text-slate-400 hover:text-white text-xs select-none">✕</button>
    `;

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

export function switchMainTab(tabId) {
    const tabs = ['kanban', 'ux', 'legal', 'meetings', 'team'];
    if (!tabs.includes(tabId)) return;

    setCurrentTab(tabId);

    tabs.forEach(t => {
        const sec = document.getElementById(`view-${t}`);
        const btn = document.getElementById(`tab-btn-${t}`);
        if (sec) sec.classList.toggle('hidden', t !== tabId);
        if (btn) {
            if (t === tabId) {
                btn.className = 'px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-active';
            } else {
                btn.className = 'px-3.5 py-1.5 rounded-xl font-bold flex items-center gap-2 transition tab-inactive';
            }
        }
    });

    // Trigger tab-specific refresh
    if (tabId === 'meetings' && window.renderMeetingsView) {
        window.renderMeetingsView();
    } else if (tabId === 'ux' && window.renderUxHonestView) {
        window.renderUxHonestView();
    }
}

export function applySavedTheme() {
    const saved = appData.ui_preferences || {};
    const theme = saved.theme || 'obsidian';
    const density = saved.density || 'compact';

    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-density', density);

    const themeSelect = document.getElementById('theme-select');
    if (themeSelect) themeSelect.value = theme;
}

export async function selectTheme(themeId, saveToBackend = true) {
    document.documentElement.setAttribute('data-theme', themeId);
    if (!saveToBackend) return;

    try {
        await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_ui_preferences',
                theme: themeId
            })
        });
        showToast('Tema Aplicado', `Tema cambiado a ${themeId}`, 'success');
    } catch (err) {
        console.error('Error al guardar tema:', err);
    }
}

export function closeAllModals() {
    const modals = [
        'new-task-modal',
        'task-detail-modal',
        'edit-task-modal',
        'skills-modal',
        'skill-detail-modal',
        'add-skill-modal',
        'ai-engine-modal',
        'costs-modal',
        'change-workspace-modal',
        'jurisdiction-modal',
        'new-project-modal',
        'import-workspace-modal'
    ];
    modals.forEach(id => {
        const el = document.getElementById(id);
        if (el && !el.classList.contains('hidden')) {
            el.classList.add('hidden');
        }
    });

    const chatDrawer = document.getElementById('chat-drawer');
    if (chatDrawer && !chatDrawer.classList.contains('translate-x-full')) {
        chatDrawer.classList.add('translate-x-full');
    }
}

export function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Esc cierra modales
        if (e.key === 'Escape') {
            closeAllModals();
            return;
        }

        // Ctrl+K o Cmd+K: Búsqueda rápida en Kanban
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            switchMainTab('kanban');
            const searchInput = document.getElementById('kanban-search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }

        // Alt+1 .. Alt+5 para cambiar pestañas
        if (e.altKey && e.key >= '1' && e.key <= '5') {
            e.preventDefault();
            const map = { '1': 'kanban', '2': 'ux', '3': 'legal', '4': 'meetings', '5': 'team' };
            if (map[e.key]) switchMainTab(map[e.key]);
        }
    });
}

