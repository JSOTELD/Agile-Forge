import { z } from 'zod';
import { WRITE, fail, ok } from './util.js';
const ENGINE_TIMEOUT_MS = 120000;
function chatReply(res) {
    const msg = res?.messages?.[1];
    if (!msg)
        return ok(res);
    const actions = (msg.meta?.actions || []).map((a) => `${a.ok ? 'ok' : 'fallo'}: ${a.summary}`);
    return ok([`${msg.sender_name} (${msg.ai_model || 'sin motor'}): ${msg.text}`, ...(actions.length ? ['Acciones:', ...actions] : [])].join('\n'));
}
export function registerEngineTools(server, c) {
    server.registerTool('director_instruction', {
        description: 'Da una instrucción al Director del motor web de AgileForge (requiere motor de IA configurado en la app). Devuelve su respuesta y acciones.',
        inputSchema: { instruction: z.string().min(3) },
        annotations: WRITE,
    }, async (a) => {
        try {
            return chatReply(await c.post('tasks.php', { action: 'director_instruction', instruction: a.instruction }, ENGINE_TIMEOUT_MS));
        }
        catch (e) {
            return fail(e);
        }
    });
    server.registerTool('ask_agent', {
        description: 'Consulta a un rol mediante el motor de IA de la app (requiere motor configurado).',
        inputSchema: { role_id: z.string().describe('Ej. project_manager, backend_architect'), message: z.string().min(2) },
        annotations: WRITE,
    }, async (a) => {
        try {
            return chatReply(await c.post('tasks.php', { action: 'send_chat', target_role: a.role_id, message: a.message }, ENGINE_TIMEOUT_MS));
        }
        catch (e) {
            return fail(e);
        }
    });
}
