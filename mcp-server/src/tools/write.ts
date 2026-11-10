import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import type { WriteClient } from '../client-write.js';
import { PRIORITIES } from './read.js';
import { WRITE, run } from './util.js';

const enc = encodeURIComponent;

/** Story points Fibonacci (el servidor acepta solo 1, 2, 3, 5, 8 y 13). */
const FIB = [1, 2, 3, 5, 8, 13] as const;
const storyPoints = z
  .number()
  .int()
  .refine((n) => (FIB as readonly number[]).includes(n), { message: 'Usa un valor Fibonacci: 1, 2, 3, 5, 8 o 13' });

export function registerWriteTools(server: McpServer, c: WriteClient): void {
  const dflt = c.cfg.client;
  const post = (route: string, body: unknown) => run(() => c.post(route, body), ['success']);

  server.registerTool('create_task', {
    description: 'Crea una historia en el backlog (queda pendiente de aprobación humana).',
    inputSchema: {
      title: z.string().min(5),
      assigned_to: z.string().describe('Rol ejecutor, ej. backend_architect'),
      user_story: z.string().describe('"Como [rol], quiero [acción], para [beneficio]"'),
      dod: z.string().describe('Definition of Done verificable'),
      story_points: storyPoints.default(2).describe('Fibonacci: 1, 2, 3, 5, 8 o 13'),
      priority: z.enum(PRIORITIES).default('media'),
      description: z.string().optional(),
      skills: z.array(z.string()).optional().describe('Skills sugeridas'),
    },
    annotations: WRITE,
  }, (a) => post('tasks', { ...a, client: dflt }));

  server.registerTool('claim_task', {
    description: 'Toma una historia: la asigna al rol, la pasa a en_progreso y la bloquea (lock).',
    inputSchema: {
      task_id: z.string(),
      role_key: z.string().describe('Rol que la toma'),
      client: z.string().optional().describe('Cliente, ej. claude-code'),
    },
    annotations: WRITE,
  }, (a) => post(`tasks/${enc(a.task_id)}/claim`, { role_key: a.role_key, client: a.client || dflt }));

  server.registerTool('update_task_status', {
    description: 'Mueve una historia a backlog, en_progreso o revision con una nota. No completa: eso lo hace review_task (QA) o el humano en la web.',
    inputSchema: {
      task_id: z.string(),
      status: z.enum(['backlog', 'en_progreso', 'revision']),
      note: z.string().min(5).describe('Motivo del cambio'),
      role_key: z.string().optional().describe('Rol que cambia el estado'),
      client: z.string().optional(),
    },
    annotations: WRITE,
  }, (a) => post(`tasks/${enc(a.task_id)}/status`, { status: a.status, note: a.note, role: a.role_key, client: a.client || dflt }));

  server.registerTool('add_comment', {
    description: 'Añade un comentario o reporte de avance a una historia.',
    inputSchema: {
      task_id: z.string(),
      role: z.string().describe('Rol autor'),
      content: z.string().min(1),
      name: z.string().optional(),
      avatar: z.string().optional(),
    },
    annotations: WRITE,
  }, (a) => post(`tasks/${enc(a.task_id)}/comments`, { role: a.role, content: a.content, name: a.name || a.role, avatar: a.avatar || '💬' }));

  server.registerTool('submit_for_review', {
    description: 'Entrega la historia a revisión con evidencia (archivos, pruebas, resumen).',
    inputSchema: {
      task_id: z.string(),
      role_key: z.string().describe('Rol que entrega'),
      files_changed: z.array(z.string()),
      tests_run: z.array(z.string()).describe('Pruebas y resultado, ej. "npm test: 24 ok"'),
      summary: z.string().min(10),
    },
    annotations: WRITE,
  }, (a) => post(`tasks/${enc(a.task_id)}/submit-review`, {
    role_key: a.role_key,
    evidence: { files_changed: a.files_changed, tests_run: a.tests_run, summary: a.summary },
  }));

  server.registerTool('review_task', {
    description: 'Dictamina una historia en revisión. approve la completa solo si el revisor es QA (qa_security_guard), distinto de quien la tomó, y hay evidencia.',
    inputSchema: {
      task_id: z.string(),
      decision: z.enum(['approve', 'request_changes']),
      checklist: z.array(z.string()).describe('Criterios DoD verificados'),
      reviewer_role: z.string().describe('Normalmente qa_security_guard'),
    },
    annotations: WRITE,
  }, (a) => post(`tasks/${enc(a.task_id)}/review`, { decision: a.decision, checklist: a.checklist, reviewer_role: a.reviewer_role }));

  server.registerTool('start_meeting', {
    description: 'Abre una sala de reunión entre roles (brainstorming, decision, compliance o daily).',
    inputSchema: {
      meeting_type: z.enum(['brainstorming', 'decision', 'compliance', 'daily']),
      topic: z.string().min(5),
      participants: z.array(z.string()).min(2).describe('Roles participantes'),
      title: z.string().optional(),
    },
    annotations: WRITE,
  }, (a) => post('meetings', a));

  server.registerTool('add_meeting_turn', {
    description: 'Registra la intervención de un rol en una reunión abierta.',
    inputSchema: {
      meeting_id: z.string(),
      role_id: z.string(),
      statement: z.string().min(10),
      name: z.string().optional(),
      alias: z.string().optional(),
      avatar: z.string().optional(),
      ai_model: z.string().optional(),
    },
    annotations: WRITE,
  }, (a) => { const { meeting_id, ...turn } = a; return post(`meetings/${enc(meeting_id)}/turns`, turn); });

  server.registerTool('close_meeting', {
    description: 'Cierra la reunión con resumen, resolución y, opcionalmente, historias derivadas.',
    inputSchema: {
      meeting_id: z.string(),
      summary: z.string().min(15),
      resolution: z.string().min(10),
      action_items: z.array(z.object({
        title: z.string(),
        assigned_to: z.string(),
        story_points: storyPoints.default(2),
        priority: z.enum(PRIORITIES).default('media'),
        user_story: z.string().optional(),
        dod: z.string().optional(),
      })).optional(),
    },
    annotations: WRITE,
  }, (a) => { const { meeting_id, ...data } = a; return post(`meetings/${enc(meeting_id)}/close`, data); });

  server.registerTool('record_decision', {
    description: 'Registra una decisión de arquitectura (ADR).',
    inputSchema: {
      title: z.string().min(5),
      context: z.string().min(10),
      decision: z.string().min(10),
      consequences: z.string().min(10),
      status: z.enum(['proposed', 'accepted', 'rejected', 'deprecated']).default('accepted'),
    },
    annotations: WRITE,
  }, (a) => post('decisions', a));

  server.registerTool('start_sprint', {
    description: 'Abre un sprint nuevo (número = último + 1) y cierra el activo del proyecto. Úsalo al planificar el siguiente sprint.',
    inputSchema: {
      name: z.string().min(3).max(200).describe('Nombre del sprint, p. ej. "Sprint 3: Pagos"'),
      goal: z.string().max(500).optional(),
      days_remaining: z.number().int().min(1).max(60).optional(),
      velocity_points: z.number().int().min(1).max(500).optional(),
      project_id: z.string().optional().describe('Proyecto; por defecto el activo'),
    },
    annotations: WRITE,
  }, (a) => post('sprint/start', a));
}
