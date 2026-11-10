/**
 * AgileForge - Presentación de roles y estados.
 * Un solo lugar para: el nombre corto de un rol, su avatar y el texto legible de un estado.
 *
 * No hay lista cerrada de roles: las etiquetas cortas son OPCIONALES. Un rol que no esté aquí
 * (por ejemplo, uno forjado) se muestra con el nombre y el avatar que entrega la API.
 */
import { appData, escapeHtml } from './state.js';
import { t } from './i18n.js';

/** Etiquetas cortas opcionales (clave i18n → texto por defecto). */
const SHORT_LABELS = {
    director: 'Director',
    creative_director: 'Dir. Creativo',
    lead_architect: 'Arquitecto',
    backend_architect: 'Backend',
    frontend_master: 'Frontend',
    qa_security_guard: 'QA',
    legal_counsel: 'Legal',
    project_manager: 'PM',
};

const STATUS_LABELS = {
    backlog: 'Backlog',
    en_progreso: 'En progreso',
    revision: 'En revisión',
    completada: 'Completada',
};

const PRIORITY_LABELS = {
    critica: 'Crítica',
    alta: 'Alta',
    media: 'Media',
    baja: 'Baja',
};

const SAFE_ID = /^[a-z0-9_]+$/;

// RFC-001: lo que un rol puede hacer viene de sus capacidades (archivos de rol), no de su ID
export const CAPABILITY_LABELS = {
    can_claim_tasks: 'Toma historias',
    can_submit_evidence: 'Entrega a revisión',
    can_review_tasks: 'Dictamina',
    can_approve_dod: 'Aprueba la DoD',
    can_record_adr: 'Registra ADR',
    can_facilitate_sprint: 'Facilita el sprint',
    can_forge_agents: 'Forja agentes',
    can_audit_compliance: 'Audita cumplimiento',
    can_orchestrate: 'Orquesta el equipo',
};

export function roleCan(roleOrId, capability) {
    const role = typeof roleOrId === 'string' ? roleById(roleOrId) : roleOrId;
    return !!role && Array.isArray(role.capabilities) && role.capabilities.includes(capability);
}

export function capabilityLabel(cap) {
    return t(`cap_${cap}`, CAPABILITY_LABELS[cap] || cap);
}

export function roleById(roleId) {
    return (appData.roles || []).find(r => r.id === roleId) || null;
}

/** Nombre corto y legible de un rol. Acepta el id o el objeto del rol. */
export function roleLabel(roleOrId) {
    const id = typeof roleOrId === 'string' ? roleOrId : roleOrId?.id;
    const role = typeof roleOrId === 'string' ? roleById(roleOrId) : roleOrId;
    if (id && SHORT_LABELS[id]) return t(`role_${id}`, SHORT_LABELS[id]);
    if (role?.name) return String(role.name);
    return id ? String(id).replace(/_/g, ' ') : t('role_unknown', 'Agente');
}

/** Emoji del rol que entrega la API (respaldo cuando no hay imagen). */
export function roleEmoji(roleOrId) {
    const role = typeof roleOrId === 'string' ? roleById(roleOrId) : roleOrId;
    return role?.avatar || '🤖';
}

/**
 * Avatar del rol como HTML seguro. Usa assets/brand/avatars/<id>.png si el id es un identificador simple;
 * si la imagen no existe o falla, la sustituye el emoji del rol (ver el listener de errores de abajo).
 * La imagen es decorativa (alt=""): el nombre del rol va siempre en texto junto a ella.
 */
export function avatarFor(roleOrId, size = 24) {
    const id = typeof roleOrId === 'string' ? roleOrId : roleOrId?.id;
    const emoji = roleEmoji(roleOrId);
    const px = Math.max(12, Math.min(96, parseInt(size, 10) || 24));
    if (!id || !SAFE_ID.test(id)) {
        return `<span class="role-avatar-emoji" aria-hidden="true">${escapeHtml(emoji)}</span>`;
    }
    return `<img class="role-avatar" src="assets/brand/avatars/${id}.png" alt="" width="${px}" height="${px}" loading="lazy" decoding="async" data-fallback="${escapeHtml(emoji)}">`;
}

/** Texto legible de un estado del tablero. */
export function statusLabel(status) {
    return STATUS_LABELS[status] ? t(`status_${status}`, STATUS_LABELS[status]) : String(status || '');
}

/** Texto legible de una prioridad. */
// Datos antiguos guardaban la prioridad en inglés; el servidor ya los migra, esto es la red de seguridad.
const PRIORITY_ALIASES = { critical: 'critica', high: 'alta', medium: 'media', low: 'baja' };

export function normalizePriority(priority) {
    const p = String(priority || '').toLowerCase();
    return PRIORITY_LABELS[p] ? p : (PRIORITY_ALIASES[p] || 'media');
}

export function priorityLabel(priority) {
    const p = normalizePriority(priority);
    return t(`priority_${p}`, PRIORITY_LABELS[p]);
}

// Los eventos "error" de las imágenes no burbujean, pero sí pasan por la fase de captura:
// un único listener sustituye por el emoji cualquier avatar que no cargue (rol sin imagen propia).
if (typeof document !== 'undefined') {
    document.addEventListener('error', (ev) => {
        const img = ev.target;
        if (!(img instanceof HTMLImageElement) || !img.classList.contains('role-avatar')) return;
        const span = document.createElement('span');
        span.className = 'role-avatar-emoji';
        span.setAttribute('aria-hidden', 'true');
        span.textContent = img.dataset.fallback || '🤖';
        img.replaceWith(span);
    }, true);
}
