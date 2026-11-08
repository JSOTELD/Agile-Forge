/**
 * AgileForge v2 - State Manager
 * Manejo del estado global de la aplicación, comunicación con la API y sanitización XSS.
 */

export let appData = {
    org_name: 'AgileForge Ecosystem',
    active_workspace: '',
    projects: [],
    current_project: null,
    sprint: {},
    roles: [],
    tasks: [],
    chat_messages: [],
    meetings: [],
    recent_events: [],
    skills_catalog: [],
    ai_engine: {},
    ui_preferences: {},
    costs_summary: {},
    ux_heuristics: null
};

export let currentTab = 'kanban';
export let activeConfigRoleId = null;
export let activeFilterRole = 'all';
export let activeFilterPriority = 'all';
export let activeFilterSkill = 'all';
export let activeFilterText = '';

export function setAppData(newData) {
    appData = { ...appData, ...newData };
}

export function setCurrentTab(tab) {
    currentTab = tab;
}

export function setActiveConfigRoleId(roleId) {
    activeConfigRoleId = roleId;
}

export function setFilterRole(r) { activeFilterRole = r; }
export function setFilterPriority(p) { activeFilterPriority = p; }
export function setFilterSkill(s) { activeFilterSkill = s; }
export function setFilterText(t) { activeFilterText = t; }

/**
 * Sanitiza texto para prevenir inyecciones XSS.
 */
export function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(str).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Peticiones autenticadas al backend PHP con token de sesión
 */
export async function apiFetch(endpoint, options = {}) {
    options.headers = options.headers || {};
    const metaToken = document.querySelector('meta[name="agileforge-token"]')?.content;
    if (metaToken) {
        options.headers['Authorization'] = `Bearer ${metaToken}`;
        options.headers['X-AgileForge-Token'] = metaToken;
    }
    return fetch(endpoint, options);
}

