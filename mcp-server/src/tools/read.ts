import { McpServer, ResourceTemplate } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import type { ReadClient } from '../client.js';
import { READ_ONLY, ok, run } from './util.js';

export const PRIORITIES = ['critica', 'alta', 'media', 'baja'] as const;
export const STATUSES = ['backlog', 'en_progreso', 'revision', 'completada'] as const;

export function registerReadTools(server: McpServer, c: ReadClient): void {
  const cfg = c.cfg;

  server.registerTool('get_overview', {
    description: 'Panorama completo del proyecto en una llamada: sprint, columnas, tareas, quién trabaja en qué, reuniones, decisiones y actividad reciente.',
    inputSchema: { project: z.string().optional().describe('Id de proyecto (por defecto, el activo)') },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get('overview', { project: a.project }), ['success']));

  server.registerTool('get_sprint_status', {
    description: 'Estado del sprint: objetivo, días restantes, velocidad y puntos por columna.',
    inputSchema: {},
    annotations: READ_ONLY,
  }, () => run(() => c.get('sprint/status')));

  server.registerTool('list_tasks', {
    description: 'Lista historias del tablero (forma compacta por defecto). Filtros opcionales.',
    inputSchema: {
      status: z.enum(STATUSES).optional().describe('Columna'),
      role: z.string().optional().describe('Rol asignado, ej. backend_architect'),
      priority: z.enum(PRIORITIES).optional(),
      project: z.string().optional(),
      compact: z.boolean().default(true).describe('false = datos completos (más tokens)'),
    },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get('tasks', { status: a.status, role: a.role, priority: a.priority, project: a.project, compact: a.compact === false ? undefined : 1 }), ['success']));

  server.registerTool('get_task', {
    description: 'Detalle de una historia: criterios, DoD, comentarios y eventos.',
    inputSchema: { task_id: z.string().describe('Ej. US-101') },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get(`tasks/${encodeURIComponent(a.task_id)}`)));

  server.registerTool('list_meetings', {
    description: 'Lista reuniones recientes (compacto: sin turnos completos).',
    inputSchema: {
      limit: z.number().int().min(1).max(50).default(5),
      compact: z.boolean().default(true).describe('false = incluye turnos completos'),
    },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get('meetings', { limit: a.limit, compact: a.compact === false ? undefined : 1 }), ['success']));

  server.registerTool('get_meeting', {
    description: 'Acta completa de una reunión con todos sus turnos.',
    inputSchema: { meeting_id: z.string().describe('Ej. MEET-001') },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get(`meetings/${encodeURIComponent(a.meeting_id)}`)));

  server.registerTool('list_decisions', {
    description: 'Decisiones de arquitectura (ADR) más recientes.',
    inputSchema: { limit: z.number().int().min(1).max(100).default(10) },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get('decisions', { limit: a.limit }), ['success']));

  server.registerTool('get_activity', {
    description: 'Actividad reciente del equipo (eventos del tablero), más nueva primero.',
    inputSchema: { limit: z.number().int().min(1).max(100).default(30) },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get('events', { limit: a.limit }), ['success']));

  server.registerTool('get_role_brief', {
    description: 'Prompt oficial de un rol, su especialidad y skills activas.',
    inputSchema: { role_id: z.string().describe('Ej. project_manager, backend_architect') },
    annotations: READ_ONLY,
  }, (a) => run(() => c.get(`roles/${encodeURIComponent(a.role_id)}/brief`)));

  server.registerTool('connection_info', {
    description: 'Diagnóstico de conexión: URL usada, modo, origen de la configuración y si la API responde. Nunca muestra el token.',
    inputSchema: {},
    annotations: READ_ONLY,
  }, async () => {
    const info: Record<string, unknown> = {
      api_url: cfg.apiUrl,
      url_origen: cfg.urlOrigin,
      modo: cfg.mode === 'vista' ? 'vista (solo lectura)' : 'control',
      token: cfg.token ? `configurado (${cfg.tokenKind}; origen: ${cfg.tokenOrigin})` : 'NO configurado',
      herramientas_motor: cfg.engineTools,
    };
    try {
      await c.get('sprint/status');
      info.api = 'responde';
    } catch (e: any) {
      info.api = `error: ${e.message}`;
    }
    return ok(info);
  });
}

/**
 * Recursos MCP de solo lectura (agileforge://...).
 * `roles` (agileforge://roles → GET /roles) solo con el token completo: el token de vista tiene una lista blanca de rutas GET
 * (overview, events, meetings, decisions, tasks, sprint/status, roles/{id}/brief) y recibiría 403.
 */
export function registerReadResources(server: McpServer, c: ReadClient, opts: { roles: boolean } = { roles: false }): void {
  const json = (uri: URL, data: unknown) => ({ contents: [{ uri: uri.href, text: JSON.stringify(data), mimeType: 'application/json' }] });
  server.resource('sprint-current', 'agileforge://sprint/current', async (uri) => json(uri, await c.get('sprint/status')));
  if (opts.roles) server.resource('roles-list', 'agileforge://roles', async (uri) => json(uri, await c.get('roles')));
  server.resource('task-detail', new ResourceTemplate('agileforge://task/{id}', { list: undefined }), async (uri, v) =>
    json(uri, await c.get(`tasks/${encodeURIComponent(String(v.id))}`)));
}
