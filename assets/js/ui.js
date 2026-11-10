/**
 * AgileForge v2 - UI & Notifications Manager
 * Notificaciones toast, navegación entre pestañas, temas (Modo Día/Noche),
 * manual para novatos y atajos de teclado.
 */

import { appData, currentTab, setCurrentTab, escapeHtml, apiFetch } from './state.js';

export function showToast(title, message, type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const currentTheme = document.documentElement.getAttribute('data-theme') || 'obsidian';
    const isLight = (currentTheme === 'light' || currentTheme === 'paper');

    const toast = document.createElement('div');
    // Sufijo aleatorio: dos avisos en el mismo milisegundo no deben compartir id (el botón ✕ cierra por id)
    const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
    toast.id = toastId;

    const colorsDark = {
        success: 'border-emerald-500/40 bg-slate-900/95 text-emerald-300 shadow-emerald-950/40',
        error:   'border-rose-500/40 bg-slate-900/95 text-rose-300 shadow-rose-950/40',
        warning: 'border-amber-500/40 bg-slate-900/95 text-amber-300 shadow-amber-950/40',
        info:    'border-indigo-500/40 bg-slate-900/95 text-indigo-300 shadow-indigo-950/40',
        magic:   'border-purple-500/40 bg-slate-900/95 text-purple-300 shadow-purple-950/40'
    };

    const colorsLight = {
        success: 'border-emerald-200 bg-white text-emerald-700 shadow-xl border-l-4 border-l-emerald-500',
        error:   'border-rose-200 bg-white text-rose-700 shadow-xl border-l-4 border-l-rose-500',
        warning: 'border-amber-200 bg-white text-amber-800 shadow-xl border-l-4 border-l-amber-500',
        info:    'border-indigo-200 bg-white text-indigo-700 shadow-xl border-l-4 border-l-indigo-600',
        magic:   'border-purple-200 bg-white text-purple-700 shadow-xl border-l-4 border-l-purple-600'
    };

    const icons = {
        success: '✓',
        error:   '✕',
        warning: '⚠️',
        info:    'ℹ️',
        magic:   '⚡'
    };

    const colorClass = (isLight ? colorsLight[type] : colorsDark[type]) || (isLight ? colorsLight.info : colorsDark.info);
    const icon = icons[type] || 'ℹ️';

    toast.className = `toast-card toast-${type} border rounded-2xl p-4 shadow-xl backdrop-blur-md flex items-start gap-3 transition-all duration-300 transform translate-y-2 opacity-0 max-w-sm w-full pointer-events-auto ${colorClass}`;
    toast.innerHTML = `
        <span class="text-base font-bold select-none shrink-0">${icon}</span>
        <div class="flex-1 min-w-0">
            <h5 class="text-xs font-bold ${isLight ? 'text-slate-900' : 'text-white'} tracking-wide">${escapeHtml(title)}</h5>
            <p class="text-[11px] ${isLight ? 'text-slate-600' : 'text-slate-300'} mt-0.5 leading-relaxed">${escapeHtml(message)}</p>
        </div>
        <button onclick="document.getElementById('${toastId}')?.remove()" class="${isLight ? 'text-slate-400 hover:text-slate-800' : 'text-slate-400 hover:text-white'} text-xs select-none">✕</button>
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
    let localTheme = null;
    try { localTheme = localStorage.getItem('agileforge_theme'); } catch (e) {}
    const theme = localTheme || saved.theme || 'obsidian';
    const density = saved.density || 'compact';

    selectTheme(theme, false);
    document.documentElement.setAttribute('data-density', density);
}

export async function selectTheme(themeId, saveToBackend = true) {
    const validThemes = ['obsidian', 'indigo', 'emerald', 'violet', 'light', 'paper'];
    if (!validThemes.includes(themeId)) themeId = 'obsidian';

    // Establecer atributo y clase en HTML
    document.documentElement.setAttribute('data-theme', themeId);
    document.documentElement.className = `theme-${themeId}`;
    try { localStorage.setItem('agileforge_theme', themeId); } catch (e) {}

    // Actualizar botones indicadores en la barra superior
    updateThemeIndicators(themeId);

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
        showToast('Tema Aplicado', `Tema visual cambiado a: ${formatThemeName(themeId)}`, 'success', 2500);
    } catch (err) {
        console.error('Error al guardar tema:', err);
    }
}

export function toggleDayNightMode() {
    const current = document.documentElement.getAttribute('data-theme') || 'obsidian';
    const isLight = (current === 'light' || current === 'paper');
    const newTheme = isLight ? 'obsidian' : 'light';
    selectTheme(newTheme, true);
}

function updateThemeIndicators(themeId) {
    const isLight = (themeId === 'light' || themeId === 'paper');

    // Cambiar logos según el tema (logo oscuro para modo día, logo luminoso para modo noche)
    const headerLogo = document.getElementById('header-brand-logo');
    if (headerLogo) {
        headerLogo.src = isLight ? 'assets/brand/symbol-agileforge.png' : 'assets/brand/symbol-agileforge-dark.png';
    }
    const footerLogo = document.getElementById('footer-brand-logo');
    if (footerLogo) {
        footerLogo.src = isLight ? 'assets/brand/symbol-agileforge.png' : 'assets/brand/symbol-agileforge-dark.png';
    }

    // Botón indicador de tema
    const themeBtnText = document.getElementById('theme-btn-text');
    if (themeBtnText) themeBtnText.textContent = formatThemeName(themeId);

    // Botón directo Día/Noche
    const dayNightIcon = document.getElementById('day-night-icon');
    const dayNightText = document.getElementById('day-night-text');
    if (dayNightIcon) dayNightIcon.textContent = isLight ? '☀️' : '🌙';
    if (dayNightText) dayNightText.textContent = isLight ? 'Día' : 'Noche';

    // Resaltar opción seleccionada en el modal de temas
    const validThemes = ['obsidian', 'indigo', 'emerald', 'violet', 'light', 'paper'];
    validThemes.forEach(t => {
        const el = document.getElementById(`theme-opt-${t}`);
        if (el) {
            if (t === themeId) {
                el.classList.add('border-indigo-500', 'ring-2', 'ring-indigo-500/30');
            } else {
                el.classList.remove('border-indigo-500', 'ring-2', 'ring-indigo-500/30');
            }
        }
    });
}

function formatThemeName(themeId) {
    const names = {
        obsidian: 'Obsidian',
        indigo: 'Indigo',
        emerald: 'Emerald',
        violet: 'Violet',
        light: 'Solar Day',
        paper: 'Clean Paper'
    };
    return names[themeId] || themeId;
}

export function openBeginnerManualModal() {
    const modal = document.getElementById('beginner-manual-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeBeginnerManualModal() {
    const modal = document.getElementById('beginner-manual-modal');
    if (modal) modal.classList.add('hidden');
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
        'import-workspace-modal',
        'theme-modal',
        'command-palette-modal',
        'beginner-manual-modal'
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

        // Alt+M abre manual de novatos
        if (e.altKey && (e.key === 'm' || e.key === 'M')) {
            e.preventDefault();
            openBeginnerManualModal();
        }
    });
}
