/**
 * AgileForge v2 - State Manager
 * Manejo del estado global de la aplicación, comunicación con la API y sanitización XSS.
 */

export const appData = {
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
    // Se actualiza el mismo objeto (no se reemplaza) para que window.appData y todos los módulos vean lo mismo
    Object.assign(appData, newData || {});
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
 * Argumento seguro para un atributo onclick="fn(...)": literal JS (JSON) escapado para HTML.
 * escapeHtml solo no basta dentro de un handler: el navegador decodifica &#039; a ' antes de ejecutar el JS,
 * y un valor con comillas (p. ej. una skill escrita por un agente) cerraría la cadena e inyectaría código.
 */
export function jsArg(value) {
    return escapeHtml(JSON.stringify(String(value ?? '')));
}

/**
 * Fecha de SQLite (CURRENT_TIMESTAMP, en UTC y sin zona) mostrada en la hora local del navegador.
 */
export function localTime(sqlDate) {
    if (!sqlDate) return '';
    const s = String(sqlDate);
    const iso = /[zZ]|[+-]\d\d:?\d\d$/.test(s) ? s : s.replace(' ', 'T') + 'Z';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return s;
    const today = new Date();
    const sameDay = d.toDateString() === today.toDateString();
    const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return sameDay ? time : `${d.toLocaleDateString([], { day: '2-digit', month: '2-digit' })} ${time}`;
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

