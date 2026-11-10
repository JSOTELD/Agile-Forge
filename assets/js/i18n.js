/**
 * AgileForge v2 - Internationalization (i18n) Module
 * Soporte bilingüe completo (Español / English) para la interfaz de AgileForge.
 */

import { showToast } from './ui.js';

export const translations = {
    es: {
        legal_not_audited: 'Sin auditar',
        signed_msg: 'Historia completada',
        signed_title: 'Firma registrada',
        review_waiting: 'Esperando la revisión de QA.',
        review_notes: 'Notas de QA',
        review_commands: 'Verificó con',
        review_checklist: 'Checklist',
        review_by: 'Aprobó',
        review_no_delivery: 'Todavía no hay una entrega registrada.',
        review_tests: 'Pruebas',
        review_files: 'Archivos',
        review_summary: 'Entrega',
        review_title: 'Revisión',
        workspace_change_tip: 'Cambiar carpeta de trabajo',
        workspace_lbl: 'Carpeta de trabajo:',
        settings_help: 'Ayuda y vista',
        settings_project: 'Proyecto',
        settings_ai: 'Motor de IA',
        settings_appearance: 'Apariencia e idioma',
        settings_btn: 'Ajustes',
        consult_tip: 'Consultar a los agentes',
        search_tip: 'Buscar (Ctrl + K)',
        project_settings_tip: 'Ajustes del proyecto (firma humana)',
        project_import_tip: 'Importar un proyecto existente (lee su historial de git)',
        project_new_tip: 'Crear nuevo proyecto',
        project_lbl: 'Proyecto',
        sign_complete: 'Firmar y completar',
        qa_signoff_pending: 'Aprobada por QA · falta tu firma',
        quick_review_tip: 'QA aprobó muy pronto tras la entrega: revísala con cuidado antes de firmar',
        quick_review: 'Revisión rápida',
        ev_comment: 'dejó un comentario',
        ev_created: 'creó la historia',
        ev_moved: 'la pasó a',
        ev_review_changes: 'pidió cambios',
        ev_review_ok: 'la aprobó',
        ev_qa_revoked: 'retiró la aprobación de QA',
        ev_qa_approved: 'la aprobó; falta tu firma',
        ev_submitted: 'la entregó a revisión',
        ev_claimed: 'tomó la historia',
        feed_agent: 'Un agente',
        feed_you: 'Tú (panel)',
        col_empty: 'Nada aquí',
        model_chip_tip: 'Esta historia usa un modelo distinto del motor global',
        lock_by: 'Tomada por',
        move_none_fwd: 'No hay estado siguiente',
        move_none_back: 'No hay estado anterior',
        move_to: 'Mover a',
        priority_lbl: 'Prioridad',
        priority_baja: 'Baja',
        priority_media: 'Media',
        priority_alta: 'Alta',
        priority_critica: 'Crítica',
        status_completada: 'Completada',
        status_revision: 'En revisión',
        status_en_progreso: 'En progreso',
        status_backlog: 'Backlog',
        role_unknown: 'Agente',
        role_project_manager: 'PM',
        role_legal_counsel: 'Legal',
        role_qa_security_guard: 'QA',
        role_frontend_master: 'Frontend',
        role_backend_architect: 'Backend',
        role_lead_architect: 'Arquitecto',
        role_creative_director: 'Dir. Creativo',
        role_director: 'Director',
        cost_plugin_tip: 'En modo plugin el trabajo corre en Claude Code: el panel no gasta tokens.',
        cost_plugin_na: 'No aplica',
        sprint_points_tip: 'Puntos completados / puntos planificados para el sprint',
        sprint_points_lbl: 'Hechos / plan',
        ux_goals_badge: '6 objetivos',
        ux_goals_sub: 'Son metas, no mediciones. Lo que sí se mide está en el escaneo del DOM y en el checklist de abajo.',
        ux_goals_title: 'Objetivos de diseño del panel',
        goal_feedback_d: 'Objetivo: un aviso breve y no invasivo después de cada acción.',
        goal_feedback_t: 'Respuesta a cada acción',
        goal_status_d: 'Objetivo: sprint, avance y motor de IA a la vista en todo momento.',
        goal_status_t: 'Estado siempre visible',
        goal_restraint_d: 'Objetivo: color solo donde aporta significado; nada decorativo.',
        goal_restraint_t: 'Estética sobria',
        goal_touch_d: 'Objetivo: controles de al menos 44×44 px en pantallas táctiles.',
        goal_touch_t: 'Áreas de toque cómodas',
        goal_motion_d: 'Objetivo: transiciones de unos 200 ms en botones, pestañas y tarjetas.',
        goal_motion_t: 'Transiciones breves',
        goal_contrast_d: 'Objetivo: texto con contraste AA (4,5:1) en todos los temas.',
        goal_contrast_t: 'Contraste legible',
        // Header
        subtitle: 'Tu equipo de agentes de IA para Claude Code',
        search_btn: 'Buscar',
        theme_btn: 'Tema',
        ai_engine_lbl: 'Motor IA:',
        local_ai_lbl: 'IA local:',
        jurisdiction_lbl: 'Jurisdicción:',
        consult_btn: 'Consultar',
        new_task_btn: 'Nueva historia',
        day_mode: 'Día',
        night_mode: 'Noche',
        manual_btn: 'Manual',
        lang_switch_tooltip: 'Cambiar idioma / Switch language',

        // Tabs
        tab_kanban: 'Tablero',
        tab_ux: 'Laboratorio UX',
        tab_legal: 'Legal',
        tab_meetings: 'Reuniones',
        tab_team: 'Equipo',

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
        manual_subtitle: 'Aprende a usar AgileForge paso a paso sin complicaciones',

        // Director General
        director_title: 'Director General',
        director_subtitle: 'Dale una instrucción: revisa el tablero, decide, delega en los agentes y te rinde cuentas.',
        director_placeholder: 'Ej.: Prioriza lo necesario para lanzar reservas online esta semana y reparte el trabajo entre el equipo (Ctrl+Enter para enviar)',
        director_send: 'Dar instrucción',
        director_quick: 'Rápidas:',
        director_q_status: '📊 Estado del sprint',
        director_q_backlog: '🧭 Ordenar backlog',
        director_q_unblock: '🚧 Desbloquear',

        // Claves de API
        api_keys_title: '🔑 Claves de API',
        api_keys_desc: 'Pega tu clave para que los roles con ese proveedor respondan desde AgileForge. Se guarda en el archivo .env de esta PC; el navegador solo ve una pista (ej. sk-ant-…a1b2).',

        // Vista y modo plugin
        view_btn: 'Vista',
        view_btn_title: 'Abrir AgileForge Vista (solo lectura) en otra pestaña',
        plugin_notice: 'Modo plugin: el cerebro es Claude Code (instala el plugin AgileForge Control) · o añade una clave / activa la IA local',
        plugin_engine_badge: '🧩 Claude Code',
        plugin_engine_hint: 'Este rol trabaja desde Claude Code; no necesita motor en la web.',
        director_plugin_note: 'Las instrucciones desde esta web necesitan un motor de IA. Desde Claude Code usa /director.',
        director_plugin_toast: 'Desde Claude Code usa /director, o añade una clave / activa la IA local para dar instrucciones desde aquí.',
        plugin_mode_title: 'Claude Code',
        plugin_mode_msg: 'Modo plugin: este trabajo lo hace Claude Code desde tu terminal (/director, /reunion, /tomar). O añade una clave / activa la IA local para usar el motor desde aquí.',

        // Detalle de historia
        detail_agent: 'Agente',
        detail_priority: 'Prioridad',
        detail_points: 'Puntos',
        detail_story: 'Historia de usuario',
        detail_desc: 'Detalles técnicos',
        detail_ask_director: '👑 Pedir decisión al Director',
        detail_save: 'Guardar cambios',
        task_create_btn: 'Crear historia y generar prompt',
        chat_subtitle: 'Da instrucciones al Director o consulta a cualquier agente',
        team_subtitle: 'Asigna a cada rol su motor de IA (API y modelo, o IA local) y sus skills. El Director General es el jefe máximo del equipo.',
    },
    en: {
        legal_not_audited: 'Not audited',
        signed_msg: 'Story completed',
        signed_title: 'Sign-off recorded',
        review_waiting: 'Waiting for the QA review.',
        review_notes: 'QA notes',
        review_commands: 'Verified with',
        review_checklist: 'Checklist',
        review_by: 'Approved by',
        review_no_delivery: 'No delivery has been recorded yet.',
        review_tests: 'Tests',
        review_files: 'Files',
        review_summary: 'Delivery',
        review_title: 'Review',
        workspace_change_tip: 'Change working folder',
        workspace_lbl: 'Working folder:',
        settings_help: 'Help and view',
        settings_project: 'Project',
        settings_ai: 'AI engine',
        settings_appearance: 'Appearance and language',
        settings_btn: 'Settings',
        consult_tip: 'Ask the agents',
        search_tip: 'Search (Ctrl + K)',
        project_settings_tip: 'Project settings (human sign-off)',
        project_import_tip: 'Import an existing project (reads its git history)',
        project_new_tip: 'Create a new project',
        project_lbl: 'Project',
        sign_complete: 'Sign and complete',
        qa_signoff_pending: 'Approved by QA · your sign-off is pending',
        quick_review_tip: 'QA approved very soon after delivery: check it carefully before signing',
        quick_review: 'Quick review',
        ev_comment: 'left a comment',
        ev_created: 'created the story',
        ev_moved: 'moved it to',
        ev_review_changes: 'requested changes',
        ev_review_ok: 'approved it',
        ev_qa_revoked: 'revoked the QA approval',
        ev_qa_approved: 'approved it; your sign-off is pending',
        ev_submitted: 'submitted it for review',
        ev_claimed: 'claimed the story',
        feed_agent: 'An agent',
        feed_you: 'You (panel)',
        col_empty: 'Nothing here',
        model_chip_tip: 'This story uses a model other than the global engine',
        lock_by: 'Claimed by',
        move_none_fwd: 'No next status',
        move_none_back: 'No previous status',
        move_to: 'Move to',
        priority_lbl: 'Priority',
        priority_baja: 'Low',
        priority_media: 'Medium',
        priority_alta: 'High',
        priority_critica: 'Critical',
        status_completada: 'Done',
        status_revision: 'In review',
        status_en_progreso: 'In progress',
        status_backlog: 'Backlog',
        role_unknown: 'Agent',
        role_project_manager: 'PM',
        role_legal_counsel: 'Legal',
        role_qa_security_guard: 'QA',
        role_frontend_master: 'Frontend',
        role_backend_architect: 'Backend',
        role_lead_architect: 'Architect',
        role_creative_director: 'Creative Dir.',
        role_director: 'Director',
        cost_plugin_tip: 'In plugin mode the work runs in Claude Code: the panel spends no tokens.',
        cost_plugin_na: 'N/A',
        sprint_points_tip: 'Points completed / points planned for the sprint',
        sprint_points_lbl: 'Done / planned',
        ux_goals_badge: '6 goals',
        ux_goals_sub: 'These are goals, not measurements. What is measured is in the DOM scan and the checklist below.',
        ux_goals_title: 'Design goals for the panel',
        goal_feedback_d: 'Goal: a short, non-intrusive notice after every action.',
        goal_feedback_t: 'Feedback for every action',
        goal_status_d: 'Goal: sprint, progress and AI engine in view at all times.',
        goal_status_t: 'Status always visible',
        goal_restraint_d: 'Goal: colour only where it carries meaning; nothing decorative.',
        goal_restraint_t: 'Restrained look',
        goal_touch_d: 'Goal: controls of at least 44×44 px on touch screens.',
        goal_touch_t: 'Comfortable touch targets',
        goal_motion_d: 'Goal: transitions of about 200 ms on buttons, tabs and cards.',
        goal_motion_t: 'Short transitions',
        goal_contrast_d: 'Goal: AA text contrast (4.5:1) in every theme.',
        goal_contrast_t: 'Readable contrast',
        // Header
        subtitle: 'Your AI agent team for Claude Code',
        search_btn: 'Search',
        theme_btn: 'Theme',
        ai_engine_lbl: 'AI Engine:',
        local_ai_lbl: 'Local AI:',
        jurisdiction_lbl: 'Jurisdiction:',
        consult_btn: 'Ask Agents',
        new_task_btn: 'New story',
        day_mode: 'Day',
        night_mode: 'Night',
        manual_btn: 'Guide',
        lang_switch_tooltip: 'Switch language / Cambiar idioma',

        // Tabs
        tab_kanban: 'Board',
        tab_ux: 'UX Lab',
        tab_legal: 'Legal',
        tab_meetings: 'Meetings',
        tab_team: 'Team',

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
        manual_subtitle: 'Learn how to use AgileForge step by step with ease',

        // Director General
        director_title: 'Managing Director',
        director_subtitle: 'Give an instruction: it reviews the board, decides, delegates to the agents and reports back to you.',
        director_placeholder: 'E.g.: Prioritize what we need to launch online bookings this week and split the work across the team (Ctrl+Enter to send)',
        director_send: 'Send instruction',
        director_quick: 'Quick:',
        director_q_status: '📊 Sprint status',
        director_q_backlog: '🧭 Sort backlog',
        director_q_unblock: '🚧 Unblock',

        // API keys
        api_keys_title: '🔑 API keys',
        api_keys_desc: 'Paste your key so roles using that provider can answer from AgileForge. It is stored in this PC\'s .env file; the browser only sees a hint (e.g. sk-ant-…a1b2).',

        // View and plugin mode
        view_btn: 'View',
        view_btn_title: 'Open AgileForge View (read-only) in another tab',
        plugin_notice: 'Plugin mode: the brain is Claude Code (install the AgileForge Control plugin) · or add a key / turn on local AI',
        plugin_engine_badge: '🧩 Claude Code',
        plugin_engine_hint: 'This role works from Claude Code; it needs no engine in the web app.',
        director_plugin_note: 'Instructions from this page need an AI engine. From Claude Code use /director.',
        director_plugin_toast: 'From Claude Code use /director, or add a key / turn on local AI to give instructions from here.',
        plugin_mode_title: 'Claude Code',
        plugin_mode_msg: 'Plugin mode: this work is done by Claude Code from your terminal (/director, /reunion, /tomar). Or add a key / turn on local AI to use the engine from here.',

        // Story detail
        detail_agent: 'Agent',
        detail_priority: 'Priority',
        detail_points: 'Points',
        detail_story: 'User story',
        detail_desc: 'Technical details',
        detail_ask_director: '👑 Ask the Director to decide',
        detail_save: 'Save changes',
        task_create_btn: 'Create story and generate prompt',
        chat_subtitle: 'Give instructions to the Director or ask any agent',
        team_subtitle: 'Assign each role its AI engine (API and model, or local AI) and its skills. The Managing Director leads the team.',
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

    // Textos generados por JS (modo plugin)
    if (window.renderPluginNotice) window.renderPluginNotice();
    if (window.renderDirectorPanel) window.renderDirectorPanel();

    // Actualizar indicador de idioma en el botón
    const langBtnText = document.getElementById('lang-btn-text');
    if (langBtnText) {
        langBtnText.textContent = currentLanguage.toUpperCase();
    }

    // Actualizar título de documento
    document.title = currentLanguage === 'es'
        ? 'AgileForge · Tu equipo de agentes de IA para Claude Code'
        : 'AgileForge · Your AI agent team for Claude Code';
}

