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

    // Conmutar avatares de roles en vivo según tema claro/oscuro sin recargar la página
    document.querySelectorAll('.role-avatar[data-avatar-id]').forEach(img => {
        const id = img.dataset.avatarId;
        if (!id) return;
        const targetSrc = isLight ? `assets/brand/avatars/${id}-light.png` : `assets/brand/avatars/${id}.png`;
        if (img.getAttribute('src') !== targetSrc) {
            img.dataset.triedDark = isLight ? 'false' : 'true';
            img.src = targetSrc;
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

/** Modales del panel: cualquier capa fija cuyo id termina en "-modal". */
function modalElements() {
    return Array.from(document.querySelectorAll('.fixed[id$="-modal"]'));
}

function isVisibleModal(el) {
    return !!el && !el.classList.contains('hidden');
}

export function closeAllModals() {
    modalElements().forEach(el => {
        if (isVisibleModal(el)) el.classList.add('hidden');
    });
}

// Orden real de apertura (lo mantiene initModalA11y): el último abierto es el que está encima
const openOrder = [];

/** Cierra solo el modal que está encima (el último que se abrió). Devuelve true si cerró alguno. */
export function closeTopModal() {
    const open = modalElements().filter(isVisibleModal);
    const top = [...openOrder].reverse().find(isVisibleModal) || open[open.length - 1];
    if (!top) return false;
    top.classList.add('hidden');
    return true;
}

/**
 * Accesibilidad de modales: papel de diálogo y devolución del foco.
 * Al abrirse un modal se recuerda qué control tenía el foco; al cerrarse, el foco vuelve a ese control.
 * Se observa la clase "hidden" para no tener que tocar cada función que abre o cierra un modal.
 */
export function initModalA11y() {
    let lastOutside = null;
    const insideOpenModal = (node) => !!(node && node.closest && node.closest('.fixed[id$="-modal"]:not(.hidden)'));
    const remember = (ev) => {
        const el = ev.target instanceof Element ? ev.target.closest('button, a, [tabindex], input, select, textarea') : null;
        if (el && !insideOpenModal(el)) lastOutside = el;
    };
    document.addEventListener('focusin', remember);
    document.addEventListener('pointerdown', remember, true);

    const openers = new WeakMap();
    const observer = new MutationObserver((records) => {
        records.forEach((rec) => {
            const modal = rec.target;
            const wasHidden = String(rec.oldValue || '').split(/\s+/).includes('hidden');
            const hidden = modal.classList.contains('hidden');
            if (wasHidden && !hidden) {
                openers.set(modal, lastOutside);
                const at = openOrder.indexOf(modal);
                if (at >= 0) openOrder.splice(at, 1);
                openOrder.push(modal);
                // Un modal abierto desde el panel de ajustes lo cierra: nunca quedan dos capas compitiendo
                toggleSettingsMenu(false);
            } else if (!wasHidden && hidden) {
                const opener = openers.get(modal);
                openers.delete(modal);
                const at = openOrder.indexOf(modal);
                if (at >= 0) openOrder.splice(at, 1);
                if (opener && document.contains(opener) && !modalElements().some(isVisibleModal)) {
                    // Si el control que lo abrió ya no se ve (estaba en el panel de ajustes), el foco va al botón de ajustes
                    const target = opener.offsetParent !== null ? opener : document.getElementById('settings-menu-btn');
                    if (target) target.focus({ preventScroll: true });
                }
            }
        });
    });
    modalElements().forEach((modal) => {
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        const heading = modal.querySelector('h2, h3, h4');
        if (heading) {
            if (!heading.id) heading.id = `${modal.id}-title`;
            modal.setAttribute('aria-labelledby', heading.id);
        }
        observer.observe(modal, { attributes: true, attributeFilter: ['class'], attributeOldValue: true });
    });
}

export function initKeyboardShortcuts() {
    initModalA11y();
    document.addEventListener('keydown', (e) => {
        // Esc cierra modales
        if (e.key === 'Escape') {
            // Primero el modal que está encima; luego popover de grafos o ajustes
            if (closeTopModal()) return;
            const graphPop = document.getElementById('graph-health-popover');
            if (graphPop && !graphPop.classList.contains('hidden')) {
                toggleGraphHealthPopover(false);
                document.getElementById('graph-health-pill')?.focus({ preventScroll: true });
                return;
            }
            const menu = document.getElementById('settings-menu');
            if (menu && !menu.classList.contains('hidden')) {
                toggleSettingsMenu(false);
                document.getElementById('settings-menu-btn')?.focus({ preventScroll: true });
            }
            return;
        }

        // WCAG AA: Focus Trap para modales activos (Tab / Shift+Tab loop)
        if (e.key === 'Tab') {
            const openModals = modalElements().filter(isVisibleModal);
            const activeModal = [...openOrder].reverse().find(isVisibleModal) || openModals[openModals.length - 1];
            if (activeModal) {
                const focusable = Array.from(activeModal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                    .filter(el => el.offsetParent !== null || el.getClientRects().length > 0);
                if (focusable.length > 0) {
                    const firstEl = focusable[0];
                    const lastEl = focusable[focusable.length - 1];
                    if (e.shiftKey) {
                        if (document.activeElement === firstEl || !activeModal.contains(document.activeElement)) {
                            e.preventDefault();
                            lastEl.focus();
                        }
                    } else {
                        if (document.activeElement === lastEl || !activeModal.contains(document.activeElement)) {
                            e.preventDefault();
                            firstEl.focus();
                        }
                    }
                }
            }
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


/**
 * Panel de ajustes de la cabecera: se abre y cierra con su botón, se cierra con Escape
 * o al pulsar fuera, y devuelve el foco al botón.
 */
export function toggleSettingsMenu(force) {
    const menu = document.getElementById('settings-menu');
    const btn = document.getElementById('settings-menu-btn');
    if (!menu || !btn) return;
    const open = typeof force === 'boolean' ? force : menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !open);
    btn.setAttribute('aria-expanded', String(open));
    if (open) {
        const first = menu.querySelector('button, a');
        if (first) first.focus({ preventScroll: true });
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('click', (ev) => {
        const menu = document.getElementById('settings-menu');
        if (menu && !menu.classList.contains('hidden')) {
            if (!ev.target.closest('#settings-menu') && !ev.target.closest('#settings-menu-btn')) {
                toggleSettingsMenu(false);
            }
        }
        const pop = document.getElementById('graph-health-popover');
        if (pop && !pop.classList.contains('hidden')) {
            if (!ev.target.closest('#graph-health-popover') && !ev.target.closest('#graph-health-pill')) {
                toggleGraphHealthPopover(false);
            }
        }
    });
    if (typeof window !== 'undefined') window.toggleSettingsMenu = toggleSettingsMenu;
}

/**
 * Indicador Ligero de Salud de Grafos (M5): Graphify + Bóveda Obsidian
 */
export function toggleGraphHealthPopover(force) {
    const pop = document.getElementById('graph-health-popover');
    const pill = document.getElementById('graph-health-pill');
    if (!pop || !pill) return;
    const open = typeof force === 'boolean' ? force : pop.classList.contains('hidden');
    pop.classList.toggle('hidden', !open);
    pill.setAttribute('aria-expanded', String(open));
    if (open) {
        toggleSettingsMenu(false);
        refreshGraphHealth();
        const first = pop.querySelector('button, a');
        if (first) first.focus({ preventScroll: true });
    }
}

export async function refreshGraphHealth() {
    const indicator = document.getElementById('graph-health-indicator');
    const statusBadge = document.getElementById('graph-health-badge-status');
    const statNodes = document.getElementById('graph-stat-nodes');
    const statEdges = document.getElementById('graph-stat-edges');
    const statComms = document.getElementById('graph-stat-communities');
    const statCommit = document.getElementById('graph-stat-commit');
    const statVault = document.getElementById('graph-stat-vault');
    const label = document.getElementById('graph-health-label');

    try {
        const res = await apiFetch('api/tasks.php?action=graph_health');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        const codeGraph = data.code_graph || {};
        const vault = data.vault || {};

        const hasReport = !!codeGraph.exists;
        const nodes = hasReport && codeGraph.nodes !== null && codeGraph.nodes !== undefined ? codeGraph.nodes : '—';
        const edges = hasReport && codeGraph.edges !== null && codeGraph.edges !== undefined ? codeGraph.edges : '—';
        const comms = hasReport && codeGraph.communities !== null && codeGraph.communities !== undefined ? codeGraph.communities : '—';
        const commit = codeGraph.built_commit || '';
        const head = codeGraph.head_commit || '';
        const commitsBehind = codeGraph.commits_behind || 0;
        const status = data.status || (hasReport ? (codeGraph.stale ? 'stale' : 'fresh') : 'missing');

        if (statNodes) statNodes.textContent = String(nodes);
        if (statEdges) statEdges.textContent = String(edges);
        if (statComms) statComms.textContent = String(comms);
        if (statCommit) {
            statCommit.textContent = commit
                ? `Commit: ${commit}${head ? ` (HEAD: ${head.slice(0, 7)})` : ''}${codeGraph.cluster_only ? ' [cluster-only]' : ''}`
                : 'Commit: —';
        }

        const vaultNotes = vault.exists ? (vault.notes_count ?? 0) : '—';
        const vaultCodeNotes = vault.code_notes_count ? ` (${vault.code_notes_count} código)` : '';
        const vaultDate = vault.generated_at ? new Date(vault.generated_at).toLocaleString() : (vault.index_exists ? 'Al día' : '—');
        if (statVault) {
            statVault.textContent = vault.exists
                ? `Notas: ${vaultNotes}${vaultCodeNotes} · Actualizado: ${vaultDate}`
                : 'Bóveda: Sin inicializar';
        }

        if (status === 'fresh') {
            if (indicator) indicator.className = 'w-2 h-2 rounded-full bg-emerald-400 shrink-0';
            if (statusBadge) {
                statusBadge.textContent = '🟢 Al día';
                statusBadge.className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-950/60 border border-emerald-500/30 text-emerald-300';
            }
            if (label) label.textContent = 'Grafo al día';
        } else if (status === 'stale') {
            if (indicator) indicator.className = 'w-2 h-2 rounded-full bg-amber-400 shrink-0';
            if (statusBadge) {
                statusBadge.textContent = '🟡 Desactualizado';
                statusBadge.className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-950/60 border border-amber-500/30 text-amber-300';
            }
            if (label) label.textContent = commitsBehind ? `Grafo: ${commitsBehind} detrás` : 'Grafo desactualizado';
        } else {
            if (indicator) indicator.className = 'w-2 h-2 rounded-full bg-slate-500 shrink-0';
            if (statusBadge) {
                statusBadge.textContent = '⚪ Sin reporte';
                statusBadge.className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400';
            }
            if (label) label.textContent = 'Sin grafo';
        }
    } catch (err) {
        if (indicator) indicator.className = 'w-2 h-2 rounded-full bg-slate-500 shrink-0';
        if (statusBadge) {
            statusBadge.textContent = '⚪ Sin reporte';
            statusBadge.className = 'text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400';
        }
        if (label) label.textContent = 'Sin grafo';
        if (statNodes) statNodes.textContent = '—';
        if (statEdges) statEdges.textContent = '—';
        if (statComms) statComms.textContent = '—';
        if (statCommit) statCommit.textContent = 'Commit: —';
        if (statVault) statVault.textContent = 'Bóveda: —';
    }
}

export async function syncBitacoraAction() {
    const btn = document.getElementById('graph-sync-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Sincronizando…';
    }
    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sync_bitacora', client: 'web' })
        });
        const data = await res.json().catch(() => ({ success: false, error: 'Sin respuesta JSON' }));
        if (data.success) {
            showToast('Bitácora Sincronizada', data.message || 'Se actualizaron las notas en Obsidian (.agileforge/vault)', 'success', 3500);
            await refreshGraphHealth();
        } else {
            showToast('Sincronización', data.error || 'No se pudo sincronizar la bitácora', 'warning', 4000);
        }
    } catch (err) {
        showToast('Error', 'Fallo al sincronizar bitácora', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>🔄</span> Sincronizar Bitácora';
        }
    }
}

if (typeof window !== 'undefined') {
    window.toggleGraphHealthPopover = toggleGraphHealthPopover;
    window.refreshGraphHealth = refreshGraphHealth;
    window.syncBitacoraAction = syncBitacoraAction;
}
