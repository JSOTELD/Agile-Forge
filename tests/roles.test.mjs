// RFC-001 fase 1: registro de roles en archivos y reglas por capacidades.
import test from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import fs from 'node:fs';
import os from 'node:os';
import { execFileSync } from 'node:child_process';
import { api, action, newTask, ROOT } from './helpers.mjs';

const EVIDENCE = { files_changed: [path.join(ROOT, 'api', 'Repository.php')], tests_run: ['node tests/run.mjs'], summary: 'Entrega de la prueba de capacidades' };
const PHP = process.env.PHP_BIN || 'php';

function rolesCli(args, env = {}) {
  try {
    return { code: 0, out: execFileSync(PHP, [path.join(ROOT, 'bin', 'roles.php'), ...args], { cwd: ROOT, encoding: 'utf8', env: { ...process.env, ...env } }) };
  } catch (e) {
    return { code: e.status, out: String(e.stdout || '') + String(e.stderr || '') };
  }
}

function tmpWorkspace(files) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'af-roles-'));
  fs.mkdirSync(path.join(dir, '.agileforge', 'roles'), { recursive: true });
  for (const [name, body] of Object.entries(files)) fs.writeFileSync(path.join(dir, '.agileforge', 'roles', name), body);
  return dir;
}

test('registro: el estado trae las capacidades de cada rol y el pack core no tiene errores', async () => {
  const s = await api('GET', 'index.php/state');
  assert.equal(s.status, 200);
  const byId = Object.fromEntries(s.json.roles.map((r) => [r.id, r]));
  assert.ok(byId.qa_security_guard.capabilities.includes('can_review_tasks'));
  assert.ok(byId.qa_security_guard.capabilities.includes('can_approve_dod'));
  assert.ok(byId.director.capabilities.includes('can_orchestrate'));
  assert.ok(!byId.director.capabilities.includes('can_claim_tasks'), 'el Director orquesta, no toma historias');
  assert.ok(byId.legal_counsel.capabilities.includes('can_audit_compliance'));
  assert.equal(s.json.roles[0].id, 'director', 'quien orquesta va primero');
  assert.equal(s.json.role_registry.capabilities.length, 9);
  assert.deepEqual(s.json.role_registry.problems, []);
});

test('capacidades: el Director no toma historias y un rol sin can_review_tasks no dictamina', async () => {
  const t = await newTask();
  const dir = await action('claim_task', { task_id: t.id, role_key: 'director' });
  assert.equal(dir.status, 403, dir.text.slice(0, 200));
  assert.match(dir.text, /can_claim_tasks/);

  assert.equal((await action('claim_task', { task_id: t.id, role_key: 'backend_architect' })).status, 200);
  assert.equal((await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: EVIDENCE })).status, 200);

  const rc = await action('review_task', { task_id: t.id, decision: 'request_changes', checklist: ['falta algo'], reviewer_role: 'frontend_master' });
  assert.equal(rc.status, 403, 'pedir cambios también es dictaminar');
  assert.match(rc.text, /can_review_tasks/);

  const qa = await action('review_task', { task_id: t.id, decision: 'request_changes', checklist: ['falta una prueba'], reviewer_role: 'qa_security_guard' });
  assert.equal(qa.status, 200, qa.text.slice(0, 200));
});

test('brief: incluye las capacidades del rol', async () => {
  const b = await api('GET', 'index.php/roles/qa_security_guard/brief');
  assert.equal(b.status, 200);
  assert.ok(b.json.brief.capabilities.includes('can_review_tasks'));
  assert.match(b.json.brief.system_prompt, /can_review_tasks/);
});

test('roles del proyecto: un archivo en .agileforge/roles sustituye al del core en ese proyecto', async () => {
  const before = await api('GET', 'index.php/state');
  const originalProject = before.json.current_project.id;
  // En este proyecto, backend_architect pierde can_claim_tasks
  const ws = tmpWorkspace({
    'backend_architect.md': '---\nid: backend_architect\nname: "Backend sin tomar"\ncapabilities:\n  - can_submit_evidence\n---\n',
    'roto.md': '---\nid: roto\nname: x\ncapabilities: [can_volar]\n---\n',
  });
  try {
    const p = await action('create_project', { name: 'Proyecto roles ' + Date.now(), path: ws, client: 'web' });
    assert.equal(p.status, 200, p.text.slice(0, 200));
    const projId = p.json.project?.id || p.json.id;
    await action('switch_project', { project_id: projId });

    const s = await api('GET', 'index.php/state');
    const be = s.json.roles.find((r) => r.id === 'backend_architect');
    assert.deepEqual(be.capabilities, ['can_submit_evidence']);
    assert.equal(be.role_source, 'project');
    assert.equal(s.json.role_registry.problems.length, 1, 'el archivo roto se informa');
    assert.equal(s.json.role_registry.problems[0].file, 'roto.md');

    const t = await action('create_task', { title: 'Historia del proyecto con roles propios', assigned_to: 'backend_architect', project_id: projId, client: 'web' });
    assert.equal(t.status, 201);
    const claim = await action('claim_task', { task_id: t.json.task.id, role_key: 'backend_architect' });
    assert.equal(claim.status, 403, claim.text.slice(0, 200));
  } finally {
    await action('switch_project', { project_id: originalProject });
    fs.rmSync(ws, { recursive: true, force: true });
  }
  const after = await api('GET', 'index.php/state');
  assert.ok(after.json.roles.find((r) => r.id === 'backend_architect').capabilities.includes('can_claim_tasks'), 'en el proyecto original vuelve el core');
});

test('CLI: validate rechaza capacidades y herramientas desconocidas y el pack core pasa', () => {
  const ws = tmpWorkspace({ 'malo.md': '---\nid: malo\nname: x\ncapabilities: [can_todo]\ntools: [Bash, curl]\n---\n' });
  try {
    const bad = rolesCli(['validate', `--workspace=${ws}`]);
    assert.equal(bad.code, 1);
    assert.match(bad.out, /can_todo/);
    assert.match(bad.out, /curl/);
    assert.match(bad.out, /riesgo \(Bash\)/);
    fs.rmSync(path.join(ws, '.agileforge', 'roles', 'malo.md'));
    const ok = rolesCli(['validate', `--workspace=${ws}`]);
    assert.equal(ok.code, 0, ok.out);
    assert.match(ok.out, /8 archivo\(s\), 0 con errores/);
  } finally {
    fs.rmSync(ws, { recursive: true, force: true });
  }
});

test('CLI: agents genera el subagente con lista explícita de herramientas y el prefijo del entorno', () => {
  const ws = tmpWorkspace({
    'auditor_pci.md': '---\nid: auditor_pci\nname: "Auditor PCI"\ndescription: "Cumplimiento PCI-DSS"\nmodel: sonnet\ncapabilities: [can_claim_tasks, can_submit_evidence]\ntools: [Read, board__get_activity]\n---\n# Auditor\n',
  });
  try {
    fs.writeFileSync(path.join(ws, '.mcp.json'), JSON.stringify({ mcpServers: { 'af-board': { command: 'node', args: ['srv/agileforge-mcp.mjs'] } } }));
    const r = rolesCli(['agents', `--workspace=${ws}`]);
    assert.equal(r.code, 0, r.out);
    const file = path.join(ws, '.claude', 'agents', 'auditor-pci.md');
    const md = fs.readFileSync(file, 'utf8');
    assert.match(md, /^tools:$/m, 'siempre lista explícita: sin ella Claude Code daría todas');
    assert.match(md, /mcp__af-board__claim_task/);
    assert.match(md, /mcp__af-board__get_activity/);
    assert.doesNotMatch(md, /mcp__af-board__review_task/, 'sin can_review_tasks no recibe review_task');
    assert.match(md, /disallowedTools:\n {2}- Bash/);

    // Un archivo que no generó AgileForge no se sobrescribe
    fs.writeFileSync(file, 'mío');
    rolesCli(['agents', `--workspace=${ws}`]);
    assert.equal(fs.readFileSync(file, 'utf8'), 'mío');
  } finally {
    fs.rmSync(ws, { recursive: true, force: true });
  }
});
