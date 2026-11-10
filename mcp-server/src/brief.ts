/**
 * --session-brief: resumen de <=6 líneas para el hook SessionStart.
 * Nunca lanza ni devuelve código distinto de 0; red con timeout de 4 s.
 */
import { ReadClient } from './client.js';
import type { Config } from './config.js';

const BRIEF_TIMEOUT_MS = 4000;
const cut = (s: unknown, n: number) => {
  const t = String(s ?? '').replace(/\s+/g, ' ').trim();
  return t.length > n ? `${t.slice(0, n - 1)}…` : t;
};

export function formatBrief(o: any): string[] {
  const sp = o.sprint ?? {};
  const col = o.columns ?? {};
  const n = (k: string) => col[k]?.count ?? 0;
  const tasks: any[] = o.tasks ?? [];
  const lines: string[] = [];

  lines.push(
    `AgileForge · ${cut(o.project?.name ?? o.project?.id ?? 'proyecto', 40)} · Sprint ${sp.number ?? '?'}${sp.name ? ` «${cut(String(sp.name).replace(/^Sprint\s*\d+\s*[:\-–]\s*/i, ''), 36)}»` : ''}: ` +
      `${sp.progress_pct ?? 0}% (${sp.points_done ?? 0}/${sp.points_total ?? 0} pts)` +
      (sp.days_remaining != null ? `, ${sp.days_remaining} d restantes` : '')
  );
  lines.push(`Columnas: backlog ${n('backlog')} · en progreso ${n('en_progreso')} · revisión ${n('revision')} · completadas ${n('completada')}`);

  const working: any[] = o.working ?? [];
  lines.push(
    working.length
      ? `En curso: ${working.slice(0, 3).map((w) => `${w.task_id} ${cut(w.task_title, 40)} (${w.role_id})`).join('; ')}${working.length > 3 ? ` +${working.length - 3}` : ''}`
      : 'En curso: nadie trabajando ahora.'
  );

  const rev = tasks.filter((t) => t.status === 'revision');
  lines.push(rev.length ? `Para revisar: ${rev.slice(0, 3).map((t) => `${t.id} ${cut(t.title, 40)}`).join('; ')}${rev.length > 3 ? ` +${rev.length - 3}` : ''}` : 'Para revisar: nada pendiente.');

  const m = (o.meetings ?? [])[0];
  lines.push(m ? `Última reunión: ${m.id} «${cut(m.title, 50)}»${m.resolution ? ` — ${cut(m.resolution, 90)}` : ''}` : 'Última reunión: ninguna.');
  const d = (o.decisions ?? [])[0];
  lines.push(d ? `Última decisión: ${d.id} ${cut(d.title, 60)} (${d.status ?? '-'})` : 'Última decisión: ninguna.');
  return lines;
}

export async function runBrief(cfg: Config): Promise<string> {
  const client = new ReadClient(cfg, BRIEF_TIMEOUT_MS);
  try {
    const o = await client.get('overview');
    return formatBrief(o).join('\n');
  } catch (e: any) {
    const st = e?.status ?? 0;
    const reason =
      st === 401 ? 'token no aceptado' :
      st === 403 ? 'acceso denegado' :
      st === 404 ? 'la API no tiene /overview (¿app desactualizada?)' :
      st === 0 ? (/Tiempo de espera/.test(String(e?.message)) ? 'tiempo de espera de 4 s agotado' : 'no pude conectar') :
      cut(String(e?.message ?? e).split(/\s\[HTTP/)[0], 100);
    return [
      `AgileForge: sin resumen (${reason}).`,
      `URL probada: ${cfg.apiUrl} (origen: ${cfg.urlOrigin}). Configura AGILEFORGE_API_URL / AGILEFORGE_API_TOKEN o ~/.agileforge/config.json; la herramienta connection_info diagnostica.`,
    ].join('\n');
  }
}

/** Imprime el brief y sale con 0 pase lo que pase (también si algo cuelga >5 s). */
export async function sessionBriefMain(cfg: Config): Promise<never> {
  const guard = setTimeout(() => process.exit(0), BRIEF_TIMEOUT_MS + 1000);
  guard.unref?.();
  try {
    process.stdout.write((await runBrief(cfg)) + '\n');
  } catch {
    /* nada */
  }
  process.exit(0);
}
