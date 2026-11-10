/**
 * Descubrimiento de configuración (ver contrato API ↔ MCP ↔ Vista).
 * Orden: 1) variables de entorno, 2) $AGILEFORGE_HOME/.env, 3) .env hacia arriba
 * desde el proyecto y el cwd (máx. 6 niveles), 4) ~/.agileforge/config.json, 5) valor por defecto.
 * Un valor vacío o con "${" sin expandir cuenta como ausente. El token nunca se imprime.
 */
import fs from 'fs';
import os from 'os';
import path from 'path';

export type Mode = 'control' | 'vista';

export interface Config {
  mode: Mode;
  apiUrl: string;
  urlOrigin: string;
  token: string;
  tokenOrigin: string;
  tokenKind: 'completo' | 'vista' | 'ninguno';
  client: string;
  engineTools: boolean;
}

export const DEFAULT_API_URL = 'http://localhost/Open-to-code/api';
const MAX_LEVELS = 6;

function clean(v: unknown): string {
  if (typeof v !== 'string') return '';
  const s = v.trim().replace(/^["']|["']$/g, '').trim();
  return s === '' || s.includes('${') ? '' : s;
}

function parseEnvFile(file: string): Record<string, string> {
  const out: Record<string, string> = {};
  try {
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
      const m = line.match(/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)$/);
      if (!m) continue;
      let val = m[2];
      if (!/^["']/.test(val)) val = val.replace(/\s+#.*$/, '');
      const c = clean(val);
      if (c) out[m[1]] = c;
    }
  } catch {
    /* ilegible: se ignora */
  }
  return out;
}

function tokenKeys(mode: Mode): string[] {
  return mode === 'vista' ? ['AGILEFORGE_VIEW_TOKEN', 'AGILEFORGE_API_TOKEN'] : ['AGILEFORGE_API_TOKEN'];
}

function pickToken(src: Record<string, string>, mode: Mode): { token: string; key: string } | null {
  for (const key of tokenKeys(mode)) if (src[key]) return { token: src[key], key };
  return null;
}

function kindOf(key: string): 'completo' | 'vista' {
  return key === 'AGILEFORGE_VIEW_TOKEN' || key === 'view_token' ? 'vista' : 'completo';
}

function findUp(startDir: string, mode: Mode): { file: string; vars: Record<string, string> } | null {
  let dir = path.resolve(startDir);
  for (let i = 0; i < MAX_LEVELS; i++) {
    const file = path.join(dir, '.env');
    if (fs.existsSync(file)) {
      const vars = parseEnvFile(file);
      if (pickToken(vars, mode)) return { file, vars };
    }
    const parent = path.dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }
  return null;
}

export function loadConfig(mode: Mode): Config {
  const env = process.env;
  let url = '';
  let urlOrigin = '';
  let token = '';
  let tokenOrigin = '';
  let tokenKind: Config['tokenKind'] = 'ninguno';

  const setUrl = (v: string | undefined, origin: string) => {
    if (!url && v) {
      url = v;
      urlOrigin = origin;
    }
  };
  const setToken = (src: Record<string, string>, origin: string) => {
    if (token) return;
    const p = pickToken(src, mode);
    if (p) {
      token = p.token;
      tokenOrigin = origin;
      tokenKind = kindOf(p.key);
    }
  };

  // 1. Entorno
  const envVars: Record<string, string> = {};
  for (const k of ['AGILEFORGE_API_URL', 'AGILEFORGE_API_TOKEN', 'AGILEFORGE_VIEW_TOKEN']) {
    const c = clean(env[k]);
    if (c) envVars[k] = c;
  }
  setUrl(envVars.AGILEFORGE_API_URL, 'variable de entorno AGILEFORGE_API_URL');
  setToken(envVars, 'variable de entorno');

  // 2. $AGILEFORGE_HOME/.env
  const home = clean(env.AGILEFORGE_HOME);
  if (home) {
    const file = path.join(home, '.env');
    if (fs.existsSync(file)) {
      const vars = parseEnvFile(file);
      setUrl(vars.AGILEFORGE_API_URL, `${file} (AGILEFORGE_HOME)`);
      setToken(vars, `${file} (AGILEFORGE_HOME)`);
    }
  }

  // 3. .env hacia arriba (proyecto y cwd)
  const starts = [clean(env.AGILEFORGE_PROJECT_DIR), clean(env.CLAUDE_PROJECT_DIR), process.cwd()].filter(Boolean);
  const seen = new Set<string>();
  for (const s of starts) {
    if (seen.has(s)) continue;
    seen.add(s);
    const found = findUp(s, mode);
    if (found) {
      setUrl(found.vars.AGILEFORGE_API_URL, found.file);
      setToken(found.vars, found.file);
    }
    if (url && token) break;
  }

  // 4. ~/.agileforge/config.json
  if (!url || !token) {
    const file = path.join(os.homedir(), '.agileforge', 'config.json');
    try {
      if (fs.existsSync(file)) {
        const j = JSON.parse(fs.readFileSync(file, 'utf8')) as Record<string, unknown>;
        setUrl(clean(j.api_url), file);
        const vars: Record<string, string> = {};
        const vt = clean(j.view_token);
        const at = clean(j.api_token);
        if (vt) vars.AGILEFORGE_VIEW_TOKEN = vt;
        if (at) vars.AGILEFORGE_API_TOKEN = at;
        // en modo control el token de vista no sirve: pickToken solo mira las claves del modo
        setToken(vars, file);
      }
    } catch {
      /* JSON inválido: se ignora */
    }
  }

  // 5. Defecto
  if (!url) {
    url = DEFAULT_API_URL;
    urlOrigin = 'valor por defecto';
  }

  return {
    mode,
    apiUrl: url.replace(/\/+$/, ''),
    urlOrigin,
    token,
    tokenOrigin: token ? tokenOrigin : 'no configurado',
    tokenKind,
    client: clean(env.AGILEFORGE_CLIENT) || 'claude-code',
    engineTools: clean(env.AGILEFORGE_ENGINE_TOOLS).toLowerCase() === 'on',
  };
}

/** Modo pedido por AGILEFORGE_MODE (el bundle completo puede degradarse a lector, nunca al revés). */
export function envWantsView(): boolean {
  return ['view', 'viewer', 'vista', 'readonly', 'lector'].includes(clean(process.env.AGILEFORGE_MODE).toLowerCase());
}
