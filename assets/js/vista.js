/**
 * AgileForge Vista: panel de solo lectura (módulo ES autónomo).
 * Solo hace GET a api/index.php/overview con el token de vista. Todo dato se pinta con textContent.
 */
const DICT = {
    es: {
        skip: 'Saltar al contenido', title_tag: 'Vista', project: 'Proyecto', night: 'Noche', day: 'Día',
        sprint: 'Sprint', board: 'Tablero', working: 'Quién trabaja en qué', meetings: 'Reuniones', decisions: 'Decisiones', activity: 'Actividad',
        readonly: 'Solo lectura · sin claves ni acciones de escritura',
        connected: 'Conectado', offline: 'Sin conexión', connecting: 'Conectando…',
        updated: 'actualizado hace {n}', retrying: 'reintentando en {n} s',
        c_backlog: 'Backlog', c_en_progreso: 'En progreso', c_revision: 'En revisión', c_completada: 'Completadas',
        pts: 'pts', tasks_n: '{n} tareas', days_left: '{n} días restantes', day_left: '1 día restante', no_sprint: 'Sin sprint activo',
        since: 'Desde ayer: {a} cerradas · {b} en revisión · {c} decisiones', no_changes: 'Sin cambios desde ayer',
        empty_tasks: 'Este sprint aún no tiene tareas.', empty_col: 'Nada aquí.', empty_working: 'Nadie tiene tareas tomadas ahora mismo.',
        empty_meetings: 'Aún no hay reuniones. Aparecen cuando el Director convoca una.', empty_decisions: 'Todavía no hay decisiones registradas.', empty_events: 'Sin actividad reciente.',
        plugin: 'Sin motor de IA en la web: el trabajo lo hace Claude Code y aquí ves lo que registra.',
        stale: 'más de 24 h tomada', turns: '{n} turnos', resolution: 'Resolución', no_resolution: 'Sin resolución todavía',
        err_auth: 'Token de vista no válido o ausente. Revisa AGILEFORGE_VIEW_TOKEN.', err_generic: 'No se pudo cargar el panel ({e}).',
        s: 's', m: 'min', h: 'h', d: 'd', ago_now: 'ahora'
    },
    en: {
        skip: 'Skip to content', title_tag: 'View', project: 'Project', night: 'Night', day: 'Day',
        sprint: 'Sprint', board: 'Board', working: 'Who is working on what', meetings: 'Meetings', decisions: 'Decisions', activity: 'Activity',
        readonly: 'Read-only · no keys or write actions',
        connected: 'Connected', offline: 'Offline', connecting: 'Connecting…',
        updated: 'updated {n} ago', retrying: 'retrying in {n} s',
        c_backlog: 'Backlog', c_en_progreso: 'In progress', c_revision: 'In review', c_completada: 'Done',
        pts: 'pts', tasks_n: '{n} tasks', days_left: '{n} days left', day_left: '1 day left', no_sprint: 'No active sprint',
        since: 'Since yesterday: {a} closed · {b} in review · {c} decisions', no_changes: 'No changes since yesterday',
        empty_tasks: 'This sprint has no tasks yet.', empty_col: 'Nothing here.', empty_working: 'Nobody has a task claimed right now.',
        empty_meetings: 'No meetings yet. They show up when the Director calls one.', empty_decisions: 'No decisions recorded yet.', empty_events: 'No recent activity.',
        plugin: 'No AI engine in the web app: Claude Code does the work and you see here what it records.',
        stale: 'claimed over 24 h ago', turns: '{n} turns', resolution: 'Resolution', no_resolution: 'No resolution yet',
        err_auth: 'View token invalid or missing. Check AGILEFORGE_VIEW_TOKEN.', err_generic: 'Could not load the panel ({e}).',
        s: 's', m: 'min', h: 'h', d: 'd', ago_now: 'now'
    }
};
const COLS = ['backlog', 'en_progreso', 'revision', 'completada'];
const ICON = { backlog: '📥', en_progreso: '🛠️', revision: '🔎', completada: '✅' };
const REFRESH_MS = 5000, MAX_BACKOFF = 30000, STALE_MS = 24 * 3600 * 1000;

const $ = (id) => document.getElementById(id);
let lang = 'es';
try { const l = localStorage.getItem('agileforge_lang'); if (l === 'en' || l === 'es') lang = l; } catch (e) {}
let data = null, lastOk = 0, failures = 0, timer = null, ctrl = null, project = new URLSearchParams(location.search).get('project') || '';

const t = (k, vars) => {
    let s = (DICT[lang] && DICT[lang][k]) || DICT.es[k] || k;
    if (vars) for (const v in vars) s = s.replace('{' + v + '}', vars[v]);
    return s;
};
function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = String(text);
    return n;
}
function parseDate(v) { const d = v ? new Date(String(v).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(String(v)) ? '' : 'Z')) : null; return d && !isNaN(d) ? d : null; }
function ago(v) {
    const d = parseDate(v); if (!d) return '';
    const s = Math.max(0, Math.round((Date.now() - d.getTime()) / 1000));
    if (s < 5) return t('ago_now');
    if (s < 60) return s + ' ' + t('s');
    if (s < 3600) return Math.floor(s / 60) + ' ' + t('m');
    if (s < 86400) return Math.floor(s / 3600) + ' ' + t('h');
    return Math.floor(s / 86400) + ' ' + t('d');
}
const clear = (n) => { while (n.firstChild) n.removeChild(n.firstChild); };

function applyStatic() {
    document.documentElement.lang = lang;
    document.querySelectorAll('[data-i]').forEach((n) => { n.textContent = t(n.getAttribute('data-i')); });
    $('lang-btn').textContent = lang.toUpperCase();
    const day = document.documentElement.getAttribute('data-theme') === 'day';
    $('theme-ico').textContent = day ? '☀️' : '🌙';
    $('theme-text').textContent = t(day ? 'day' : 'night');
    $('theme-btn').setAttribute('aria-pressed', String(day));
}
function setConn(ok) {
    const b = $('conn');
    b.className = 'badge ' + (ok ? 'on' : 'off');
    $('conn').querySelector('.ico').textContent = ok ? '✔' : '✖';
    $('conn-text').textContent = ok ? t('connected') : (failures ? t('offline') : t('connecting'));
}
function renderUpdated() {
    const n = $('updated');
    if (!lastOk) { n.textContent = ''; return; }
    let s = t('updated', { n: ago(new Date(lastOk).toISOString()) });
    if (failures) s += ' · ' + t('retrying', { n: Math.round(delay() / 1000) });
    n.textContent = s;
}

function renderSprint(d) {
    const sp = d.sprint || null;
    $('sprint-name').textContent = sp ? (sp.name ? `${sp.number && !/^\s*sprint\b/i.test(sp.name) ? 'Sprint ' + sp.number + ' · ' : ''}${sp.name}` : `Sprint ${sp.number || ''}`.trim()) : t('no_sprint');
    $('sprint-goal').textContent = sp && sp.goal ? sp.goal : '';
    const pct = sp ? Math.max(0, Math.min(100, Math.round(Number(sp.progress_pct) || 0))) : 0;
    $('sprint-pct').textContent = pct + '%';
    $('sprint-fill').style.width = pct + '%';
    const bar = $('sprint-bar');
    bar.setAttribute('aria-valuenow', String(pct));
    bar.setAttribute('aria-valuetext', pct + '% · ' + (sp ? `${sp.points_done || 0}/${sp.points_total || 0} ${t('pts')}` : ''));
    let days = '';
    if (sp && sp.days_remaining !== undefined && sp.days_remaining !== null) days = sp.days_remaining === 1 ? t('day_left') : t('days_left', { n: sp.days_remaining });
    if (sp) days += (days ? ' · ' : '') + `${sp.points_done || 0}/${sp.points_total || 0} ${t('pts')}`;
    $('sprint-days').textContent = days;
    const y = d.since_yesterday || {};
    const a = +y.completed || 0, b = +y.to_review || 0, c = +y.decisions || 0;
    $('since').textContent = (a || b || c) ? t('since', { a, b, c }) : t('no_changes');
}
function renderBoard(d) {
    const tasks = Array.isArray(d.tasks) ? d.tasks : [];
    const cols = d.columns || {};
    $('board-empty').classList.toggle('hidden', tasks.length > 0);
    $('board-empty').textContent = t('empty_tasks');
    const board = $('board'); clear(board);
    COLS.forEach((c) => {
        const info = cols[c] || {};
        const sec = el('div', 'col');
        const h = el('h3');
        h.appendChild(el('span', '', `${ICON[c]} ${t('c_' + c)}`));
        h.appendChild(el('span', 'n', `${info.count ?? tasks.filter((x) => x.status === c).length} · ${info.points ?? 0} ${t('pts')}`));
        sec.appendChild(h);
        const ul = el('ul');
        const items = tasks.filter((x) => x.status === c);
        items.forEach((k) => {
            const li = el('li', 'card' + (k.priority === 'critica' ? ' crit' : k.priority === 'alta' ? ' high' : ''));
            li.appendChild(el('div', 'id mono', k.id));
            li.appendChild(el('div', '', k.title));
            const m = el('div', 'meta');
            m.appendChild(el('span', '', `${k.avatar || ''} ${k.role_name || k.assigned_to || ''}`.trim()));
            m.appendChild(el('span', '', `${k.story_points ?? 0} ${t('pts')}`));
            li.appendChild(m);
            ul.appendChild(li);
        });
        if (!items.length && tasks.length) ul.appendChild(el('li', 'empty', t('empty_col')));
        sec.appendChild(ul);
        board.appendChild(sec);
    });
}
function renderWorking(d) {
    const ul = $('working'); clear(ul);
    const w = Array.isArray(d.working) ? d.working : [];
    if (!w.length) { ul.appendChild(el('li', 'empty', t('empty_working'))); return; }
    w.forEach((x) => {
        const since = parseDate(x.since);
        const stale = since && Date.now() - since.getTime() > STALE_MS;
        const li = el('li', stale ? 'stale' : '');
        const r = el('div', 'row');
        r.appendChild(el('span', 'av', x.avatar || '🤖'));
        r.appendChild(el('strong', '', x.name || x.role_id));
        r.appendChild(el('span', 'mono muted', x.task_id));
        r.appendChild(el('span', '', x.task_title));
        const wh = el('span', 'when', (stale ? '⚠️ ' : '') + ago(x.since) + (stale ? ' · ' + t('stale') : ''));
        r.appendChild(wh);
        li.appendChild(r);
        ul.appendChild(li);
    });
}
function renderMeetings(d) {
    const box = $('meetings'); clear(box);
    const ms = Array.isArray(d.meetings) ? d.meetings : [];
    if (!ms.length) { box.appendChild(el('p', 'empty', t('empty_meetings'))); return; }
    ms.forEach((m) => {
        const a = el('article', 'meet');
        const h = el('h3'); h.textContent = m.title || m.id;
        a.appendChild(h);
        const meta = el('p', 'muted', [m.id, m.type, t('turns', { n: m.turns_count || 0 }), ago(m.updated_at || m.created_at)].filter(Boolean).join(' · '));
        a.appendChild(meta);
        const res = el('p', 'res');
        res.appendChild(el('strong', '', t('resolution') + ': '));
        res.appendChild(document.createTextNode(m.resolution || t('no_resolution')));
        a.appendChild(res);
        const turns = Array.isArray(m.last_turns) ? m.last_turns : [];
        if (turns.length) {
            const ul = el('ul', 'turns');
            turns.forEach((x) => {
                const li = el('li');
                li.appendChild(el('b', '', `${x.avatar || ''} ${x.name || x.role_id}: `.trimStart()));
                li.appendChild(document.createTextNode(x.statement || ''));
                ul.appendChild(li);
            });
            a.appendChild(ul);
        }
        box.appendChild(a);
    });
}
function renderDecisions(d) {
    const ul = $('decisions'); clear(ul);
    const ds = Array.isArray(d.decisions) ? d.decisions : [];
    if (!ds.length) { ul.appendChild(el('li', 'empty', t('empty_decisions'))); return; }
    ds.forEach((x) => {
        const li = el('li');
        const r = el('div', 'row');
        r.appendChild(el('strong', 'mono', x.id));
        r.appendChild(el('span', 'chip', x.status || ''));
        r.appendChild(el('span', 'when', ago(x.created_at)));
        li.appendChild(r);
        li.appendChild(el('div', '', x.title));
        if (x.decision) li.appendChild(el('div', 'muted', x.decision));
        ul.appendChild(li);
    });
}
function renderEvents(d) {
    const ul = $('activity'); clear(ul);
    const es = Array.isArray(d.events) ? d.events : [];
    if (!es.length) { ul.appendChild(el('li', 'empty', t('empty_events'))); return; }
    es.forEach((x) => {
        const li = el('li');
        const r = el('div', 'row');
        if (x.actor) r.appendChild(el('strong', '', x.actor));
        r.appendChild(el('span', 'chip', x.type || ''));
        r.appendChild(el('span', 'when', ago(x.created_at)));
        li.appendChild(r);
        li.appendChild(el('div', 'muted', x.message));
        ul.appendChild(li);
    });
}
function renderNotice(d) {
    const n = $('notice');
    const on = !!(d && d.engine && d.engine.plugin_mode);
    n.classList.toggle('hidden', !on);
    n.textContent = on ? 'ℹ️ ' + t('plugin') : '';
}
function renderProjects(d) {
    const wrap = $('project-wrap'), sel = $('project-select');
    const list = Array.isArray(d.projects) ? d.projects : null;
    if (!list || list.length < 2) { wrap.classList.add('hidden'); return; }
    wrap.classList.remove('hidden');
    clear(sel);
    list.forEach((p) => { const o = el('option', '', p.name || p.id); o.value = p.id; sel.appendChild(o); });
    sel.value = (d.project && d.project.id) || project || '';
}
function render() {
    if (!data) return;
    $('project-name').textContent = data.project ? (data.project.name || data.project.id || '') : '';
    renderNotice(data); renderProjects(data); renderSprint(data); renderBoard(data);
    renderWorking(data); renderMeetings(data); renderDecisions(data); renderEvents(data);
}

function delay() { return failures ? Math.min(MAX_BACKOFF, REFRESH_MS * Math.pow(2, failures)) : REFRESH_MS; }
function showError(msg) { const n = $('notice'); n.classList.remove('hidden'); n.classList.add('warn'); n.textContent = '⚠️ ' + msg; }
async function load() {
    clearTimeout(timer);
    if (document.hidden) return;
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    const token = (document.querySelector('meta[name="agileforge-view-token"]') || {}).content || '';
    const url = 'api/index.php/overview' + (project ? '?project=' + encodeURIComponent(project) : '');
    try {
        const res = await fetch(url, { headers: { 'X-AgileForge-Token': token, 'Accept': 'application/json' }, cache: 'no-store', signal: ctrl.signal });
        let j = null;
        try { j = await res.json(); } catch (e) { /* cuerpo no JSON */ }
        if (!res.ok || !j || j.success === false) {
            const err = new Error((j && j.error) || 'HTTP ' + res.status); err.status = res.status; throw err;
        }
        data = j; lastOk = Date.now(); failures = 0;
        $('notice').classList.remove('warn');
        setConn(true); render();
    } catch (e) {
        if (e.name === 'AbortError') return;
        failures++;
        setConn(false);
        if (e.status === 401 || e.status === 403) showError(t('err_auth'));
        else if (!data) showError(t('err_generic', { e: e.message }));
    } finally {
        renderUpdated();
        if (!document.hidden) timer = setTimeout(load, delay());
    }
}

$('lang-btn').addEventListener('click', () => {
    lang = lang === 'es' ? 'en' : 'es';
    try { localStorage.setItem('agileforge_lang', lang); } catch (e) {}
    applyStatic(); setConn(!failures && !!lastOk); render(); renderUpdated();
});
$('theme-btn').addEventListener('click', () => {
    const day = document.documentElement.getAttribute('data-theme') !== 'day';
    document.documentElement.setAttribute('data-theme', day ? 'day' : 'night');
    try { localStorage.setItem('agileforge_theme', day ? 'light' : 'obsidian'); } catch (e) {}
    applyStatic();
});
$('project-select').addEventListener('change', (e) => {
    project = e.target.value;
    const u = new URL(location.href); u.searchParams.set('project', project); history.replaceState(null, '', u);
    data = null; load();
});
document.addEventListener('visibilitychange', () => { if (document.hidden) { clearTimeout(timer); if (ctrl) ctrl.abort(); } else load(); });
setInterval(renderUpdated, 1000);
applyStatic(); setConn(false); load();
