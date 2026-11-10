// Contrato de los servidores MCP que viajan dentro de los plugins (los bundles, no el código fuente).
import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import path from 'node:path';
import { BASE, TOKEN, VIEW_TOKEN, ROOT, newTask } from './helpers.mjs';

const CONTROL = path.join(ROOT, 'plugin', 'agileforge', 'server', 'agileforge-mcp.mjs');
const VISTA = path.join(ROOT, 'plugin', 'agileforge-vista', 'server', 'agileforge-vista-mcp.mjs');
const READ_TOOLS = ['get_overview', 'get_sprint_status', 'list_tasks', 'get_task', 'list_meetings', 'get_meeting', 'list_decisions', 'get_activity', 'get_role_brief', 'list_memories', 'get_memory'];
const WRITE_TOOLS = ['create_task', 'claim_task', 'update_task_status', 'add_comment', 'submit_for_review', 'review_task', 'start_meeting', 'add_meeting_turn', 'close_meeting', 'record_decision', 'start_sprint', 'remember', 'forget'];

/** Cliente MCP mínimo por stdio (JSON-RPC delimitado por saltos de línea). */
function connect(bundle, extraEnv) {
  const child = spawn(process.execPath, [bundle], {
    env: { ...process.env, AGILEFORGE_API_URL: `${BASE}/api`, AGILEFORGE_HOME: '', AGILEFORGE_CLIENT: 'contract-test', ...extraEnv },
    stdio: ['pipe', 'pipe', 'ignore'],
  });
  let buffer = '';
  let nextId = 1;
  const pending = new Map();
  child.stdout.on('data', (chunk) => {
    buffer += chunk;
    let nl;
    while ((nl = buffer.indexOf('\n')) >= 0) {
      const line = buffer.slice(0, nl).trim();
      buffer = buffer.slice(nl + 1);
      if (!line) continue;
      try {
        const msg = JSON.parse(line);
        if (msg.id !== undefined && pending.has(msg.id)) {
          pending.get(msg.id)(msg);
          pending.delete(msg.id);
        }
      } catch { /* línea que no es JSON */ }
    }
  });
  const request = (method, params = {}) => new Promise((resolve, reject) => {
    const id = nextId++;
    const timer = setTimeout(() => reject(new Error(`MCP sin respuesta a ${method}`)), 20000);
    pending.set(id, (msg) => { clearTimeout(timer); resolve(msg); });
    child.stdin.write(`${JSON.stringify({ jsonrpc: '2.0', id, method, params })}\n`);
  });
  const notify = (method) => child.stdin.write(`${JSON.stringify({ jsonrpc: '2.0', method })}\n`);
  return {
    async init() {
      const r = await request('initialize', { protocolVersion: '2024-11-05', capabilities: {}, clientInfo: { name: 'contract-test', version: '1' } });
      notify('notifications/initialized');
      return r.result;
    },
    async tools() {
      return (await request('tools/list')).result.tools.map((t) => t.name);
    },
    async call(name, args = {}) {
      const r = await request('tools/call', { name, arguments: args });
      const text = (r.result?.content || []).map((c) => c.text || '').join('\n');
      return { isError: Boolean(r.error || r.result?.isError), text };
    },
    close() { child.kill(); },
  };
}

test('MCP de control: versión, herramientas de lectura y de escritura', async () => {
  const mcp = connect(CONTROL, { AGILEFORGE_API_TOKEN: TOKEN });
  try {
    const info = await mcp.init();
    assert.equal(info.serverInfo.name, 'agileforge');
    assert.match(info.serverInfo.version, /^\d+\.\d+\.\d+$/);
    const tools = await mcp.tools();
    for (const t of [...READ_TOOLS, ...WRITE_TOOLS]) assert.ok(tools.includes(t), `falta la herramienta ${t}`);
  } finally {
    mcp.close();
  }
});

test('MCP de control: crea y lee una historia contra el tablero', async () => {
  const mcp = connect(CONTROL, { AGILEFORGE_API_TOKEN: TOKEN });
  try {
    await mcp.init();
    const t = await newTask({ title: 'Historia visible por MCP' });
    const got = await mcp.call('get_task', { task_id: t.id });
    assert.equal(got.isError, false, got.text.slice(0, 200));
    assert.ok(got.text.includes('Historia visible por MCP'));

    // Puntos fuera de Fibonacci: el esquema de la herramienta los rechaza
    const bad = await mcp.call('create_task', { title: 'Puntos inválidos', story_points: 4 });
    assert.equal(bad.isError, true);

    // Un cliente MCP no puede dar por completada una historia directamente
    const done = await mcp.call('update_task_status', { task_id: t.id, status: 'completada' });
    assert.equal(done.isError, true, 'el MCP completó una historia sin pasar por QA ni por la web');

    // Flujo MCP: claim -> submit_for_review -> espera min review -> review_task approve con verification
    await mcp.call('claim_task', { task_id: t.id, role_key: 'backend_architect' });
    const sub = await mcp.call('submit_for_review', {
      task_id: t.id,
      role_key: 'backend_architect',
      files_changed: [path.join(ROOT, 'api', 'Repository.php')],
      tests_run: ['npm test: 26 passed'],
      summary: 'Entrega formal de la historia con pruebas de contrato ejecutadas',
    });
    assert.equal(sub.isError, false, sub.text);

    // Esperar tiempo mínimo de revisión configurado en test (2s)
    await new Promise((r) => setTimeout(r, 2100));

    // QA aprueba vía MCP con bloque verification
    const rev = await mcp.call('review_task', {
      task_id: t.id,
      decision: 'approve',
      checklist: ['DoD verificada', 'Pruebas verdes'],
      reviewer_role: 'qa_security_guard',
      verification: {
        commands_run: ['npm test'],
        notes: 'Verificación técnica aprobada en el entorno de pruebas sin fallos.',
      },
    });
    assert.equal(rev.isError, false, rev.text);
    assert.ok(rev.text.includes('"revision"'));

    // Con firma humana activa, el MCP sigue sin poder completarla directamente
    const doneAfterQa = await mcp.call('update_task_status', { task_id: t.id, status: 'completada' });
    assert.equal(doneAfterQa.isError, true, 'el MCP completó una historia directamente con firma humana activa');
  } finally {
    mcp.close();
  }
});

test('MCP de vista: solo lectura, sin ninguna herramienta de escritura', async () => {
  const mcp = connect(VISTA, { AGILEFORGE_VIEW_TOKEN: VIEW_TOKEN, AGILEFORGE_API_TOKEN: '' });
  try {
    const info = await mcp.init();
    assert.equal(info.serverInfo.name, 'agileforge-vista');
    const tools = await mcp.tools();
    for (const t of WRITE_TOOLS) assert.ok(!tools.includes(t), `el plugin de vista expone ${t}`);
    assert.ok(tools.includes('list_tasks'));
    const r = await mcp.call('list_tasks', {});
    assert.equal(r.isError, false, r.text.slice(0, 200));
  } finally {
    mcp.close();
  }
});

test('MCP de control en modo vista (AGILEFORGE_MODE=vista) no expone escritura', async () => {
  const mcp = connect(CONTROL, { AGILEFORGE_MODE: 'vista', AGILEFORGE_VIEW_TOKEN: VIEW_TOKEN, AGILEFORGE_API_TOKEN: '' });
  try {
    await mcp.init();
    const tools = await mcp.tools();
    for (const t of WRITE_TOOLS) assert.ok(!tools.includes(t), `modo vista expone ${t}`);
  } finally {
    mcp.close();
  }
});
