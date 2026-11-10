/**
 * Cliente HTTP base: SOLO lectura (GET). El cliente de escritura vive en client-write.ts
 * y el bundle de Vista no lo importa.
 */
import type { Config } from './config.js';

export class ApiError extends Error {
  constructor(message: string, public status = 0) {
    super(message);
  }
}

export const TIMEOUT_MS = 15000;

export class ReadClient {
  constructor(public readonly cfg: Config, protected timeoutMs = TIMEOUT_MS) {}

  /** URL de una ruta REST, sin credenciales (se usa en los mensajes de error). */
  protected urlFor(route: string, params?: Record<string, string | number | boolean | undefined>): string {
    let base = this.cfg.apiUrl;
    let r = route.replace(/^\/+/, '');
    if (!/\/index\.php$/.test(base) && !/\.php$/.test(r)) r = `index.php/${r}`;
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params ?? {})) if (v !== undefined && v !== '') qs.set(k, String(v));
    const q = qs.toString();
    let u = `${base}/${r}`.replace(/\/index\.php\/index\.php/, '/index.php');
    return q ? `${u}?${q}` : u;
  }

  protected headers(extra: Record<string, string> = {}): Record<string, string> {
    const h: Record<string, string> = { Accept: 'application/json', ...extra };
    if (this.cfg.token) {
      h.Authorization = `Bearer ${this.cfg.token}`;
      h['X-AgileForge-Token'] = this.cfg.token;
    }
    return h;
  }

  protected connectionHint(): string {
    return `Configura AGILEFORGE_API_URL (origen actual: ${this.cfg.urlOrigin}).`;
  }

  protected tokenHint(): string {
    const env = this.cfg.mode === 'vista' ? 'AGILEFORGE_VIEW_TOKEN' : 'AGILEFORGE_API_TOKEN';
    return this.cfg.token
      ? `El token (${this.cfg.tokenKind}, origen: ${this.cfg.tokenOrigin}) no fue aceptado; revisa ${env}.`
      : `No hay token configurado: define ${env} o ponlo en .env / ~/.agileforge/config.json.`;
  }

  protected async settle(res: Response, url: string): Promise<any> {
    const text = await res.text();
    let data: any;
    try {
      data = JSON.parse(text);
    } catch {
      if (res.status === 404 || res.ok) {
        throw new ApiError(`${url} no responde como API de AgileForge (HTTP ${res.status}, no es JSON). La URL debe terminar en /api. ${this.connectionHint()}`, res.status);
      }
      throw new ApiError(`Respuesta no JSON de ${url} (HTTP ${res.status}): ${text.slice(0, 120).replace(/\s+/g, ' ')}`, res.status);
    }
    if (res.status === 401) {
      throw new ApiError(`No autorizado (401) en ${url}. ${this.tokenHint()}`, 401);
    }
    if (!res.ok || data?.success === false) {
      const msg = data?.error || data?.message || `HTTP ${res.status}`;
      const extra = res.status === 403 && this.cfg.mode === 'vista' ? ' (modo Vista: solo lectura)' : '';
      throw new ApiError(`${msg}${extra} [HTTP ${res.status} ${url}]`, res.status);
    }
    return data;
  }

  protected netError(err: any, url: string): ApiError {
    if (err instanceof ApiError) return err;
    if (err?.name === 'TimeoutError' || err?.name === 'AbortError') {
      return new ApiError(`Tiempo de espera agotado (${Math.round(this.timeoutMs / 1000)} s) con ${url}. ¿Está el servidor encendido? ${this.connectionHint()}`);
    }
    const code = err?.cause?.code ? ` (${err.cause.code})` : '';
    return new ApiError(`No pude conectar con ${url}${code}. ¿Está XAMPP/PHP encendido y la URL es correcta? ${this.connectionHint()}`);
  }

  async get<T = any>(route: string, params?: Record<string, string | number | boolean | undefined>): Promise<T> {
    const url = this.urlFor(route, params);
    try {
      const res = await fetch(url, { method: 'GET', headers: this.headers(), signal: AbortSignal.timeout(this.timeoutMs) });
      return (await this.settle(res, url)) as T;
    } catch (err: any) {
      throw this.netError(err, url);
    }
  }
}
