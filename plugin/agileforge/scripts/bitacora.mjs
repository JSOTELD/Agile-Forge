#!/usr/bin/env node
// Bitácora de AgileForge → notas de Obsidian (grafo bibliográfico).
//
// Lee el tablero por la API REST (solo GET: funciona con el token de vista) y escribe en el vault una nota por
// historia, decisión (ADR), reunión, memoria, rol y sprint, enlazadas con [[wikilinks]] entre sí y con las notas
// del código que genera `graphify export obsidian` (carpeta codigo/ del vault).
//
// Uso:  node bitacora.mjs --vault <carpeta> [--project <id>] [--codigo codigo] [--root <carpeta>] [--dry-run] [--no-detail]
//
// Reglas:
// - Sin dependencias (Node >= 18). Nunca imprime el token ni escribe fuera de --vault.
// - Solo sobrescribe archivos que generó él (manifiesto .agileforge-bitacora.json). Un archivo tuyo con el mismo
//   nombre, o una nota generada que editaste a mano, no se toca: se informa.
// - Incremental: solo pide el detalle de las historias y reuniones que cambiaron desde la última ejecución.
// - Las notas de sprint son instantáneas: nunca se borran (quedan como historial).
// - Excluye a propósito prompt_template, modelos de IA y rutas del workspace.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const RENDERER = 2; // súbelo si cambia el formato de las notas: fuerza a regenerarlas todas
const MANIFEST = '.agileforge-bitacora.json';
const DEFAULT_API_URL = 'http://localhost/Open-to-code/api';
const TIMEOUT_MS = 15000;
const MARK = 'agileforge-bitacora';
const ID_RE = /^[A-Za-z0-9][A-Za-z0-9_.-]{0,80}$/;
const ROLE_RE = /^[a-z0-9][a-z0-9_-]{0,63}$/;
const ROLE_ALIASES = {
  'chief-architect': 'lead_architect',
  chief_architect: 'lead_architect',
  backend: 'backend_architect',
  frontend: 'frontend_master',
  'qa-security': 'qa_security_guard',
  qa_security: 'qa_security_guard',
  'legal-compliance': 'legal_counsel',
  legal_compliance: 'legal_counsel',
  'creative-director': 'creative_director',
  'project-manager': 'project_manager',
};
export const normalizeRole = (id) => {
  const k = String(id ?? '').trim();
  return ROLE_ALIASES[k] || k;
};
const FOLDERS = { US: 'historias', ADR: 'decisiones', MEET: 'reuniones', MEM: 'memoria' };
const STATUS_LABEL = { backlog: 'Backlog', en_progreso: 'En progreso', revision: 'En revisión', completada: 'Completada' };
const STATUS_ORDER = ['en_progreso', 'revision', 'backlog', 'completada'];

// ───────────────────────────── argumentos ─────────────────────────────
function parseArgs(argv) {
  const o = { vault: '', project: '', codigo: 'codigo', root: process.cwd(), dryRun: false, detail: true, help: false };
  const val = (i, a) => {
    const eq = a.indexOf('=');
    if (eq > -1) return [a.slice(eq + 1), i];
    if (i + 1 >= argv.length) throw new Error(`Falta el valor de ${a}`);
    return [argv[i + 1], i + 1];
  };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    const key = a.split('=')[0];
    if (key === '--vault') [o.vault, i] = val(i, a);
    else if (key === '--project') [o.project, i] = val(i, a);
    else if (key === '--codigo') [o.codigo, i] = val(i, a);
    else if (key === '--root') [o.root, i] = val(i, a);
    else if (a === '--dry-run') o.dryRun = true;
    else if (a === '--no-detail') o.detail = false;
    else if (a === '-h' || a === '--help') o.help = true;
    else throw new Error(`Opción desconocida: ${a}`);
  }
  return o;
}

const USAGE = `Uso: node bitacora.mjs --vault <carpeta> [--project <id>] [--codigo codigo] [--root <carpeta>] [--dry-run] [--no-detail]
  --vault     carpeta del vault de Obsidian (p. ej. .agileforge/vault). Obligatoria.
  --project   id del proyecto del tablero (por defecto, el activo).
  --codigo    subcarpeta del vault con las notas de graphify (por defecto: codigo).
  --root      raíz del proyecto, para mostrar rutas relativas (por defecto: carpeta actual).
  --dry-run   muestra qué haría sin escribir nada.
  --no-detail no pide el detalle de cada historia (sin comentarios; menos peticiones).`;

// ───────────────────────────── configuración (mismo orden que el servidor MCP) ─────────────────────────────
function clean(v) {
  if (typeof v !== 'string') return '';
  const s = v.trim().replace(/^["']|["']$/g, '').trim();
  return s === '' || s.includes('${') ? '' : s;
}

function parseEnvFile(file) {
  const out = {};
  try {
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
      const m = line.match(/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)$/);
      if (!m) continue;
      let v = m[2];
      if (!/^["']/.test(v)) v = v.replace(/\s+#.*$/, '');
      const c = clean(v);
      if (c) out[m[1]] = c;
    }
  } catch {
    /* ilegible: se ignora */
  }
  return out;
}

// Mínimo privilegio: primero el token de vista (solo lectura), luego el completo.
const TOKEN_KEYS = ['AGILEFORGE_VIEW_TOKEN', 'AGILEFORGE_API_TOKEN'];

export function loadConfig(env = process.env, cwd = process.cwd()) {
  let url = '';
  let urlOrigin = '';
  let token = '';
  let tokenKind = 'ninguno';
  let tokenOrigin = 'no configurado';
  const setUrl = (v, origin) => { if (!url && v) { url = v; urlOrigin = origin; } };
  const setToken = (src, origin) => {
    if (token) return;
    for (const k of TOKEN_KEYS) {
      if (src[k]) { token = src[k]; tokenKind = k === 'AGILEFORGE_VIEW_TOKEN' ? 'vista' : 'completo'; tokenOrigin = origin; return; }
    }
  };

  const envVars = {};
  for (const k of ['AGILEFORGE_API_URL', ...TOKEN_KEYS]) { const c = clean(env[k]); if (c) envVars[k] = c; }
  setUrl(envVars.AGILEFORGE_API_URL, 'variable de entorno AGILEFORGE_API_URL');
  setToken(envVars, 'variable de entorno');

  const home = clean(env.AGILEFORGE_HOME);
  if (home && fs.existsSync(path.join(home, '.env'))) {
    const vars = parseEnvFile(path.join(home, '.env'));
    setUrl(vars.AGILEFORGE_API_URL, 'AGILEFORGE_HOME/.env');
    setToken(vars, 'AGILEFORGE_HOME/.env');
  }

  const starts = [clean(env.AGILEFORGE_PROJECT_DIR), clean(env.CLAUDE_PROJECT_DIR), cwd].filter(Boolean);
  for (const s of [...new Set(starts)]) {
    let dir = path.resolve(s);
    for (let i = 0; i < 6; i++) {
      const f = path.join(dir, '.env');
      if (fs.existsSync(f)) {
        const vars = parseEnvFile(f);
        if (TOKEN_KEYS.some((k) => vars[k])) { setUrl(vars.AGILEFORGE_API_URL, '.env del proyecto'); setToken(vars, '.env del proyecto'); break; }
      }
      const parent = path.dirname(dir);
      if (parent === dir) break;
      dir = parent;
    }
    if (url && token) break;
  }

  if (!url || !token) {
    const f = path.join(os.homedir(), '.agileforge', 'config.json');
    try {
      if (fs.existsSync(f)) {
        const j = JSON.parse(fs.readFileSync(f, 'utf8'));
        setUrl(clean(j.api_url), '~/.agileforge/config.json');
        const vars = {};
        if (clean(j.view_token)) vars.AGILEFORGE_VIEW_TOKEN = clean(j.view_token);
        if (clean(j.api_token)) vars.AGILEFORGE_API_TOKEN = clean(j.api_token);
        setToken(vars, '~/.agileforge/config.json');
      }
    } catch {
      /* JSON inválido: se ignora */
    }
  }
  if (!url) { url = DEFAULT_API_URL; urlOrigin = 'valor por defecto'; }
  return { apiUrl: url.replace(/\/+$/, ''), urlOrigin, token, tokenKind, tokenOrigin };
}

// ───────────────────────────── cliente HTTP (solo GET) ─────────────────────────────
class Api {
  constructor(cfg) {
    this.cfg = cfg;
    this.count = 0;
    this.last = 0;
    const n = Number(process.env.AGILEFORGE_BITACORA_INTERVAL_MS);
    this.interval = Number.isFinite(n) && n >= 0 ? n : 340; // ≤ ~176 peticiones/min: por debajo del límite por defecto (180)
  }

  url(route, params = {}) {
    const base = this.cfg.apiUrl;
    let r = route.replace(/^\/+/, '');
    if (!/\/index\.php$/.test(base)) r = `index.php/${r}`;
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
    const q = qs.toString();
    return `${base}/${r}${q ? `?${q}` : ''}`;
  }

  async get(route, params, { optional = false } = {}) {
    const url = this.url(route, params);
    for (let attempt = 0; attempt < 3; attempt++) {
      const wait = this.last + this.interval - Date.now();
      if (wait > 0) await sleep(wait);
      this.last = Date.now();
      this.count++;
      let res;
      try {
        const headers = { Accept: 'application/json' };
        if (this.cfg.token) { headers.Authorization = `Bearer ${this.cfg.token}`; headers['X-AgileForge-Token'] = this.cfg.token; }
        res = await fetch(url, { headers, signal: AbortSignal.timeout(TIMEOUT_MS) });
      } catch (e) {
        throw new Error(`No pude conectar con ${url}${e?.cause?.code ? ` (${e.cause.code})` : ''}. ¿Está la web de AgileForge en marcha? Configura AGILEFORGE_API_URL (origen actual: ${this.cfg.urlOrigin}).`);
      }
      if (res.status === 429 && attempt < 2) {
        const ra = Math.min(65, Math.max(1, Number(res.headers.get('retry-after')) || 30));
        process.stderr.write(`Límite de peticiones de la API (429): espero ${ra} s…\n`);
        await sleep(ra * 1000);
        continue;
      }
      const text = await res.text();
      let data = null;
      try { data = JSON.parse(text); } catch { /* no JSON */ }
      if (res.status === 401) {
        throw new Error(this.cfg.token
          ? `No autorizado (401): el token (${this.cfg.tokenKind}, origen: ${this.cfg.tokenOrigin}) no fue aceptado.`
          : 'No autorizado (401): no hay token. Define AGILEFORGE_VIEW_TOKEN o AGILEFORGE_API_TOKEN, o abre la sesión dentro de la carpeta de la app.');
      }
      if (optional && (res.status === 403 || res.status === 404)) return null;
      if (!data) throw new Error(`${url} no responde como API de AgileForge (HTTP ${res.status}).`);
      if (!res.ok || data.success === false) throw new Error(`${data.error || data.message || `HTTP ${res.status}`} [HTTP ${res.status} ${route}]`);
      return data;
    }
    throw new Error(`La API siguió respondiendo 429 en ${route}.`);
  }
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ───────────────────────────── utilidades de Markdown ─────────────────────────────
const sha = (s) => crypto.createHash('sha256').update(s).digest('hex').slice(0, 16);
const yamlStr = (v) => JSON.stringify(String(v ?? '')); // JSON escapado es YAML válido entre comillas dobles
const tagSafe = (s) => String(s ?? '').normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9_/-]+/g, '-').replace(/^-+|-+$/g, '') || 'sin-dato';

/** Texto del tablero → Markdown inofensivo: sin HTML activo, sin wikilinks ni transclusiones falsas, sin títulos ni separadores accidentales. */
export function mdText(s) {
  return String(s ?? '')
    .replace(/\r\n?/g, '\n')
    .replace(/</g, '&lt;')
    .replace(/\[\[/g, '[\\[')
    .replace(/\]\]/g, ']\\]')
    .split('\n')
    .map((l) => (/^\s*#/.test(l) || /^\s*(-{3,}|={3,}|%%)\s*$/.test(l) ? `\\${l.trimStart()}` : l))
    .join('\n')
    .trim();
}

/** Convierte menciones US-…, ADR-…, MEET-… y MEM-… en enlaces a su nota (Obsidian las resuelve aunque la nota llegue después). */
export function linkify(md, selfId = '') {
  return md.replace(/\b(US|ADR|MEET|MEM)-([A-Za-z0-9]{1,40})\b/g, (m, kind) => (m === selfId ? m : `[[bitacora/${FOLDERS[kind]}/${m}|${m}]]`));
}

const text = (s, selfId) => linkify(mdText(s), selfId);
/** Texto visible de un [[enlace|alias]]: sin corchetes ni barras que romperían el enlace. */
const alias = (s, max = 90) => oneLine(String(s ?? '').replace(/[[\]|]+/g, ' '), max) || '—';
const oneLine = (s, max = 160) => {
  const t = mdText(s).replace(/\s+/g, ' ');
  return t.length > max ? `${t.slice(0, max - 1)}…` : t;
};

function frontmatter(fields, tags) {
  const lines = ['---'];
  for (const [k, v] of Object.entries(fields)) {
    if (v === undefined || v === null || v === '') continue;
    lines.push(`${k}: ${typeof v === 'number' ? v : yamlStr(v)}`);
  }
  lines.push('tags:');
  for (const t of tags) lines.push(`  - ${tagSafe(t)}`);
  lines.push(`generado_por: ${MARK}`, '---', '');
  return lines.join('\n');
}

const roleLink = (ctx, rawId) => {
  const id = normalizeRole(rawId);
  return id && ROLE_RE.test(id) ? `[[bitacora/roles/${id}|${alias(ctx.roleName(id), 60)}]]` : mdText(rawId || '—');
};
const noteLink = (kind, id, label) => `[[bitacora/${FOLDERS[kind]}/${id}|${label ? alias(label) : id}]]`;

// ───────────────────────────── mapa del código (notas de graphify) ─────────────────────────────
/** source_file → nombre de la nota del ARCHIVO en codigo/ (la nota cuyo título es el nombre del archivo). */
export function codeIndex(dir) {
  const map = new Map();
  const communities = [];
  let canvas = false;
  if (!dir || !fs.existsSync(dir)) return { map, communities, canvas, exists: false };
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (e.isFile() && e.name === 'graph.canvas') canvas = true;
    if (!e.isFile() || !e.name.endsWith('.md')) continue;
    const stem = e.name.slice(0, -3);
    if (stem.startsWith('_COMMUNITY_')) { communities.push(stem); continue; }
    let head = '';
    try {
      const fd = fs.openSync(path.join(dir, e.name), 'r');
      const buf = Buffer.alloc(2048);
      const n = fs.readSync(fd, buf, 0, 2048, 0);
      fs.closeSync(fd);
      head = buf.slice(0, n).toString('utf8');
    } catch { continue; }
    const sf = head.match(/^source_file:\s*"([^"\n]*)"/m)?.[1];
    const title = head.match(/^# (.+)$/m)?.[1]?.trim();
    if (!sf || !title) continue;
    const norm = sf.replace(/\\/g, '/').replace(/^\.\//, '');
    if (path.posix.basename(norm) === title && !map.has(norm)) map.set(norm, stem);
  }
  communities.sort((a, b) => a.localeCompare(b));
  return { map, communities, canvas, exists: true };
}

function fileRef(ctx, file) {
  const raw = String(file ?? '').trim();
  // "api/Database.php (migración v5)" → se enlaza la ruta y se conserva la nota
  const m = raw.match(/^(\S+?)(\s+\(.*\))?$/);
  const note = m?.[2] ? m[2].trim() : '';
  let p = (m ? m[1] : raw).replace(/\\/g, '/');
  const root = ctx.root.replace(/\\/g, '/').replace(/\/+$/, '');
  if (root && p.toLowerCase().startsWith(`${root.toLowerCase()}/`)) p = p.slice(root.length + 1);
  p = p.replace(/^\.\//, '');
  let stem = ctx.code.map.get(p);
  if (!stem) {
    const lower = p.toLowerCase();
    for (const [sf, st] of ctx.code.map) {
      const l = sf.toLowerCase();
      if (lower === l || lower.endsWith(`/${l}`)) { stem = st; break; }
    }
  }
  const shown = p.replace(/`/g, "'");
  const extra = note ? ` ${mdText(note)}` : '';
  return stem ? `[[${ctx.codigo}/${stem}|${alias(shown, 160)}]]${extra}` : `\`${raw.replace(/`/g, "'")}\``;
}

// ───────────────────────────── notas ─────────────────────────────
function storyNote(ctx, t) {
  const assigned = normalizeRole(t.assigned_to);
  const ev = t.review_evidence && typeof t.review_evidence === 'object' ? t.review_evidence : null;
  const out = [frontmatter({
    id: t.id, tipo: 'historia', titulo: t.title, estado: t.status, prioridad: t.priority, puntos: Number(t.story_points) || 0,
    rol: assigned, proyecto: ctx.project.id, creada: t.created_at, actualizada: t.updated_at,
  }, ['agileforge/historia', `estado/${t.status}`, `prioridad/${t.priority}`, `rol/${assigned}`])];
  out.push(`# ${t.id} · ${oneLine(t.title, 200)}`, '');
  if (t.user_story) out.push(`> ${text(t.user_story, t.id).replace(/\n/g, '\n> ')}`, '');
  out.push(`**Rol:** ${roleLink(ctx, assigned)} · **Estado:** ${STATUS_LABEL[t.status] || mdText(t.status)} · **Prioridad:** ${mdText(t.priority)} · **Puntos:** ${Number(t.story_points) || 0}`, '');
  if (t.dod) out.push('## Definition of Done', '', text(t.dod, t.id), '');
  if (t.description) out.push('## Descripción', '', text(t.description, t.id), '');
  if (Array.isArray(t.skills) && t.skills.length) out.push(`**Skills:** ${t.skills.map((s) => `\`${mdText(s)}\``).join(', ')}`, '');
  if (ev) {
    out.push('## Evidencia de entrega', '');
    if (ev.summary) out.push(`- **Resumen:** ${text(ev.summary, t.id).replace(/\n/g, ' ')}`);
    if (Array.isArray(ev.files_changed) && ev.files_changed.length) out.push(`- **Archivos:** ${ev.files_changed.slice(0, 60).map((f) => fileRef(ctx, f)).join(' · ')}`);
    if (Array.isArray(ev.tests_run) && ev.tests_run.length) out.push(`- **Pruebas:** ${ev.tests_run.slice(0, 30).map((x) => `\`${String(x).replace(/`/g, "'").replace(/\s+/g, ' ').slice(0, 200)}\``).join(' · ')}`);
    if (ev.provenance?.watermark) out.push(`- **Sello:** \`${mdText(ev.provenance.watermark)}\``);
    out.push('');
  }
  if (Array.isArray(t.comments) && t.comments.length) {
    out.push('## Comentarios', '');
    for (const c of t.comments.slice(-50)) {
      const who = c.author_role && ROLE_RE.test(c.author_role) ? roleLink(ctx, c.author_role) : mdText(c.author_name || c.author_role || '—');
      out.push(`- ${mdText(c.created_at)} · ${who}: ${text(c.content, t.id).replace(/\n+/g, ' ')}`);
    }
    out.push('');
  }
  return out.join('\n');
}

function decisionNote(ctx, d) {
  const id = d.code || d.id;
  const out = [frontmatter({ id, tipo: 'decision', titulo: d.title, estado: d.status, proyecto: d.project_id || ctx.project.id, creada: d.created_at },
    ['agileforge/decision', `adr/${d.status || 'sin-estado'}`])];
  out.push(`# ${id} · ${oneLine(d.title, 200)}`, '', `**Estado:** ${mdText(d.status || '—')} · **Fecha:** ${mdText(d.created_at || '—')}`, '');
  if (d.context) out.push('## Contexto', '', text(d.context, id), '');
  if (d.decision) out.push('## Decisión', '', text(d.decision, id), '');
  if (d.consequences) out.push('## Consecuencias', '', text(d.consequences, id), '');
  return out.join('\n');
}

function meetingNote(ctx, m) {
  const parts = Array.isArray(m.participants) ? [...new Set(m.participants.map(normalizeRole).filter(Boolean))] : [];
  const out = [frontmatter({ id: m.id, tipo: 'reunion', titulo: m.title, clase: m.type, proyecto: m.project_id || ctx.project.id, creada: m.created_at, cerrada: m.closed_at },
    ['agileforge/reunion', `reunion/${m.type || 'general'}`])];
  out.push(`# ${m.id} · ${oneLine(m.title, 200)}`, '');
  out.push(`**Tipo:** ${mdText(m.type || '—')} · **Fecha:** ${mdText(m.created_at || '—')}${m.closed_at ? ` · **Cerrada:** ${mdText(m.closed_at)}` : ''}`, '');
  if (parts.length) out.push(`**Participantes:** ${parts.map((p) => roleLink(ctx, p)).join(' · ')}`, '');
  if (m.topic) out.push('## Tema', '', text(m.topic, m.id), '');
  if (m.summary) out.push('## Resumen', '', text(m.summary, m.id), '');
  if (m.resolution) out.push('## Resolución', '', text(m.resolution, m.id), '');
  const items = Array.isArray(m.action_items) ? m.action_items : [];
  if (items.length) {
    out.push('## Acuerdos', '');
    for (const a of items.slice(0, 40)) out.push(`- ${oneLine(a.title, 200)}${a.assigned_to ? ` → ${roleLink(ctx, a.assigned_to)}` : ''}${a.story_points ? ` (${Number(a.story_points) || 0} pts)` : ''}`);
    out.push('');
  }
  const turns = Array.isArray(m.turns) ? m.turns : [];
  if (turns.length) {
    out.push('## Turnos', '');
    for (const tr of turns.slice(0, 80)) out.push(`- ${tr.role_id && ROLE_RE.test(tr.role_id) ? roleLink(ctx, tr.role_id) : mdText(tr.name || '—')}: ${text(tr.statement, m.id).replace(/\n+/g, ' ')}`);
    out.push('');
  }
  return out.join('\n');
}

function memoryNote(ctx, m) {
  const out = [frontmatter({ id: m.id, tipo: 'memoria', titulo: m.title, clase: m.node_type, rol: m.role_id, importancia: Number(m.importance_score) || 0, proyecto: m.project_id || ctx.project.id, actualizada: m.updated_at || m.created_at },
    ['agileforge/memoria', `memoria/${m.node_type || 'nota'}`])];
  out.push(`# ${m.id} · ${oneLine(m.title, 200)}`, '');
  out.push(`**Tipo:** ${mdText(m.node_type || '—')} · **Rol:** ${m.role_id === 'all' ? 'todo el equipo' : roleLink(ctx, m.role_id)} · **Importancia:** ${Number(m.importance_score) || 0}/10`, '');
  out.push(text(m.content_text, m.id), '');
  return out.join('\n');
}

function byStatus(tasks) {
  const g = {};
  for (const t of tasks) (g[t.status] ||= []).push(t);
  return STATUS_ORDER.filter((s) => g[s]?.length).map((s) => [s, g[s]]).concat(Object.keys(g).filter((s) => !STATUS_ORDER.includes(s)).map((s) => [s, g[s]]));
}

function roleNote(ctx, roleId, tasks, meetings, memories) {
  const out = [frontmatter({ id: roleId, tipo: 'rol', nombre: ctx.roleName(roleId), proyecto: ctx.project.id }, ['agileforge/rol'])];
  out.push(`# ${oneLine(ctx.roleName(roleId), 120)}`, '', `\`${roleId}\``, '');
  const desc = ctx.roles.get(roleId)?.description;
  if (desc) out.push(text(desc), '');
  if (tasks.length) {
    out.push('## Historias', '');
    for (const [st, list] of byStatus(tasks)) {
      out.push(`### ${STATUS_LABEL[st] || mdText(st)}`, '');
      for (const t of list) out.push(`- ${noteLink('US', t.id, `${t.id} · ${t.title}`)}`);
      out.push('');
    }
  }
  if (meetings.length) { out.push('## Reuniones', '', ...meetings.map((m) => `- ${noteLink('MEET', m.id, `${m.id} · ${m.title}`)}`), ''); }
  if (memories.length) { out.push('## Memoria', '', ...memories.map((m) => `- ${noteLink('MEM', m.id, `${m.id} · ${m.title}`)}`), ''); }
  return out.join('\n');
}

function sprintNote(ctx, sp, tasks, now) {
  const out = [frontmatter({ tipo: 'sprint', numero: Number(sp.number) || 0, nombre: sp.name, proyecto: ctx.project.id, instantanea: now },
    ['agileforge/sprint'])];
  out.push(`# Sprint ${Number(sp.number) || 0} · ${oneLine(sp.name, 160)}`, '');
  out.push(`> Instantánea del ${now}. Las notas de sprints anteriores se conservan como historial.`, '');
  if (sp.goal) out.push(`**Objetivo:** ${text(sp.goal)}`, '');
  out.push(`**Avance:** ${Number(sp.points_done) || 0}/${Number(sp.points_total) || 0} pts (${Number(sp.progress_pct) || 0}%) · **Días restantes:** ${Number(sp.days_remaining) || 0} · **Velocidad:** ${Number(sp.velocity_points) || 0}`, '');
  for (const [st, list] of byStatus(tasks)) {
    out.push(`## ${STATUS_LABEL[st] || mdText(st)} (${list.length})`, '');
    for (const t of list) out.push(`- ${noteLink('US', t.id, `${t.id} · ${t.title}`)} — ${roleLink(ctx, t.assigned_to)} · ${Number(t.story_points) || 0} pts`);
    out.push('');
  }
  return out.join('\n');
}

function indexNote(ctx, d) {
  const out = [frontmatter({ tipo: 'indice', proyecto: ctx.project.id, actualizado: d.now }, ['agileforge/indice'])];
  out.push(`# Bitácora · ${oneLine(ctx.project.name || ctx.project.id, 120)}`, '');
  out.push('> Generada desde el tablero de AgileForge por el Curador de grafos. No la edites: se regenera. Tus notas van en `notas/` y `fuentes/`.', '');
  if (ctx.sprintLink) out.push(`**Sprint actual:** ${ctx.sprintLink}`, '');
  out.push(`## Historias (${d.tasks.length})`, '');
  for (const [st, list] of byStatus(d.tasks)) {
    out.push(`### ${STATUS_LABEL[st] || mdText(st)} (${list.length})`, '');
    for (const t of list) out.push(`- ${noteLink('US', t.id, `${t.id} · ${t.title}`)} — ${roleLink(ctx, t.assigned_to)}`);
    out.push('');
  }
  if (d.decisions.length) out.push(`## Decisiones (${d.decisions.length})`, '', ...d.decisions.map((x) => `- ${noteLink('ADR', x.code || x.id, `${x.code || x.id} · ${x.title}`)} — ${mdText(x.status || '')}`), '');
  if (d.meetings.length) out.push(`## Reuniones (${d.meetings.length})`, '', ...d.meetings.map((x) => `- ${noteLink('MEET', x.id, `${x.id} · ${x.title}`)}`), '');
  if (d.memories.length) out.push(`## Memoria del equipo (${d.memories.length})`, '', ...d.memories.map((x) => `- ${noteLink('MEM', x.id, `${x.id} · ${x.title}`)}`), '');
  if (d.roleIds.length) out.push('## Roles', '', ...d.roleIds.map((r) => `- ${roleLink(ctx, r)}`), '');
  return out.join('\n');
}

/** Ficha bibliográfica (fuentes/*.md, escritas por el Curador o por ti) → entrada de la bibliografía. */
function readFicha(file) {
  let head = '';
  try { head = fs.readFileSync(file, 'utf8').slice(0, 4096); } catch { return null; }
  const fm = head.match(/^---\r?\n([\s\S]*?)\r?\n---/)?.[1] || '';
  const get = (...keys) => {
    for (const k of keys) {
      const m = fm.match(new RegExp(`^${k}:\\s*(.+)$`, 'mi'));
      if (m) return m[1].trim().replace(/^["']|["']$/g, '');
    }
    return '';
  };
  return { titulo: get('titulo', 'title') || head.match(/^# (.+)$/m)?.[1] || path.basename(file, '.md'), autor: get('autor', 'author', 'autores'), anio: get('anio', 'año', 'year', 'fecha'), tipo: get('tipo', 'type'), url: get('url', 'enlace', 'link') };
}

function fuentesIndex(dir, now) {
  const out = [frontmatter({ tipo: 'bibliografia', actualizado: now }, ['agileforge/bibliografia'])];
  out.push('# Fuentes y bibliografía', '', '> Índice generado a partir de las fichas de esta carpeta (una nota por fuente). Para añadir una, copia `_plantilla.md`.', '');
  const fichas = [];
  if (fs.existsSync(dir)) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      if (!e.isFile() || !e.name.endsWith('.md') || e.name.startsWith('_')) continue;
      const f = readFicha(path.join(dir, e.name));
      if (f) fichas.push({ ...f, stem: e.name.slice(0, -3) });
    }
  }
  fichas.sort((a, b) => (a.autor || a.titulo).localeCompare(b.autor || b.titulo));
  if (!fichas.length) out.push('_Todavía no hay fichas._', '');
  for (const f of fichas) {
    const autor = f.autor ? `${oneLine(f.autor, 120)} ` : '';
    const anio = f.anio ? `(${oneLine(f.anio, 20)}). ` : '';
    const tipo = f.tipo ? ` ${oneLine(f.tipo, 40)}.` : '';
    const url = /^https?:\/\/\S+$/i.test(f.url) ? ` <${f.url.replace(/[<>\s]/g, '')}>` : '';
    out.push(`- ${autor}${anio}*${oneLine(f.titulo, 200)}*.${tipo}${url} — [[fuentes/${f.stem.replace(/[[\]|#^]/g, '')}|ficha]]`);
  }
  out.push('');
  return out.join('\n');
}

const PLANTILLA_FICHA = `---
titulo: ""
autor: ""
anio: ""
tipo: "documentación"   # documentación | artículo | libro | paper | video | repositorio | conversación
url: ""
consultada: ""
relacionado: []          # p. ej. ["US-101", "ADR-002"]
tags:
  - agileforge/fuente
---

# Título de la fuente

## Resumen
Qué dice, en 3-5 líneas y con tus palabras.

## Qué tomamos de aquí
- Idea o dato concreto → dónde se usó: [[bitacora/historias/US-101|US-101]], [[bitacora/decisiones/ADR-001|ADR-001]]

## Citas (≤ 1 por fuente, cortas)
`;

function homeNote(ctx, d) {
  const out = [frontmatter({ tipo: 'inicio', proyecto: ctx.project.id, actualizado: d.now }, ['agileforge/inicio'])];
  out.push(`# ${oneLine(ctx.project.name || ctx.project.id, 120)} · grafo bibliográfico`, '');
  out.push('Mapa de todo lo que se hizo en el proyecto, para consultarlo y citarlo como bibliografía. Ábrelo en Obsidian y usa la vista de grafo.', '');
  out.push('| carpeta | qué contiene | quién la escribe |', '|---|---|---|');
  out.push(`| \`${ctx.codigo}/\` | Mapa del código: una nota por símbolo y por comunidad, y el lienzo \`graph.canvas\` | graphify (\`graphify export obsidian\`). No editar |`);
  out.push('| `bitacora/` | Historias, decisiones (ADR), reuniones, sprints, memoria y roles del tablero | Curador de grafos (`bitacora.mjs`). No editar |');
  out.push('| `fuentes/` | Fichas bibliográficas: documentación, artículos, repos y conversaciones consultadas | Curador de grafos y tú |');
  out.push('| `notas/` | Notas propias: conceptos, aprendizajes, borradores | Tú (el Curador solo enlaza) |', '');
  out.push('## Entradas', '');
  out.push('- [[bitacora/_indice|Índice de la bitácora]]');
  if (ctx.sprintLink) out.push(`- Sprint actual: ${ctx.sprintLink}`);
  out.push('- [[fuentes/_indice|Fuentes y bibliografía]]');
  if (ctx.code.canvas) out.push(`- [[${ctx.codigo}/graph.canvas|Lienzo del grafo del código]]`);
  out.push('');
  if (ctx.code.communities.length) {
    out.push(`## Comunidades del código (${ctx.code.communities.length})`, '');
    for (const c of ctx.code.communities.slice(0, 80)) out.push(`- [[${ctx.codigo}/${c}|${alias(c.replace(/^_COMMUNITY_/, ''))}]]`);
    out.push('');
  } else {
    out.push(`> Aún no hay notas del código en \`${ctx.codigo}/\`. Las genera \`graphify export obsidian --dir <vault>/${ctx.codigo}\`.`, '');
  }
  if (d.notas.length) out.push('## Notas', '', ...d.notas.map((n) => `- [[notas/${n}|${alias(n)}]]`), '');
  out.push('## Grafo de la IA', '', 'Los agentes no leen este vault: consultan `graphify-out/graph.json` (`graphify query`, `graphify path`, `graphify explain`). El informe para humanos está en `graphify-out/GRAPH_REPORT.md`.', '');
  return out.join('\n');
}

// ───────────────────────────── escritura con dueño ─────────────────────────────
class Writer {
  constructor(vault, manifest, dryRun) {
    this.vault = vault;
    this.real = null;
    this.old = manifest.files || {};
    this.files = {};
    this.dryRun = dryRun;
    this.stats = { written: 0, unchanged: 0, removed: 0 };
    this.human = [];
    this.edited = [];
  }

  abs(rel) {
    const a = path.resolve(this.vault, rel);
    const r = path.relative(this.vault, a);
    if (!r || r.startsWith('..') || path.isAbsolute(r)) throw new Error(`Ruta fuera del vault: ${rel}`);
    return a;
  }

  /** ¿La nota generada sigue intacta en disco? (sirve para el modo incremental). */
  intact(rel) {
    const rec = this.old[rel];
    if (!rec) return false;
    try { return sha(fs.readFileSync(this.abs(rel), 'utf8')) === rec.hash; } catch { return false; }
  }

  keep(rel) {
    this.files[rel] = this.old[rel];
    this.stats.unchanged++;
  }

  write(rel, content, extra = {}) {
    const a = this.abs(rel);
    const rec = this.old[rel];
    if (fs.existsSync(a)) {
      const cur = fs.readFileSync(a, 'utf8');
      if (!rec) { this.human.push(rel); return false; }
      if (sha(cur) !== rec.hash) { this.edited.push(rel); this.files[rel] = rec; return false; }
      if (cur === content) { this.files[rel] = { ...rec, ...extra }; this.stats.unchanged++; return true; }
    }
    if (!this.dryRun) {
      fs.mkdirSync(path.dirname(a), { recursive: true });
      // Un enlace simbólico dentro del vault no puede sacar la escritura fuera de él
      this.real ||= fs.realpathSync(this.vault);
      const rd = path.relative(this.real, fs.realpathSync(path.dirname(a)));
      if (rd.startsWith('..') || path.isAbsolute(rd)) throw new Error(`Ruta fuera del vault (enlace simbólico): ${rel}`);
      const tmp = `${a}.tmp-${process.pid}`;
      fs.writeFileSync(tmp, content, 'utf8');
      fs.renameSync(tmp, a);
    }
    this.files[rel] = { hash: sha(content), ...extra };
    this.stats.written++;
    return true;
  }

  prune() {
    for (const [rel, rec] of Object.entries(this.old)) {
      if (this.files[rel] || rec.keep) { if (rec.keep && !this.files[rel]) this.files[rel] = rec; continue; }
      let a;
      try { a = this.abs(rel); } catch { continue; }
      if (!fs.existsSync(a)) continue;
      if (sha(fs.readFileSync(a, 'utf8')) !== rec.hash) { this.edited.push(rel); continue; } // editada a mano: pasa a ser tuya
      if (!this.dryRun) fs.rmSync(a);
      this.stats.removed++;
    }
  }
}

function loadManifest(vault) {
  try {
    const j = JSON.parse(fs.readFileSync(path.join(vault, MANIFEST), 'utf8'));
    return j && typeof j === 'object' && j.files ? j : { files: {} };
  } catch {
    return { files: {} };
  }
}

const sameProject = (pid, rowPid) => {
  const r = rowPid ?? '';
  if (r === pid) return true;
  if (pid === 'default' && (r === '' || r === null)) return true;
  return false;
};

// ───────────────────────────── programa ─────────────────────────────
export async function run(argv = process.argv.slice(2), env = process.env) {
  let o;
  try { o = parseArgs(argv); } catch (e) { console.error(`${e.message}\n${USAGE}`); return 2; }
  if (o.help) { console.log(USAGE); return 0; }
  if (!o.vault) { console.error(`Falta --vault.\n${USAGE}`); return 2; }
  if (!/^[A-Za-z0-9_.-]{1,64}$/.test(o.codigo) || o.codigo.startsWith('.')) { console.error('--codigo debe ser el nombre de una subcarpeta simple (p. ej. codigo).'); return 2; }

  const vault = path.resolve(o.vault);
  o.root = path.resolve(o.root);
  if (vault === path.parse(vault).root || vault === os.homedir() || vault === o.root || vault === process.cwd() || fs.existsSync(path.join(vault, '.git'))) {
    console.error(`El vault no puede ser la raíz del proyecto, del disco ni tu carpeta personal (${vault}). Usa una subcarpeta, p. ej. .agileforge/vault.`);
    return 2;
  }

  const cfg = loadConfig(env, process.cwd());
  const api = new Api(cfg);
  const now = new Date().toISOString().replace(/\.\d{3}Z$/, 'Z');

  try {
    const ov = await api.get('overview', { project: o.project || undefined });
    const project = { id: ov.project?.id || o.project || 'default', name: ov.project?.name || '' };
    if (!ID_RE.test(project.id)) throw new Error(`Id de proyecto no válido: ${project.id}`);

    const rolesResp = await api.get('roles', undefined, { optional: true }); // 403 con el token de vista: se usan los nombres del overview
    const roles = new Map();
    for (const r of rolesResp?.roles || []) if (r?.id) roles.set(r.id, { name: r.name, description: r.description });
    for (const t of ov.tasks || []) if (t.assigned_to && !roles.has(t.assigned_to) && t.role_name) roles.set(t.assigned_to, { name: t.role_name });

    const all = (await api.get('tasks')).tasks || [];
    const tasks = all.filter((t) => sameProject(project.id, t.project_id) && ID_RE.test(String(t.id || '')));
    const decisions = ((await api.get('decisions', { limit: 200 })).decisions || [])
      .filter((d) => sameProject(project.id, d.project_id) && ID_RE.test(String(d.code || d.id || '')));
    const meetings = ((await api.get('meetings', { limit: 100 })).meetings || [])
      .filter((m) => sameProject(project.id, m.project_id) && ID_RE.test(String(m.id || '')));
    const memories = ((await api.get('memories', { project: project.id, limit: 100, active_only: 1 }, { optional: true }))?.memories || [])
      .filter((m) => ID_RE.test(String(m.id || '')));

    const roleIds = [...new Set([
      ...tasks.map((t) => normalizeRole(t.assigned_to)),
      ...meetings.flatMap((m) => (Array.isArray(m.participants) ? m.participants.map(normalizeRole) : [])),
      ...memories.map((m) => normalizeRole(m.role_id)).filter((r) => r !== 'all'),
    ])].filter((r) => r && ROLE_RE.test(r)).sort();
    // Sin /roles (token de vista): el nombre sale del brief del rol, que sí está en su lista blanca
    for (const r of roleIds) {
      if (roles.get(r)?.description || (rolesResp && roles.has(r))) continue;
      const b = (await api.get(`roles/${encodeURIComponent(r)}/brief`, undefined, { optional: true }))?.brief;
      if (b?.name) roles.set(r, { name: b.name, description: b.description });
    }

    if (!o.dryRun) fs.mkdirSync(vault, { recursive: true });
    const manifest = loadManifest(vault);
    const fresh = manifest.renderer !== RENDERER || manifest.project !== project.id;
    const cache = fresh ? {} : manifest.cache || {};
    // Otro proyecto en el mismo vault: sus notas generadas (también las instantáneas de sprint) se retiran
    const otherProject = Boolean(manifest.project) && manifest.project !== project.id;
    const oldFiles = Object.fromEntries(Object.entries(manifest.files || {}).map(([k, v]) => [k, otherProject ? { hash: v.hash } : v]));
    const w = new Writer(vault, fresh ? { files: oldFiles } : manifest, o.dryRun);
    const sp = ov.sprint && Number.isFinite(Number(ov.sprint.number)) ? ov.sprint : null;

    const ctx = {
      project, root: o.root, codigo: o.codigo, roles,
      roleName: (id) => roles.get(id)?.name || id,
      code: codeIndex(path.join(vault, o.codigo)),
      sprintLink: sp ? `[[bitacora/sprints/Sprint-${Number(sp.number)}|Sprint ${Number(sp.number)}]]` : '',
    };
    const newCache = {};

    // Historias (detalle solo si cambiaron o si la nota no está intacta)
    for (const t of tasks) {
      const rel = `bitacora/historias/${t.id}.md`;
      const stamp = `${t.updated_at || ''}|${ctx.code.map.size}`;
      newCache[`US:${t.id}`] = stamp;
      if (!fresh && cache[`US:${t.id}`] === stamp && w.intact(rel)) { w.keep(rel); continue; }
      let full = t;
      if (o.detail) {
        const det = await api.get(`tasks/${encodeURIComponent(t.id)}`, undefined, { optional: true });
        if (det?.task) full = det.task;
      }
      w.write(rel, storyNote(ctx, full));
    }
    for (const d of decisions) w.write(`bitacora/decisiones/${d.code || d.id}.md`, decisionNote(ctx, d));
    for (const m of meetings) {
      const rel = `bitacora/reuniones/${m.id}.md`;
      const stamp = String(m.updated_at || '');
      newCache[`MEET:${m.id}`] = stamp;
      if (!fresh && cache[`MEET:${m.id}`] === stamp && w.intact(rel)) { w.keep(rel); continue; }
      const det = o.detail ? await api.get(`meetings/${encodeURIComponent(m.id)}`, undefined, { optional: true }) : null;
      w.write(rel, meetingNote(ctx, det?.meeting || m));
    }
    for (const m of memories) w.write(`bitacora/memoria/${m.id}.md`, memoryNote(ctx, m));

    for (const r of roleIds) {
      w.write(`bitacora/roles/${r}.md`, roleNote(ctx, r,
        tasks.filter((t) => normalizeRole(t.assigned_to) === r),
        meetings.filter((m) => Array.isArray(m.participants) && m.participants.map(normalizeRole).includes(r)),
        memories.filter((m) => normalizeRole(m.role_id) === r)));
    }
    if (sp) w.write(`bitacora/sprints/Sprint-${Number(sp.number)}.md`, sprintNote(ctx, sp, tasks, now.slice(0, 10)), { keep: true });

    // Índices: la fecha va en el frontmatter, así que solo se reescriben si cambia el contenido real
    const stableNow = now.slice(0, 10);
    w.write('bitacora/_indice.md', indexNote(ctx, { now: stableNow, tasks, decisions, meetings, memories, roleIds }));
    if (!o.dryRun) { fs.mkdirSync(path.join(vault, 'fuentes'), { recursive: true }); fs.mkdirSync(path.join(vault, 'notas'), { recursive: true }); }
    w.write('fuentes/_plantilla.md', PLANTILLA_FICHA);
    w.write('fuentes/_indice.md', fuentesIndex(path.join(vault, 'fuentes'), stableNow));
    const notasDir = path.join(vault, 'notas');
    const notas = fs.existsSync(notasDir) ? fs.readdirSync(notasDir).filter((f) => f.endsWith('.md')).map((f) => f.slice(0, -3)).sort() : [];
    w.write('Inicio.md', homeNote(ctx, { now: stableNow, notas }));

    w.prune();
    if (!o.dryRun) {
      const tmp = path.join(vault, `${MANIFEST}.tmp-${process.pid}`);
      fs.writeFileSync(tmp, JSON.stringify({ version: 1, renderer: RENDERER, project: project.id, generated_at: now, files: w.files, cache: newCache }, null, 1), 'utf8');
      fs.renameSync(tmp, path.join(vault, MANIFEST));
    }

    console.log(`Bitácora${o.dryRun ? ' (simulación, no se escribió nada)' : ''} → ${vault}`);
    console.log(`  proyecto: ${project.id}${project.name ? ` (${project.name})` : ''} · API: ${cfg.apiUrl} · token: ${cfg.tokenKind} · peticiones: ${api.count}`);
    console.log(`  historias ${tasks.length} · decisiones ${decisions.length} · reuniones ${meetings.length} · memoria ${memories.length} · roles ${roleIds.length} · sprint ${sp ? Number(sp.number) : '—'} · notas de código enlazables ${ctx.code.map.size}`);
    console.log(`  escritas ${w.stats.written} · sin cambios ${w.stats.unchanged} · eliminadas ${w.stats.removed}`);
    if (w.edited.length) console.log(`  editadas a mano (no se sobrescriben; bórralas para regenerarlas): ${w.edited.join(', ')}`);
    if (w.human.length) console.log(`  archivos tuyos con el mismo nombre (no se tocan): ${w.human.join(', ')}`);
    if (!ctx.code.exists) console.log(`  aviso: no hay ${o.codigo}/ en el vault; los archivos de la evidencia no se enlazan al código (ejecuta antes graphify export obsidian).`);
    return 0;
  } catch (e) {
    console.error(`Bitácora: ${e.message}`);
    return 1;
  }
}

const isMain = Boolean(process.argv[1]) && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) run().then((code) => process.exit(code));
