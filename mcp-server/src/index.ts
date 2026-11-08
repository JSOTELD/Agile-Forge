#!/usr/bin/env node
/**
 * AgileForge - Servidor MCP Oficial v2.0
 * Protocolo de Contexto de Modelo (MCP) sobre transporte stdio.
 * Conecta agentes autónomos (Claude Code, Cursor, Codex, Gemini) con el Command Center de AgileForge.
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { ResourceTemplate } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { AgileForgeClient } from './client.js';

const client = new AgileForgeClient();
const server = new McpServer({
  name: 'agileforge',
  version: '2.0.0'
});

// Helper para formatear respuestas de herramientas
function formatResult(data: any) {
  return {
    content: [
      {
        type: 'text' as const,
        text: typeof data === 'string' ? data : JSON.stringify(data, null, 2)
      }
    ]
  };
}

// -------------------------------------------------------------
// 1. HERRAMIENTAS DE SPRINT & TABLERO KANBAN
// -------------------------------------------------------------

server.tool(
  'get_sprint_status',
  'Obtiene el estado en vivo del Sprint: objetivo, días restantes, velocidad, puntos por columna y tareas bloqueadas.',
  {},
  async () => {
    try {
      const res = await client.getSprintStatus();
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'list_tasks',
  'Lista las historias y tareas del tablero Scrum con filtros opcionales por estado, rol asignado, prioridad y proyecto.',
  {
    status: z.enum(['backlog', 'en_progreso', 'revision', 'completada']).optional().describe('Filtrar por columna de estado'),
    role: z.string().optional().describe('Filtrar por identificador de rol asignado (ej: frontend_master, backend_architect)'),
    priority: z.enum(['baja', 'media', 'alta', 'critica']).optional().describe('Filtrar por nivel de prioridad'),
    project: z.string().optional().describe('Identificador de proyecto')
  },
  async (args) => {
    try {
      const res = await client.listTasks(args);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'get_task',
  'Obtiene el detalle exhaustivo de una historia: criterio de aceptación, Definition of Done (DoD), skills requeridas, prompt, comentarios e historial de eventos.',
  {
    task_id: z.string().describe('Código de la historia de usuario (ej: US-101, US-102)')
  },
  async (args) => {
    try {
      const res = await client.getTask(args.task_id);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'claim_task',
  'Asigna una historia a un rol específico y la mueve a "en_progreso" con un lock atómico. Impide que dos agentes tomen la misma tarea a la vez.',
  {
    task_id: z.string().describe('Código de la historia de usuario a tomar (ej: US-101)'),
    role_key: z.string().describe('Identificador del rol que toma la tarea (ej: backend_architect, frontend_master)'),
    client: z.string().optional().describe('Cliente o herramienta que ejecuta la acción (ej: claude-code, cursor, codex, gemini)')
  },
  async (args) => {
    try {
      const res = await client.claimTask(args.task_id, args.role_key, args.client);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'update_task_status',
  'Cambia el estado de una historia (backlog, en_progreso, revision) exigiendo una nota obligatoria de avance.',
  {
    task_id: z.string().describe('Código de la historia (ej: US-101)'),
    status: z.enum(['backlog', 'en_progreso', 'revision', 'completada']).describe('Nuevo estado destino de la historia'),
    note: z.string().min(5).describe('Nota explicativa obligatoria que justifica el cambio de estado'),
    role_key: z.string().optional().describe('Rol que efectúa el cambio'),
    client: z.string().optional().describe('Cliente de IA ejecutante (ej: claude-code)'),
    human_approved: z.boolean().optional().describe('Flag obligatorio para transicionar a "completada" si no se opera desde la web')
  },
  async (args) => {
    try {
      const res = await client.updateTaskStatus(args.task_id, args.status, args.note, args.role_key, args.client, args.human_approved);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'add_comment',
  'Registra un comentario o reporte técnico de progreso en el hilo de una historia de usuario.',
  {
    task_id: z.string().describe('Código de la historia de usuario'),
    role: z.string().describe('Rol que emite el comentario'),
    content: z.string().min(1).describe('Texto del comentario o reporte técnico'),
    name: z.string().optional().describe('Nombre legible del autor'),
    avatar: z.string().optional().describe('Emoji avatar del autor')
  },
  async (args) => {
    try {
      const res = await client.addComment(args.task_id, args.role, args.content, args.name, args.avatar);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'submit_for_review',
  'Mueve una historia al estado "revision" adjuntando la evidencia obligatoria de entrega: lista de archivos modificados, tests ejecutados y resumen técnico.',
  {
    task_id: z.string().describe('Código de la historia de usuario (ej: US-101)'),
    role_key: z.string().describe('Rol que entrega la tarea para auditoría'),
    files_changed: z.array(z.string()).describe('Lista de rutas relativas o nombres de archivos modificados/creados'),
    tests_run: z.array(z.string()).describe('Lista de pruebas ejecutadas y sus resultados (ej: ["npm test: 24 passed", "php test: 100% OK"])'),
    summary: z.string().min(10).describe('Resumen técnico de la implementación realizada')
  },
  async (args) => {
    try {
      const res = await client.submitForReview(args.task_id, args.role_key, args.files_changed, args.tests_run, args.summary);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'review_task',
  'Audita y dictamina una historia en revisión (approve / request_changes) evaluando el checklist de la Definition of Done (DoD). "approve" exige evidencia previa.',
  {
    task_id: z.string().describe('Código de la historia a auditar'),
    decision: z.enum(['approve', 'request_changes']).describe('Dictamen: "approve" para aprobar o "request_changes" para solicitar ajustes'),
    checklist: z.array(z.string()).describe('Criterios de DoD validados (ej: ["Lint 0 errores", "Tests en verde", "Sin regresiones"])'),
    reviewer_role: z.string().describe('Rol auditor (normalmente qa_security_guard o project_manager)')
  },
  async (args) => {
    try {
      const res = await client.reviewTask(args.task_id, args.decision, args.checklist, args.reviewer_role);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'create_task',
  'Permite al Project Manager o agentes de arquitectura descomponer trabajo y crear nuevas historias que ingresan al backlog pendientes de aprobación humana.',
  {
    title: z.string().min(5).describe('Título claro de la historia de usuario'),
    story_points: z.number().int().min(1).max(13).default(2).describe('Estimación en puntos de historia (secuencia Fibonacci)'),
    assigned_to: z.string().describe('Rol asignado para la ejecución'),
    priority: z.enum(['alta', 'media', 'baja']).default('media').describe('Prioridad de la historia'),
    user_story: z.string().describe('Enunciado formal: "Como [rol], quiero [acción], para [beneficio]"'),
    dod: z.string().describe('Criterios de Definition of Done verificables'),
    description: z.string().optional().describe('Detalles técnicos adicionales'),
    skills: z.array(z.string()).optional().describe('Skills sugeridas para la misión')
  },
  async (args) => {
    try {
      const res = await client.createTask(args);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

// -------------------------------------------------------------
// 2. HERRAMIENTAS DE SALAS DE REUNIÓN & DELIBERACIÓN
// -------------------------------------------------------------

server.tool(
  'start_meeting',
  'Inicia una sala formal de reunión entre agentes de IA (brainstorming, decision ADR, compliance legal o daily) con tema y participantes.',
  {
    meeting_type: z.enum(['brainstorming', 'decision', 'compliance', 'daily']).describe('Tipo de reunión'),
    topic: z.string().min(5).describe('Tema central o tópico de deliberación'),
    participants: z.array(z.string()).min(2).describe('Lista de roles participantes en la mesa'),
    title: z.string().optional().describe('Título personalizado para el acta de la reunión')
  },
  async (args) => {
    try {
      const res = await client.startMeeting(args);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'add_meeting_turn',
  'Registra la intervención, argumento o propuesta técnica de un agente dentro de una reunión activa.',
  {
    meeting_id: z.string().describe('Código de la reunión (ej: MEET-001)'),
    role_id: z.string().describe('Rol del agente que interviene'),
    statement: z.string().min(10).describe('Declaración, propuesta o voto técnico'),
    name: z.string().optional().describe('Nombre del agente'),
    alias: z.string().optional().describe('Alias temático del agente'),
    avatar: z.string().optional().describe('Emoji avatar del agente'),
    ai_model: z.string().optional().describe('Modelo de IA utilizado')
  },
  async (args) => {
    try {
      const { meeting_id, ...turnData } = args;
      const res = await client.addMeetingTurn(meeting_id, turnData);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'close_meeting',
  'Cierra la sesión de reunión generando el acta oficial con resumen ejecutivo, resolución adoptada y compromisos/action items.',
  {
    meeting_id: z.string().describe('Código de la reunión a cerrar'),
    summary: z.string().min(15).describe('Resumen ejecutivo de los debates sostenidos'),
    resolution: z.string().min(10).describe('Resolución o acuerdo formal alcanzado'),
    action_items: z.array(
      z.object({
        title: z.string(),
        assigned_to: z.string(),
        story_points: z.number().int().default(2),
        priority: z.enum(['alta', 'media', 'baja']).default('media'),
        user_story: z.string().optional(),
        dod: z.string().optional()
      })
    ).optional().describe('Lista de tareas o compromisos derivados del acuerdo')
  },
  async (args) => {
    try {
      const { meeting_id, ...closeData } = args;
      const res = await client.closeMeeting(meeting_id, closeData);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

// -------------------------------------------------------------
// 3. HERRAMIENTAS DE ARQUITECTURA (ADR) & SYSTEM PROMPT
// -------------------------------------------------------------

server.tool(
  'record_decision',
  'Registra un Architectural Decision Record (ADR) formal con título, contexto del trade-off, decisión adoptada y consecuencias técnicas.',
  {
    title: z.string().min(5).describe('Título de la decisión arquitectónica'),
    context: z.string().min(10).describe('Contexto del problema, alternativas evaluadas y trade-offs considerados'),
    decision: z.string().min(10).describe('Decisión técnica adoptada'),
    consequences: z.string().min(10).describe('Consecuencias positivas, negativas y riesgos aceptados'),
    status: z.enum(['proposed', 'accepted', 'rejected', 'deprecated']).default('accepted').describe('Estado del ADR')
  },
  async (args) => {
    try {
      const res = await client.recordDecision(args);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

server.tool(
  'get_role_brief',
  'Obtiene el system prompt oficial del rol, su especialidad, toolkit de skills activas y la normativa legal vigente de la jurisdicción.',
  {
    role_id: z.string().describe('Identificador del rol (ej: project_manager, lead_architect, legal_counsel)')
  },
  async (args) => {
    try {
      const res = await client.getRoleBrief(args.role_id);
      return formatResult(res);
    } catch (err: any) {
      return formatResult({ error: err.message });
    }
  }
);

// -------------------------------------------------------------
// 4. RECURSOS MCP (agileforge://...)
// -------------------------------------------------------------

server.resource(
  'sprint-current',
  'agileforge://sprint/current',
  async (uri) => {
    const res = await client.getSprintStatus();
    return {
      contents: [
        {
          uri: uri.href,
          text: JSON.stringify(res, null, 2),
          mimeType: 'application/json'
        }
      ]
    };
  }
);

server.resource(
  'roles-list',
  'agileforge://roles',
  async (uri) => {
    const res = await client.listRoles();
    return {
      contents: [
        {
          uri: uri.href,
          text: JSON.stringify(res, null, 2),
          mimeType: 'application/json'
        }
      ]
    };
  }
);

server.resource(
  'task-detail',
  new ResourceTemplate('agileforge://task/{id}', { list: undefined }),
  async (uri, variables) => {
    const taskId = String(variables.id);
    const res = await client.getTask(taskId);
    return {
      contents: [
        {
          uri: uri.href,
          text: JSON.stringify(res, null, 2),
          mimeType: 'application/json'
        }
      ]
    };
  }
);

// -------------------------------------------------------------
// 5. INICIALIZACIÓN DEL TRANSPORTE STDIO
// -------------------------------------------------------------

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((error) => {
  console.error('Error fatal al iniciar servidor MCP AgileForge:', error);
  process.exit(1);
});

