/**
 * AgileForge v2 - Internationalization (i18n) Module
 * Soporte bilingüe completo (Español / English) para la interfaz de AgileForge.
 */

import { showToast } from './ui.js';

export const translations = {
    es: {
        // Header
        subtitle: 'Command Center de Agentes IA · Suite de Ingeniería, Diseño UX y Compliance',
        search_btn: 'Buscar',
        theme_btn: 'Tema',
        ai_engine_lbl: 'Motor IA:',
        local_ai_lbl: 'IA local:',
        jurisdiction_lbl: 'Jurisdicción:',
        consult_btn: 'Consultar',
        new_task_btn: '+ Nueva Historia',
        day_mode: 'Día',
        night_mode: 'Noche',
        manual_btn: 'Manual Novatos',
        lang_switch_tooltip: 'Cambiar idioma / Switch language',

        // Tabs
        tab_kanban: 'Tablero Scrum',
        tab_ux: 'Laboratorio UX/UI & Diseño',
        tab_legal: 'Auditoría Legal',
        tab_meetings: 'Sala de Reuniones',
        tab_team: 'Equipo de Agentes',

        // Kanban
        sprint_goal: 'Objetivo del Sprint:',
        sprint_progress: 'Progreso:',
        col_backlog: 'Historias de Usuario (Backlog)',
        col_in_progress: 'En Desarrollo',
        col_review: 'Revisión DoD / QA',
        col_completed: 'Aprobadas / Done',
        filter_all_roles: 'Todos los Agentes',
        filter_all_priorities: 'Todas las Prioridades',
        filter_all_skills: 'Todas las Skills',
        filter_search_placeholder: 'Buscar historias por título, ID o skill (Ctrl+K)...',
        priority_critical: 'Crítica',
        priority_high: 'Alta',
        priority_medium: 'Media',
        priority_low: 'Baja',

        // Projects
        current_project: 'Proyecto:',
        btn_new_project: 'Nuevo Proyecto',
        btn_import_workspace: 'Importar Workspace',

        // Modals
        modal_theme_title: 'Diseño & Ergonomía Visual',
        modal_theme_subtitle: 'Personaliza la estética, contraste y densidad del Command Center',
        palette_label: 'Paleta de Diseño',
        theme_obsidian: 'Cyber Obsidian (Noche)',
        theme_indigo: 'Electric Indigo (Noche Azul)',
        theme_emerald: 'Neon Emerald (Noche Verde)',
        theme_violet: 'Midnight Violet (Noche Púrpura)',
        theme_light: 'Solar Day (Modo Día)',
        theme_paper: 'Clean Paper (Día Suave)',
        density_label: 'Densidad de Información',
        density_comfortable: '👌 Cómodo (Default)',
        density_compact: '📊 Compacto (Scrum Pro)',
        btn_done: 'Listo',
        btn_cancel: 'Cancelar',

        // Manual modal
        manual_title: 'Manual de Inicio Rápido para Novatos',
        manual_subtitle: 'Aprende a usar AgileForge paso a paso sin complicaciones'
    },
    en: {
        // Header
        subtitle: 'AI Agent Command Center · Engineering, UX Design & Compliance Suite',
        search_btn: 'Search',
        theme_btn: 'Theme',
        ai_engine_lbl: 'AI Engine:',
        local_ai_lbl: 'Local AI:',
        jurisdiction_lbl: 'Jurisdiction:',
        consult_btn: 'Ask Agents',
        new_task_btn: '+ New Story',
        day_mode: 'Day',
        night_mode: 'Night',
        manual_btn: 'Beginner Guide',
        lang_switch_tooltip: 'Switch language / Cambiar idioma',

        // Tabs
        tab_kanban: 'Scrum Board',
        tab_ux: 'UX/UI & Design Lab',
        tab_legal: 'Legal Audit',
        tab_meetings: 'Meeting Room',
        tab_team: 'Agent Team',

        // Kanban
        sprint_goal: 'Sprint Goal:',
        sprint_progress: 'Progress:',
        col_backlog: 'User Stories (Backlog)',
        col_in_progress: 'In Progress',
        col_review: 'DoD / QA Review',
        col_completed: 'Approved / Done',
        filter_all_roles: 'All Agents',
        filter_all_priorities: 'All Priorities',
        filter_all_skills: 'All Skills',
        filter_search_placeholder: 'Search stories by title, ID or skill (Ctrl+K)...',
        priority_critical: 'Critical',
        priority_high: 'High',
        priority_medium: 'Medium',
        priority_low: 'Low',

        // Projects
        current_project: 'Project:',
        btn_new_project: 'New Project',
        btn_import_workspace: 'Import Workspace',

        // Modals
        modal_theme_title: 'Design & Visual Ergonomics',
        modal_theme_subtitle: 'Customize aesthetics, contrast and density of the Command Center',
        palette_label: 'Design Palette',
        theme_obsidian: 'Cyber Obsidian (Night)',
        theme_indigo: 'Electric Indigo (Deep Blue Night)',
        theme_emerald: 'Neon Emerald (Matrix Green)',
        theme_violet: 'Midnight Violet (Dark Violet)',
        theme_light: 'Solar Day (Day Mode)',
        theme_paper: 'Clean Paper (Soft Day)',
        density_label: 'Information Density',
        density_comfortable: '👌 Comfortable (Default)',
        density_compact: '📊 Compact (Scrum Pro)',
        btn_done: 'Done',
        btn_cancel: 'Cancel',

        // Manual modal
        manual_title: 'Beginner Quick Start Manual',
        manual_subtitle: 'Learn how to use AgileForge step by step with ease'
    }
};

let currentLanguage = localStorage.getItem('agileforge_lang') || 'es';

export function getLanguage() {
    return currentLanguage;
}

export function t(key, fallback = '') {
    const langDict = translations[currentLanguage] || translations.es;
    return langDict[key] || fallback || key;
}

export function setLanguage(lang) {
    if (lang !== 'es' && lang !== 'en') lang = 'es';
    currentLanguage = lang;
    localStorage.setItem('agileforge_lang', lang);
    applyLanguageToDOM();

    const langBtnText = document.getElementById('lang-btn-text');
    if (langBtnText) {
        langBtnText.textContent = lang.toUpperCase();
    }

    showToast(
        lang === 'es' ? 'Idioma Actualizado' : 'Language Updated',
        lang === 'es' ? 'Interfaz configurada en Español' : 'Interface set to English',
        'info',
        2500
    );
}

export function toggleLanguage() {
    const newLang = currentLanguage === 'es' ? 'en' : 'es';
    setLanguage(newLang);
}

export function applyLanguageToDOM() {
    const dict = translations[currentLanguage] || translations.es;

    // Elementos con data-i18n
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[key]) {
            el.textContent = dict[key];
        }
    });

    // Elementos con data-i18n-placeholder
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (dict[key]) {
            el.setAttribute('placeholder', dict[key]);
        }
    });

    // Elementos con data-i18n-title
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        if (dict[key]) {
            el.setAttribute('title', dict[key]);
        }
    });

    // Actualizar indicador de idioma en el botón
    const langBtnText = document.getElementById('lang-btn-text');
    if (langBtnText) {
        langBtnText.textContent = currentLanguage.toUpperCase();
    }

    // Actualizar título de documento
    document.title = currentLanguage === 'es'
        ? 'AgileForge — Scrum 3.0 & Command Center de Agentes IA'
        : 'AgileForge — Scrum 3.0 & AI Agent Command Center';
}

