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

test('bug 1 (LOCKS): update_task_status y update_task con lock vigente de otro rol responden 409', async () => {
  const t = await newTask();
  // backend_architect toma la tarea
  const claim = await action('claim_task', { task_id: t.id, role_key: 'backend_architect' });
  assert.equal(claim.status, 200);

  // frontend_master intenta moverla a backlog -> 409
  const moveByOther = await action('update_task_status', { task_id: t.id, status: 'backlog', role: 'frontend_master', client: 'mcp' });
  assert.equal(moveByOther.status, 409);
  assert.ok(moveByOther.text.includes('bloqueada por el rol: backend_architect'));

  // frontend_master intenta editarla -> 409
  const editByOther = await action('update_task', { task_id: t.id, title: 'Título cambiado por otro', role: 'frontend_master', client: 'api' });
  assert.equal(editByOther.status, 409);
  assert.ok(editByOther.text.includes('bloqueada por el rol: backend_architect'));

  // El dueño del lock (backend_architect) sí puede editarla -> 200
  const editByOwner = await action('update_task', { task_id: t.id, title: 'Título cambiado por dueño', role: 'backend_architect', client: 'mcp' });
  assert.equal(editByOwner.status, 200);

  // El panel web sí puede moverla y editarla -> 200
  const editByWeb = await action('update_task', { task_id: t.id, title: 'Título por web', client: 'web' });
  assert.equal(editByWeb.status, 200);
  const moveByWeb = await action('update_task_status', { task_id: t.id, status: 'backlog', client: 'web' });
  assert.equal(moveByWeb.status, 200);
});

test('bug 2 (ROL INEXISTENTE EN CLAIM): claim_task valida role_key antes de cualquier otra comprobación (422)', async () => {
  const t = await newTask();
  // Tarea existente con rol inventado -> 422
  const badRole = await action('claim_task', { task_id: t.id, role_key: 'rol_inventado' });
  assert.equal(badRole.status, 422);
  assert.ok(badRole.text.includes("El rol 'rol_inventado' no existe"));

  // Tarea inexistente con rol inventado -> 422 (antes de comprobar existencia de la tarea)
  const badRoleNoTask = await action('claim_task', { task_id: 'TASK-NO-EXISTE', role_key: 'rol_inventado' });
  assert.equal(badRoleNoTask.status, 422);
  assert.ok(badRoleNoTask.text.includes("El rol 'rol_inventado' no existe"));
});

test('bug 3 (REUNIONES): rechaza roles inexistentes (422) y convertToTasks reasigna a project_manager sin 500', async () => {
  // start_meeting con participante inválido -> 422
  const badStart = await action('start_meeting', { topic: 'Tema de prueba', participants: ['project_manager', 'rol_fantasma'] });
  assert.equal(badStart.status, 422);
  assert.ok(badStart.text.includes("El rol 'rol_fantasma' no existe"));

  // Crear reunión válida
  const okStart = await action('start_meeting', { topic: 'Reunión de prueba', participants: ['project_manager', 'lead_architect'] });
  assert.equal(okStart.status, 200);
  const meetId = okStart.json.meeting?.id || okStart.json.id;

  // add_meeting_turn con rol inválido -> 422
  const badTurn = await action('add_meeting_turn', { meeting_id: meetId, role_id: 'rol_fantasma', statement: 'Intervención de prueba' });
  assert.equal(badTurn.status, 422);
  assert.ok(badTurn.text.includes("El rol 'rol_fantasma' no existe"));

  // close_meeting con assigned_to inválido -> 422
  const badClose = await action('close_meeting', {
    meeting_id: meetId,
    summary: 'Resumen de prueba con más de quince caracteres',
    resolution: 'Resolución de prueba con más de diez caracteres',
    action_items: [{ title: 'Acuerdo 1', assigned_to: 'rol_fantasma', story_points: 2 }]
  });
  assert.equal(badClose.status, 422);
  assert.ok(badClose.text.includes("El rol asignado 'rol_fantasma' en los acuerdos no existe"));

  // convert_meeting_to_tasks con datos antiguos (rol no reconocido): no da 500 y reasigna a project_manager
  const oldMeetingStart = await action('start_meeting', { topic: 'Reunión antigua', participants: ['project_manager', 'lead_architect'] });
  assert.equal(oldMeetingStart.status, 200);
  const oldMeetId = oldMeetingStart.json.meeting?.id || oldMeetingStart.json.id;
  await action('close_meeting', {
    meeting_id: oldMeetId,
    summary: 'Resumen de prueba con más de quince caracteres',
    resolution: 'Resolución de prueba con más de diez caracteres',
    action_items: [{ title: 'Acuerdo legado', assigned_to: 'project_manager', story_points: 3 }]
  });

  // Modificar en SQLite el assigned_to a un rol antiguo inválido para simular histórico
  if (process.env.AGILEFORGE_DB_PATH) {
    const { DatabaseSync } = await import('node:sqlite');
    const db = new DatabaseSync(process.env.AGILEFORGE_DB_PATH);
    db.prepare('UPDATE meetings SET action_items_json = ? WHERE id = ?').run(
      JSON.stringify([{ title: 'Acuerdo legado', assigned_to: 'rol_invalido_antiguo', story_points: 3 }]),
      oldMeetId
    );
    db.close();
  }

  const convRes = await action('convert_meeting_to_tasks', { meeting_id: oldMeetId });
  assert.equal(convRes.status, 200);
  assert.ok(convRes.json.success);
  assert.ok(convRes.json.created_tasks.some((t) => t.assigned_to === 'project_manager'));
});

test('bug 4 (ACTA): save_meeting_to_workspace guarda en el workspace del proyecto de la reunión', async () => {
  const fs = await import('node:fs');
  const os = await import('node:os');
  const projName = 'Proyecto Workspace ' + Date.now();
  const projWs = path.join(os.tmpdir(), 'af-ws-test-' + Date.now());
  fs.mkdirSync(projWs, { recursive: true });
  try {
    const newProj = await action('create_project', { name: projName, path: projWs, client: 'web' });
    assert.equal(newProj.status, 200);
    const projId = newProj.json.project?.id || newProj.json.id;

    const meet = await action('start_meeting', { topic: 'Reunión de proyecto con workspace', participants: ['project_manager', 'lead_architect'], project_id: projId });
    assert.equal(meet.status, 200);
    const meetId = meet.json.meeting?.id || meet.json.id;

    const save = await action('save_meeting_to_workspace', { meeting_id: meetId });
    assert.equal(save.status, 200);
    assert.ok(save.json.file_path, 'debe devolver file_path');
    const normalizedSaved = path.normalize(save.json.file_path).toLowerCase();
    const normalizedWs = path.normalize(projWs).toLowerCase();
    assert.ok(normalizedSaved.startsWith(normalizedWs), `El acta ${save.json.file_path} no se guardó en el workspace ${projWs}`);
  } finally {
    fs.rmSync(projWs, { recursive: true, force: true });
  }
});

test('bug 5 (OTRO PROYECTO): mutaciones desde clientes no web sobre historias de otro proyecto responden 409', async () => {
  const p2Name = 'Segundo Proyecto ' + Date.now();
  const p2 = await action('create_project', { name: p2Name, path: ROOT, client: 'web' });
  assert.equal(p2.status, 200);
  const p2Id = p2.json.project?.id || p2.json.id;

  const t2 = await action('create_task', { title: 'Historia en segundo proyecto', project_id: p2Id, client: 'web' });
  assert.equal(t2.status, 201);
  const taskId = t2.json.task.id;

  // Cambiar el proyecto activo a 'default'
  await action('switch_project', { project_id: 'default' });

  // claim_task por cliente mcp -> 409
  const claimOther = await action('claim_task', { task_id: taskId, role_key: 'backend_architect', client: 'mcp' });
  assert.equal(claimOther.status, 409);
  assert.ok(claimOther.text.includes('pertenece al proyecto'));

  // submit_for_review por cliente mcp -> 409
  const subOther = await action('submit_for_review', { task_id: taskId, role_key: 'backend_architect', evidence: EVIDENCE, client: 'mcp' });
  assert.equal(subOther.status, 409);
  assert.ok(subOther.text.includes('pertenece al proyecto'));

  // update_task_status por cliente mcp -> 409
  const moveOther = await action('update_task_status', { task_id: taskId, status: 'en_progreso', client: 'mcp' });
  assert.equal(moveOther.status, 409);
  assert.ok(moveOther.text.includes('pertenece al proyecto'));

  // update_task por cliente api -> 409
  const updateOther = await action('update_task', { task_id: taskId, title: 'Nuevo título', client: 'api' });
  assert.equal(updateOther.status, 409);
  assert.ok(updateOther.text.includes('pertenece al proyecto'));

  // add_comment por cliente api -> 409
  const commentOther = await action('add_comment', { task_id: taskId, content: 'Comentario en otra tarea', client: 'api' });
  assert.equal(commentOther.status, 409);
  assert.ok(commentOther.text.includes('pertenece al proyecto'));

  // La web puede operar la historia de cualquier proyecto -> 200
  const webUpdate = await action('update_task', { task_id: taskId, title: 'Título cambiado por web', client: 'web' });
  assert.equal(webUpdate.status, 200);
});

test('bug 6 (REVISION SIN ENTREGA): update_task_status a revision desde clientes no web responde 409', async () => {
  const t = await newTask();
  const res = await action('update_task_status', { task_id: t.id, status: 'revision', client: 'mcp' });
  assert.equal(res.status, 409);
  assert.ok(res.text.includes('submit_for_review'));

  const resUpdate = await action('update_task', { task_id: t.id, fields: { status: 'revision' }, client: 'api' });
  assert.equal(resUpdate.status, 409);
  assert.ok(resUpdate.text.includes('submit_for_review'));
});

test('bug 7 (LÍMITES): valida límites de longitud y unicidad de nombre de proyecto', async () => {
  // Título de historia <= 200 (422)
  const longTitle = 'A'.repeat(201);
  const badTitle = await action('create_task', { title: longTitle, client: 'web' });
  assert.equal(badTitle.status, 422);
  assert.ok(badTitle.text.includes('no puede superar los 200 caracteres'));

  // Comentario <= 5000 (422)
  const t = await newTask();
  const longComment = 'C'.repeat(5001);
  const badComment = await action('add_comment', { task_id: t.id, content: longComment, client: 'web' });
  assert.equal(badComment.status, 422);
  assert.ok(badComment.text.includes('no puede superar los 5000 caracteres'));

  // Comentario con rol inexistente (422)
  const badRoleComment = await action('add_comment', { task_id: t.id, content: 'Comentario normal', role: 'rol_fantasma', client: 'web' });
  assert.equal(badRoleComment.status, 422);
  assert.ok(badRoleComment.text.includes("El rol 'rol_fantasma' no existe"));

  // Nombre de proyecto <= 120 (422)
  const longProjName = 'P'.repeat(121);
  const badProjName = await action('create_project', { name: longProjName, client: 'web' });
  assert.equal(badProjName.status, 422);
  assert.ok(badProjName.text.includes('no puede superar los 120 caracteres'));

  // Nombre de proyecto duplicado exacto (409)
  const uniqueName = 'Proyecto Unico ' + Date.now();
  const p1 = await action('create_project', { name: uniqueName, client: 'web' });
  assert.equal(p1.status, 200);
  const dupProj = await action('create_project', { name: uniqueName, client: 'web' });
  assert.equal(dupProj.status, 409);
  assert.ok(dupProj.text.includes('Ya existe un proyecto con el nombre'));

  // Descripción de skill <= 1000 (422)
  const longSkillDesc = 'S'.repeat(1001);
  const badSkill = await action('create_skill', { name: 'Skill Larga', description: longSkillDesc });
  assert.equal(badSkill.status, 422);
  assert.ok(badSkill.text.includes('no puede superar los 1000 caracteres'));
});

test('bug 8 (TIPOGRAFÍA): index.php no contiene peticiones externas y usa pila de fuentes del sistema', async () => {
  const fs = await import('node:fs');
  const indexContent = fs.readFileSync(path.join(ROOT, 'index.php'), 'utf8');
  assert.ok(!indexContent.includes('fonts.googleapis.com'), 'index.php no debe hacer peticiones a Google Fonts');
  assert.ok(!indexContent.includes('fonts.gstatic.com'), 'index.php no debe hacer peticiones a gstatic');
  assert.ok(indexContent.includes('-apple-system'), 'index.php debe usar pila de fuentes del sistema');
});
