// Contrato de la API HTTP: las reglas que ningún cambio debe romper.
import test from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { api, action, rawGet, newTask, TOKEN, VIEW_TOKEN, ROOT } from './helpers.mjs';

const CORE_ROLES = ['director', 'lead_architect', 'backend_architect', 'frontend_master', 'qa_security_guard', 'creative_director', 'legal_counsel', 'project_manager'];
// Ruta ABSOLUTA: la evidencia vale sea cual sea el workspace del proyecto activo
// (en la PC de un desarrollador la base temporal se siembra con su data/team.json y su propio workspace).
const EVIDENCE = { files_changed: [path.join(ROOT, 'api', 'Repository.php')], tests_run: ['node tests/run.mjs'], summary: 'Entrega de la prueba de contrato' };
const VERIFICATION = { commands_run: ['npm test', 'node tests/run.mjs'], notes: 'Verificación exhaustiva de DoD y ejecución de pruebas unitarias sin fallos.' };

test('autenticación: sin token o con token inválido responde 401', async () => {
  assert.equal((await api('GET', 'index.php/state', undefined, null)).status, 401);
  assert.equal((await api('GET', 'index.php/state', undefined, 'token-invalido')).status, 401);
  assert.equal((await action('create_task', { title: 'x' }, null)).status, 401);
});

test('seguridad: un Host u Origin que no es local responde 403', async () => {
  assert.equal(await rawGet('index.php/state', { Host: 'evil.example.com', Authorization: `Bearer ${TOKEN}` }), 403);
  assert.equal(await rawGet('index.php/state', { Origin: 'https://evil.example.com', Authorization: `Bearer ${TOKEN}` }), 403);
});

test('estado: devuelve proyecto activo, los 8 roles del core y las historias', async () => {
  const r = await api('GET', 'index.php/state');
  assert.equal(r.status, 200);
  const ids = r.json.roles.map((x) => x.id);
  for (const id of CORE_ROLES) assert.ok(ids.includes(id), `falta el rol ${id}`);
  assert.ok(Array.isArray(r.json.tasks));
});

test('historias: los puntos se ajustan a Fibonacci y el rol debe existir', async () => {
  const t = await newTask({ story_points: 4 });
  assert.ok([1, 2, 3, 5, 8, 13].includes(Number(t.story_points)), `story_points=${t.story_points}`);
  const bad = await action('create_task', { title: 'Rol inexistente', assigned_to: 'nadie', client: 'web' });
  assert.ok(bad.status >= 400 && bad.status < 500, `status ${bad.status}`);
});

test('historias: estado inválido o historia inexistente no dan 500', async () => {
  const t = await newTask();
  const a = await action('update_task_status', { task_id: t.id, status: 'inventado', client: 'web' });
  const b = await action('update_task_status', { task_id: 'NO-EXISTE', status: 'backlog', client: 'web' });
  for (const r of [a, b]) assert.ok(r.status >= 400 && r.status < 500, `status ${r.status}`);
});

test('regla: solo la web puede mover una historia a completada', async () => {
  const t = await newTask();
  const mcp = await action('update_task_status', { task_id: t.id, status: 'completada', client: 'mcp' });
  assert.ok(mcp.status >= 400 && mcp.status < 500, `un cliente mcp completó la historia (status ${mcp.status})`);
  const web = await action('update_task_status', { task_id: t.id, status: 'completada', client: 'web' });
  assert.equal(web.status, 200);
});

test('locks: una historia tomada no la puede tomar otro rol', async () => {
  const t = await newTask();
  assert.equal((await action('claim_task', { task_id: t.id, role_key: 'backend_architect' })).status, 200);
  const other = await action('claim_task', { task_id: t.id, role_key: 'frontend_master' });
  assert.equal(other.status, 409);
});

test('revisión: solo qa_security_guard aprueba, y al aprobar queda completada con su memoria', async () => {
  const t = await newTask();
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  const sub = await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  assert.equal(sub.status, 200, sub.text.slice(0, 200));

  const notQa = await action('review_task', { task_id: t.id, decision: 'approve', checklist: ['ok'], reviewer_role: 'backend_architect', verification: VERIFICATION });
  assert.ok(notQa.status >= 400 && notQa.status < 500, `un rol que no es QA aprobó (status ${notQa.status})`);

  const invalid = await action('review_task', { task_id: t.id, decision: 'quizas', checklist: [], reviewer_role: 'qa_security_guard' });
  assert.ok(invalid.status >= 400 && invalid.status < 500);

  // Esperar tiempo mínimo de revisión configurado en test (2s)
  await new Promise((r) => setTimeout(r, 2100));

  const ok = await action('review_task', { task_id: t.id, decision: 'approve', checklist: ['DoD verificada'], reviewer_role: 'qa_security_guard', verification: VERIFICATION });
  assert.equal(ok.status, 200, ok.text.slice(0, 200));

  // Con firma humana por defecto, review_task deja la tarea en revision con qa_approved; el panel web la completa
  assert.equal(ok.json.status, 'revision');
  assert.equal(ok.json.qa_approved, true);

  const webDone = await action('update_task_status', { task_id: t.id, status: 'completada', client: 'web' });
  assert.equal(webDone.status, 200);

  const state = await api('GET', 'index.php/state');
  assert.equal(state.json.tasks.find((x) => x.id === t.id).status, 'completada');

  const mem = await api('GET', `index.php/memories/MEM-TASK-${t.id}`);
  assert.equal(mem.status, 200);
  assert.equal(Number(mem.json.memory.is_active), 1);

  // El brief del rol debe traer la memoria CON contenido (regresión: llegaba vacía)
  const brief = await api('GET', 'index.php/roles/backend_architect/brief');
  const m = brief.json.brief.memories.find((x) => x.id === `MEM-TASK-${t.id}`);
  assert.ok(m, 'la memoria no aparece en el brief');
  assert.ok(String(m.content || '').length > 0, 'la memoria llega sin contenido al brief');
});

test('revisión: un workspace relativo (".") se resuelve contra la raíz de la app', async (t) => {
  const projects = (await action('list_projects')).json?.projects || [];
  const active = projects.find((p) => Number(p.active) === 1) || {};
  const ws = String(active.path || '').trim();
  if (ws !== '' && ws !== '.') return t.skip(`el proyecto activo usa un workspace propio (${ws})`);
  const deliver = async (file) => {
    const task = await newTask();
    await action('claim_task', { task_id: task.id, role_key: 'backend_architect' });
    await action('submit_for_review', { task_id: task.id, role_key: 'backend_architect', evidence: { ...EVIDENCE, files_changed: [file] } });
    await new Promise((r) => setTimeout(r, 2100));
    return action('review_task', { task_id: task.id, decision: 'approve', checklist: ['DoD verificada'], reviewer_role: 'qa_security_guard', verification: VERIFICATION });
  };
  const ok = await deliver('api/Repository.php');
  assert.equal(ok.status, 200, ok.text.slice(0, 200));
  // Un archivo que no existe sigue rechazando la aprobación
  assert.equal((await deliver('no/existe/archivo-xyz.txt')).status, 422);
});

test('memoria: reabrir la historia desactiva su memoria y recompletarla no la duplica', async () => {
  const t = await newTask({ assigned_to: 'frontend_master' });
  const id = `MEM-TASK-${t.id}`;
  await action('update_task_status', { task_id: t.id, status: 'completada', client: 'web' });
  assert.equal(Number((await api('GET', `index.php/memories/${id}`)).json.memory.is_active), 1);
  await action('update_task_status', { task_id: t.id, status: 'en_progreso', client: 'web' });
  assert.equal(Number((await api('GET', `index.php/memories/${id}`)).json.memory.is_active), 0);
  await action('update_task_status', { task_id: t.id, status: 'completada', client: 'web' });
  const all = await api('GET', 'index.php/memories?active_only=false&limit=200');
  assert.equal(all.json.memories.filter((x) => x.id === id).length, 1);
});

test('memoria: validaciones de rol, proyecto, tamaño e id', async () => {
  const cases = [
    { role_id: 'nadie', title: 't', content: 'contenido' },
    { project_id: 'no-existe', title: 't', content: 'contenido' },
    { title: 'x'.repeat(201), content: 'contenido' },
    { title: 't', content: 'x'.repeat(4001) },
    { content: 'sin título' },
    { id: '../malo', title: 't', content: 'contenido' },
  ];
  for (const body of cases) {
    const r = await api('POST', 'index.php/memories', body);
    assert.equal(r.status, 422, `${JSON.stringify(body).slice(0, 60)} → ${r.status}`);
  }
});

test('memoria: crear, desactivar con "false", filtrar y borrar', async () => {
  const c = await api('POST', 'index.php/memories', { role_id: 'backend_architect', title: 'Nota de contrato', content: 'Contenido de la nota', node_type: 'learning', importance: 7 });
  assert.equal(c.status, 200, c.text.slice(0, 200));
  const id = c.json.memory.id;
  await api('POST', `index.php/memories/${id}/toggle`, { is_active: 'false' });
  assert.equal(Number((await api('GET', `index.php/memories/${id}`)).json.memory.is_active), 0);
  const activeOnly = await api('GET', 'index.php/memories?active_only=true&limit=200');
  assert.ok(!activeOnly.json.memories.some((x) => x.id === id));
  const everything = await api('GET', 'index.php/memories?active_only=false&limit=200');
  assert.ok(everything.json.memories.some((x) => x.id === id));
  assert.equal((await api('DELETE', `index.php/memories/${id}`)).status, 200);
  assert.equal((await api('GET', `index.php/memories/${id}`)).status, 404);
});

test('presupuesto del sprint: rechaza valores inválidos y guarda los válidos', async () => {
  assert.equal((await action('update_sprint_budget', { budget_usd: 'abc' })).status, 422);
  assert.equal((await action('update_sprint_budget', { budget_usd: -1 })).status, 422);
  assert.equal((await action('update_sprint_budget', { budget_usd: 12.5 })).status, 200);
});

test('reuniones: sin motor de IA responde 409 y no crea la reunión', async () => {
  const before = (await api('GET', 'index.php/state')).json.meetings?.length ?? 0;
  const r = await action('meeting_begin', { meeting_type: 'decision', topic: 'Reunión de contrato', participants: ['director', 'legal_counsel'] });
  assert.equal(r.status, 409);
  const after = (await api('GET', 'index.php/state')).json.meetings?.length ?? 0;
  assert.equal(after, before);
});

test('token de vista: puede leer pero no escribir', async () => {
  assert.equal((await api('GET', 'index.php/overview', undefined, VIEW_TOKEN)).status, 200);
  assert.equal((await api('GET', 'index.php/tasks', undefined, VIEW_TOKEN)).status, 200);
  // El estado completo incluye configuración: fuera de la lista blanca del token de vista
  assert.equal((await api('GET', 'index.php/state', undefined, VIEW_TOKEN)).status, 403);
  const writes = [
    await action('create_task', { title: 'No debería crearse', client: 'web' }, VIEW_TOKEN),
    await api('POST', 'index.php/memories', { title: 't', content: 'contenido' }, VIEW_TOKEN),
    await action('update_sprint_budget', { budget_usd: 1 }, VIEW_TOKEN),
  ];
  for (const r of writes) assert.equal(r.status, 403, `el token de vista escribió (status ${r.status})`);
});

test('barreras de entrega: submit_for_review sin tests_run responde 422', async () => {
  const t = await newTask();
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  const res = await action('submit_for_review', {
    task_id: t.id,
    role_key: 'backend_architect',
    evidence: { files_changed: EVIDENCE.files_changed, summary: 'Resumen válido de entrega con más de 20 caracteres', tests_run: [] }
  });
  assert.equal(res.status, 422);
});

test('barreras de entrega: submit_for_review con summary corto responde 422', async () => {
  const t = await newTask();
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  const res = await action('submit_for_review', {
    task_id: t.id,
    role_key: 'backend_architect',
    evidence: { files_changed: EVIDENCE.files_changed, tests_run: ['npm test'], summary: 'Corto' }
  });
  assert.equal(res.status, 422);
});

test('barreras de revisión: review_task approve sin verification responde 422', async () => {
  const t = await newTask();
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  const res = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard'
  });
  assert.equal(res.status, 422);
});

test('barreras de revisión: review_task approve antes del tiempo mínimo responde 422', async () => {
  const t = await newTask();
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  // Sin esperar el tiempo mínimo (2s en entorno de prueba):
  const res = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION
  });
  assert.equal(res.status, 422);
});

test('firma humana encendida: review_task approve deja en revision con qa_approved y panel web completa', async () => {
  const t = await newTask();
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  const rev = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION
  });
  assert.equal(rev.status, 200);
  assert.equal(rev.json.status, 'revision');
  assert.equal(rev.json.qa_approved, true);

  // La historia sigue en revision con marca qa_approved
  const taskMid = (await api('GET', `index.php/tasks/${t.id}`)).json.task;
  assert.equal(taskMid.status, 'revision');
  assert.ok(taskMid.review_evidence?.qa_approved);
  assert.equal(taskMid.review_evidence.qa_approved.reviewer, 'qa_security_guard');

  // Solo client=web la pasa a completada
  const webDone = await action('update_task_status', { task_id: t.id, status: 'completada', client: 'web' });
  assert.equal(webDone.status, 200);
  const taskFinal = (await api('GET', `index.php/tasks/${t.id}`)).json.task;
  assert.equal(taskFinal.status, 'completada');
});

test('firma humana apagada: review_task approve pasa directo a completada', async () => {
  const t = await newTask();
  await action('update_human_signoff', { project_id: 'default', enabled: false, client: 'web' });
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  const rev = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION
  });
  assert.equal(rev.status, 200);
  assert.equal(rev.json.status, 'completada');

  const taskFinal = (await api('GET', `index.php/tasks/${t.id}`)).json.task;
  assert.equal(taskFinal.status, 'completada');

  // Restaurar firma humana a true por defecto
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
});

test('gobernanza: cliente MCP no puede pasar a completada directamente si firma encendida (403)', async () => {
  const t = await newTask();
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION
  });

  // Intento de completar desde MCP vía action update_task_status
  const res = await action('update_task_status', { task_id: t.id, status: 'completada', client: 'mcp' });
  assert.equal(res.status, 403);
});

test('marca qa_approved: claim, mover o editar por clientes no web responde 409', async () => {
  const t = await newTask();
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION,
  });

  // Claim por otro rol -> 409
  const claimRes = await action('claim_task', { task_id: t.id, role_key: 'frontend_master', client: 'mcp' });
  assert.equal(claimRes.status, 409);
  assert.ok(claimRes.text.includes('Aprobada por QA: espera la firma humana en el panel'));

  // Mover por cliente no web -> 409
  const moveRes = await action('update_task_status', { task_id: t.id, status: 'backlog', client: 'mcp' });
  assert.equal(moveRes.status, 409);
  assert.ok(moveRes.text.includes('Aprobada por QA: espera la firma humana en el panel'));

  // Editar por cliente no web -> 409
  const editRes = await action('update_task', { task_id: t.id, title: 'Nuevo título', client: 'api' });
  assert.equal(editRes.status, 409);
  assert.ok(editRes.text.includes('Aprobada por QA: espera la firma humana en el panel'));
});

test('marca qa_approved: request_changes o mover desde web revoca la aprobación', async () => {
  // Caso A: request_changes de QA revoca qa_approved
  const t1 = await newTask();
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
  await action('claim_task', { task_id: t1.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t1.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  await action('review_task', {
    task_id: t1.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION,
  });

  const reqChg = await action('review_task', {
    task_id: t1.id,
    decision: 'request_changes',
    checklist: ['Faltan pruebas adicionales'],
    reviewer_role: 'qa_security_guard',
  });
  assert.equal(reqChg.status, 200);
  const task1After = (await api('GET', `index.php/tasks/${t1.id}`)).json.task;
  assert.equal(task1After.status, 'en_progreso');
  assert.equal(task1After.review_evidence?.qa_approved, undefined);
  assert.ok(task1After.events.some((e) => e.event_type === 'qa_approval_revoked'));

  // Caso B: mover desde web a otro estado revoca qa_approved
  const t2 = await newTask();
  await action('claim_task', { task_id: t2.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t2.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  await action('review_task', {
    task_id: t2.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION,
  });

  const webBacklog = await action('update_task_status', { task_id: t2.id, status: 'backlog', client: 'web' });
  assert.equal(webBacklog.status, 200);
  const task2After = (await api('GET', `index.php/tasks/${t2.id}`)).json.task;
  assert.equal(task2After.status, 'backlog');
  assert.equal(task2After.review_evidence?.qa_approved, undefined);
  assert.ok(task2After.events.some((e) => e.event_type === 'qa_approval_revoked'));
});

test('interruptor human_signoff: validación de client, booleanos en texto y proyecto existente', async () => {
  // client que no sea web responde 403
  const mcpRes = await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'mcp' });
  assert.equal(mcpRes.status, 403);

  // Proyecto inexistente responde 404
  const notFound = await action('update_human_signoff', { project_id: 'proyecto_inexistente_123', enabled: true, client: 'web' });
  assert.equal(notFound.status, 404);

  // "false" como texto apaga la firma humana
  const offText = await action('update_human_signoff', { project_id: 'default', enabled: 'false', client: 'web' });
  assert.equal(offText.status, 200);
  assert.equal(offText.json.enabled, false);

  // Booleano inválido responde 422
  const badBool = await action('update_human_signoff', { project_id: 'default', enabled: 'quizas', client: 'web' });
  assert.equal(badBool.status, 422);

  // Restaurar a true
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
});

test('revisión: approve sobre historia ya aprobada responde 409 sin duplicar eventos', async () => {
  const t = await newTask();
  await action('update_human_signoff', { project_id: 'default', enabled: true, client: 'web' });
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));
  const ok1 = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION,
  });
  assert.equal(ok1.status, 200);

  const eventsBefore = (await api('GET', `index.php/tasks/${t.id}`)).json.task.events?.length ?? 0;
  const commentsBefore = (await api('GET', `index.php/tasks/${t.id}`)).json.task.comments?.length ?? 0;

  // Segundo approve
  const dup = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada otra vez'],
    reviewer_role: 'qa_security_guard',
    verification: VERIFICATION,
  });
  assert.equal(dup.status, 409);
  assert.ok(dup.text.includes('Ya está aprobada por QA'));

  const eventsAfter = (await api('GET', `index.php/tasks/${t.id}`)).json.task.events?.length ?? 0;
  const commentsAfter = (await api('GET', `index.php/tasks/${t.id}`)).json.task.comments?.length ?? 0;
  assert.equal(eventsAfter, eventsBefore);
  assert.equal(commentsAfter, commentsBefore);
});

test('barreras de revisión: verification.commands_run con elementos no-string responde 422 sin avisos PHP', async () => {
  const t = await newTask();
  await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE });
  await new Promise((r) => setTimeout(r, 2100));

  const badCommands = await action('review_task', {
    task_id: t.id,
    decision: 'approve',
    checklist: ['DoD verificada'],
    reviewer_role: 'qa_security_guard',
    verification: {
      commands_run: [{ cmd: 'npm test' }], // Objeto dentro del array
      notes: 'Verificación técnica con más de treinta caracteres para cumplir.',
    },
  });
  assert.equal(badCommands.status, 422);
  assert.ok(!badCommands.text.includes('Array to string conversion'));
});
