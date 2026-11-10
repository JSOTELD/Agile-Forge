/**
 * AgileForge v2 - Multi-Project & Workspace Selector
 * Permite cambiar entre proyectos aislados, crear nuevos y auto-importar con escaneo de git log.
 */

import { appData, apiFetch, escapeHtml } from './state.js';
import { showToast } from './ui.js';

export function renderProjectSelector() {
    const sel = document.getElementById('project-selector-dropdown');
    if (!sel) return;

    const projects = appData.projects || [];
    const current = appData.current_project || {};

    if (projects.length === 0) {
        sel.innerHTML = `<option value="default">AgileForge Default</option>`;
        return;
    }

    sel.innerHTML = projects.map(p => {
        const isSelected = p.id === current.id ? 'selected' : '';
        return `<option value="${escapeHtml(p.id)}" ${isSelected}>${escapeHtml(p.name)}</option>`;
    }).join('');

    const wsDisplay = document.getElementById('active-workspace-display');
    if (wsDisplay) {
        wsDisplay.textContent = current.path || appData.active_workspace || 'Sin workspace';
    }

    const chk = document.getElementById('project-setting-human-signoff');
    if (chk) {
        chk.checked = current.human_signoff ?? appData.human_signoff?.enabled ?? true;
    }
}

export async function handleSwitchProject(projectId) {
    if (!projectId) return;

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'switch_project',
                project_id: projectId
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Proyecto Activo', data.message, 'success');
            if (window.fetchTeamData) await window.fetchTeamData();
        } else {
            showToast('Error', data.error || 'No se pudo cambiar de proyecto', 'error');
        }
    } catch (err) {
        showToast('Error de Red', 'Fallo al conectar con el servidor', 'error');
    }
}

export function openNewProjectModal() {
    const modal = document.getElementById('new-project-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeNewProjectModal() {
    const modal = document.getElementById('new-project-modal');
    if (modal) modal.classList.add('hidden');
}

export async function submitNewProject() {
    const nameInput = document.getElementById('new-project-name');
    const pathInput = document.getElementById('new-project-path');

    const name = nameInput?.value?.trim() || '';
    const path = pathInput?.value?.trim() || '';

    if (!name) {
        showToast('Campo Requerido', 'Ingresa el nombre del nuevo proyecto', 'warning');
        return;
    }

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_project',
                name,
                path
            })
        });
        const data = await res.json();
        if (data.success) {
            closeNewProjectModal();
            showToast('Proyecto Creado', data.message, 'magic');
            if (nameInput) nameInput.value = '';
            if (window.fetchTeamData) await window.fetchTeamData();
        } else {
            showToast('Error', data.error || 'No se pudo crear el proyecto', 'error');
        }
    } catch (err) {
        showToast('Error de Red', 'Fallo al crear proyecto', 'error');
    }
}

export function openImportWorkspaceModal() {
    const modal = document.getElementById('import-workspace-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeImportWorkspaceModal() {
    const modal = document.getElementById('import-workspace-modal');
    if (modal) modal.classList.add('hidden');
}

export async function submitImportWorkspace() {
    const pathInput = document.getElementById('import-workspace-path');
    const nameInput = document.getElementById('import-workspace-name');

    const path = pathInput?.value?.trim() || '';
    const name = nameInput?.value?.trim() || '';

    if (!path) {
        showToast('Ruta Requerida', 'Especifica la ruta absoluta de la carpeta a importar', 'warning');
        return;
    }

    showToast('Importando Workspace', 'Escaneando historial git y dependencias...', 'info');

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'import_workspace',
                path,
                name
            })
        });
        const data = await res.json();
        if (data.success) {
            closeImportWorkspaceModal();
            showToast('Workspace Importado', data.message, 'magic');
            if (window.fetchTeamData) await window.fetchTeamData();
        } else {
            showToast('Error de Importación', data.error || 'No se pudo importar el workspace', 'error');
        }
    } catch (err) {
        showToast('Error de Red', 'Fallo al importar workspace', 'error');
    }
}

export function openProjectSettingsModal() {
    const modal = document.getElementById('project-settings-modal');
    if (!modal) return;
    const current = appData.current_project || {};
    const chk = document.getElementById('project-setting-human-signoff');
    if (chk) {
        chk.checked = current.human_signoff ?? appData.human_signoff?.enabled ?? true;
    }
    const nameEl = document.getElementById('project-settings-name');
    if (nameEl) {
        nameEl.textContent = current.name || 'AgileForge Default';
    }
    modal.classList.remove('hidden');
}

export function closeProjectSettingsModal() {
    const modal = document.getElementById('project-settings-modal');
    if (modal) modal.classList.add('hidden');
}

export async function toggleProjectHumanSignoff(enabled) {
    if (typeof enabled !== 'boolean') return; // solo el interruptor (true/false) cambia este ajuste
    const current = appData.current_project || {};
    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_human_signoff',
                project_id: current.id || null,
                enabled: Boolean(enabled),
                client: 'web'
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Firma Humana', enabled ? 'Firma humana requerida para completar historias' : 'Firma humana desactivada (QA completará directo)', 'info');
            if (window.fetchTeamData) await window.fetchTeamData();
        } else {
            showToast('Error', data.error || 'No se pudo actualizar el ajuste', 'error');
        }
    } catch (err) {
        showToast('Error de Red', 'Fallo al comunicar con el servidor', 'error');
    }
}

window.openProjectSettingsModal = openProjectSettingsModal;
window.closeProjectSettingsModal = closeProjectSettingsModal;
window.toggleProjectHumanSignoff = toggleProjectHumanSignoff;


