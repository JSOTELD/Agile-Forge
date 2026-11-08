/**
 * AgileForge v2 - Skills Catalog & Agent Toolkit
 * Asignación de skills por agente, explorador de toolkit y visor de fichas técnicas SKILL.md.
 */

import { appData, apiFetch, escapeHtml, activeConfigRoleId, setActiveConfigRoleId } from './state.js';
import { showToast } from './ui.js';

let skillsActiveCategory = 'all';
let skillsSearchQuery = '';

export function openSkillsConfig(roleId) {
    setActiveConfigRoleId(roleId);
    const role = (appData.roles || []).find(r => r.id === roleId);
    if (!role) return;

    const modalTitle = document.getElementById('skills-modal-agent');
    if (modalTitle) modalTitle.textContent = `${role.avatar} ${role.name}`;

    skillsActiveCategory = 'all';
    skillsSearchQuery = '';

    const searchInput = document.getElementById('skills-search-input');
    if (searchInput) searchInput.value = '';

    updateSkillsCategoryButtons();
    renderSkillsCards();

    const modal = document.getElementById('skills-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeSkillsConfig() {
    const modal = document.getElementById('skills-modal');
    if (modal) modal.classList.add('hidden');
    setActiveConfigRoleId(null);
}

export function filterSkillsByCategory(category) {
    skillsActiveCategory = category;
    updateSkillsCategoryButtons();
    renderSkillsCards();
}

export function filterSkillsCatalog() {
    const input = document.getElementById('skills-search-input');
    skillsSearchQuery = (input?.value || '').toLowerCase().trim();
    renderSkillsCards();
}

export function updateSkillsCategoryButtons() {
    const buttons = document.querySelectorAll('.skills-cat-btn');
    buttons.forEach(btn => {
        const onclickAttr = btn.getAttribute('onclick') || '';
        if (onclickAttr.includes(`'${skillsActiveCategory}'`)) {
            btn.className = 'skills-cat-btn px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-bold whitespace-nowrap';
        } else {
            btn.className = 'skills-cat-btn px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white font-medium whitespace-nowrap';
        }
    });
}

export function renderSkillsCards() {
    const container = document.getElementById('skills-cards-container');
    const role = (appData.roles || []).find(r => r.id === activeConfigRoleId);
    if (!container || !role) return;

    const assigned = new Set(role.assigned_skills || []);
    let catalog = appData.skills_catalog || [];

    if (skillsActiveCategory !== 'all') {
        catalog = catalog.filter(s => s.category === skillsActiveCategory);
    }

    if (skillsSearchQuery) {
        catalog = catalog.filter(s => 
            s.name.toLowerCase().includes(skillsSearchQuery) ||
            s.id.toLowerCase().includes(skillsSearchQuery) ||
            (s.description || '').toLowerCase().includes(skillsSearchQuery)
        );
    }

    if (catalog.length === 0) {
        container.innerHTML = `<div class="col-span-2 p-6 text-center text-xs text-slate-500">No se encontraron skills que coincidan con el filtro.</div>`;
        return;
    }

    container.innerHTML = catalog.map(s => {
        const isChecked = assigned.has(s.id);
        return `
            <div class="p-3 rounded-2xl bg-slate-950 border ${isChecked ? 'border-indigo-500/40 bg-indigo-950/20' : 'border-slate-800 hover:border-slate-700'} text-xs transition flex items-start gap-2.5">
                <input type="checkbox" value="${escapeHtml(s.id)}" ${isChecked ? 'checked' : ''} onchange="updateSelectedSkillsCount()"
                       class="w-4 h-4 mt-0.5 accent-indigo-500 rounded cursor-pointer">
                <div class="space-y-1 flex-1 cursor-pointer">
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-bold text-white flex items-center gap-1">
                            <span>${escapeHtml(s.icon || '⚡')}</span> <span>${escapeHtml(s.name)}</span>
                        </span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded-full font-mono text-slate-400 bg-slate-900 border border-slate-800 whitespace-nowrap">
                            ${escapeHtml(s.category_name || s.category)}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-snug">${escapeHtml(s.description || '')}</p>
                </div>
                <button type="button" onclick="openSkillDetail('${escapeHtml(s.id)}')" title="Ver Ficha Técnica" class="text-slate-500 hover:text-indigo-400 p-1">
                    ℹ️
                </button>
            </div>
        `;
    }).join('');

    updateSelectedSkillsCount();
}

export function updateSelectedSkillsCount() {
    const role = (appData.roles || []).find(r => r.id === activeConfigRoleId);
    if (!role) return;

    const checkedInputs = Array.from(document.querySelectorAll('#skills-cards-container input:checked'));
    const badge = document.getElementById('skills-selected-count');
    if (badge) {
        badge.textContent = `${checkedInputs.length} skills seleccionadas para ${role.name.split(' ')[0]}`;
    }
}

export async function saveAgentSkills() {
    if (!activeConfigRoleId) return;
    const checked = Array.from(document.querySelectorAll('#skills-cards-container input:checked')).map(i => i.value);

    try {
        await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_agent_skills', role_id: activeConfigRoleId, skills: checked })
        });
        closeSkillsConfig();
        if (window.fetchTeamData) await window.fetchTeamData();
        showToast('Toolkit Actualizado', `Skills asignadas correctamente`, 'success');
    } catch (err) {
        showToast('Error', 'No se pudieron guardar las skills', 'error');
    }
}

export function openSkillDetail(skillId) {
    const skill = (appData.skills_catalog || []).find(s => s.id === skillId) || {
        id: skillId,
        name: skillId,
        icon: '⚡',
        description: 'Capacidad especializada para ejecución de ingeniería de software.',
        prompt_snippet: `Ejecuta la tarea aplicando las directivas técnicas de ${skillId}.`,
        recommended_roles: ['lead_architect', 'backend_architect']
    };

    const iconEl = document.getElementById('detail-skill-icon');
    const nameEl = document.getElementById('detail-skill-name');
    const idEl = document.getElementById('detail-skill-id');
    const descEl = document.getElementById('detail-skill-desc');
    const snippetEl = document.getElementById('detail-skill-snippet');
    const rolesContainer = document.getElementById('detail-skill-roles');

    if (iconEl) iconEl.textContent = skill.icon || '⚡';
    if (nameEl) nameEl.textContent = skill.name || skill.id;
    if (idEl) idEl.textContent = skill.id;
    if (descEl) descEl.textContent = skill.description || 'Sin descripción adicional.';
    if (snippetEl) snippetEl.value = skill.prompt_snippet || `Aplica la skill ${skill.name} con estricta adherencia a estándares de calidad.`;

    if (rolesContainer) {
        const roles = skill.recommended_roles || [];
        rolesContainer.innerHTML = roles.map(rId => {
            const role = (appData.roles || []).find(r => r.id === rId);
            return `<span class="px-2 py-0.5 rounded-lg bg-slate-900 border border-slate-800 text-[10px] text-slate-300 font-mono">${role ? role.avatar : '🤖'} ${role ? role.name : rId}</span>`;
        }).join('') || '<span class="text-slate-500 text-[10px]">Todos los agentes</span>';
    }

    const modal = document.getElementById('skill-detail-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeSkillDetailModal() {
    const modal = document.getElementById('skill-detail-modal');
    if (modal) modal.classList.add('hidden');
}

export function copySkillSnippet() {
    const snippetEl = document.getElementById('detail-skill-snippet');
    if (snippetEl && snippetEl.value) {
        navigator.clipboard.writeText(snippetEl.value).then(() => {
            showToast('Prompt Copiado', 'Directiva copiada al portapapeles', 'success');
        });
    }
}

export function openAddSkillModal() {
    const modal = document.getElementById('add-skill-modal');
    if (modal) modal.classList.remove('hidden');
}

export function closeAddSkillModal() {
    const modal = document.getElementById('add-skill-modal');
    if (modal) modal.classList.add('hidden');
    document.getElementById('add-skill-form')?.reset();
}

export async function handleCreateCustomSkill(e) {
    if (e && e.preventDefault) e.preventDefault();
    const name = document.getElementById('new-skill-name')?.value?.trim();
    const category = document.getElementById('new-skill-category')?.value || 'engineering';
    const icon = document.getElementById('new-skill-icon')?.value?.trim() || '⚡';
    const desc = document.getElementById('new-skill-desc')?.value?.trim() || '';
    const promptSnippet = document.getElementById('new-skill-prompt')?.value?.trim() || '';

    if (!name) {
        showToast('Campo Requerido', 'Ingresa el nombre de la skill', 'warning');
        return;
    }

    const catNames = {
        design: 'Diseño & UI/UX',
        product: 'Estrategia',
        agile: 'Scrum',
        engineering: 'Arquitectura & Backend',
        qa: 'QA & Seguridad',
        legal: 'Legal'
    };

    const id = name.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');

    try {
        const res = await apiFetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_custom_skill',
                id,
                name,
                category,
                category_name: catNames[category] || 'General',
                icon,
                description: desc,
                prompt_snippet: promptSnippet
            })
        });
        const data = await res.json();
        if (data.success) {
            closeAddSkillModal();
            if (window.fetchTeamData) await window.fetchTeamData();
            if (activeConfigRoleId) renderSkillsCards();
            showToast('Skill Creada', `Registrada ${icon} ${name}`, 'magic');
        }
    } catch (err) {
        showToast('Error', 'No se pudo crear la skill', 'error');
    }
}

