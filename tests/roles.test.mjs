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

test('M2b CLI: agents soporta --target=antigravity, --target=gemini y --target=all de forma aditiva', () => {
  const ws = tmpWorkspace({
    'auditor_gemini.md': '---\nid: auditor_gemini\nname: "Auditor Gemini"\ndescription: "Auditoría multi-host"\nmodel: argon\ncapabilities: [can_claim_tasks, can_submit_evidence]\ntools: [Read, board__get_activity]\n---\n# Auditor Gemini\n',
  });
  try {
    fs.writeFileSync(path.join(ws, '.mcp.json'), JSON.stringify({ mcpServers: { 'af-board': { command: 'node', args: ['srv/agileforge-mcp.mjs'] } } }));

    // 1. --target=antigravity genera .gemini/agents/*.md y .agents/skills/af-*/SKILL.md sin romper .claude/agents
    const anti = rolesCli(['agents', `--workspace=${ws}`, '--target=antigravity']);
    assert.equal(anti.code, 0, anti.out);
    const geminiAgent = path.join(ws, '.gemini', 'agents', 'auditor-gemini.md');
    const antiSkill = path.join(ws, '.agents', 'skills', 'af-auditor-gemini', 'SKILL.md');
    assert.ok(fs.existsSync(geminiAgent), 'falta .gemini/agents/auditor-gemini.md');
    assert.ok(fs.existsSync(antiSkill), 'falta .agents/skills/af-auditor-gemini/SKILL.md');
    const geminiMd = fs.readFileSync(geminiAgent, 'utf8');
    const skillMd = fs.readFileSync(antiSkill, 'utf8');
    assert.match(geminiMd, /^model:\s*gemini-4-argon$/m, 'alias argon debe resolverse a gemini-4-argon en target Gemini');
    assert.match(geminiMd, /mcp__af-board__claim_task/);
    assert.match(skillMd, /^name:\s*af-auditor-gemini$/m);
    assert.match(skillMd, /<!-- generado por AgileForge/);
    assert.doesNotMatch(skillMd, /review_task/, 'sin can_review_tasks no recibe review_task');
    assert.ok(!fs.existsSync(path.join(ws, '.claude', 'agents', 'auditor-gemini.md')), '--target=antigravity no debe crear .claude/agents');

    // Protección contra sobrescritura manual si falta el MARKER
    fs.writeFileSync(geminiAgent, 'custom-gemini');
    fs.writeFileSync(antiSkill, 'custom-skill');
    rolesCli(['agents', `--workspace=${ws}`, '--target=antigravity']);
    assert.equal(fs.readFileSync(geminiAgent, 'utf8'), 'custom-gemini');
    assert.equal(fs.readFileSync(antiSkill, 'utf8'), 'custom-skill');

    // 2. --target=all con --force genera claude, gemini y antigravity skill juntos
    const allRes = rolesCli(['agents', `--workspace=${ws}`, '--target=all', '--force']);
    assert.equal(allRes.code, 0, allRes.out);
    const claudeFile = path.join(ws, '.claude', 'agents', 'auditor-gemini.md');
    assert.ok(fs.existsSync(claudeFile), '--target=all debe generar .claude/agents');
    assert.match(fs.readFileSync(claudeFile, 'utf8'), /^model:\s*argon$/m, 'en target claude se conserva el modelo declarado');
    assert.match(fs.readFileSync(geminiAgent, 'utf8'), /^model:\s*gemini-4-argon$/m);
    assert.match(fs.readFileSync(antiSkill, 'utf8'), /<!-- generado por AgileForge/);

    // 3. --target=gemini sobre un segundo workspace solo crea .gemini/agents
    const wsGem = tmpWorkspace({
      'rol_gem.md': '---\nid: rol_gem\nname: "Rol Gem"\ndescription: "Prueba"\nmodel: flash\ncapabilities: [can_claim_tasks]\ntools: [Read]\n---\n',
    });
    try {
      const gemOnly = rolesCli(['agents', `--workspace=${wsGem}`, '--target=gemini']);
      assert.equal(gemOnly.code, 0, gemOnly.out);
      const gemOnlyFile = path.join(wsGem, '.gemini', 'agents', 'rol-gem.md');
      assert.ok(fs.existsSync(gemOnlyFile));
      assert.match(fs.readFileSync(gemOnlyFile, 'utf8'), /^model:\s*gemini-3\.8-flash$/m);
      assert.ok(!fs.existsSync(path.join(wsGem, '.agents', 'skills', 'af-rol-gem', 'SKILL.md')));
    } finally {
      fs.rmSync(wsGem, { recursive: true, force: true });
    }

    // 4. Target inválido responde código de salida 2
    const badTarget = rolesCli(['agents', `--workspace=${ws}`, '--target=invalido']);
    assert.equal(badTarget.code, 2);
  } finally {
    fs.rmSync(ws, { recursive: true, force: true });
  }
});

test('M2a y BUG-08: seedDirectorRole, catálogo/costos Gemini y googleFunctionDeclarations', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'af-m2a-'));
  const db = path.join(dir, 'm2a.sqlite');
  const phpBin = process.env.AGILEFORGE_PHP || PHP;
  try {
    const phpScript = [
      'require_once "api/bootstrap.php";',
      'require_once "api/Database.php";',
      'require_once "api/Repository.php";',
      'require_once "api/services/LlmService.php";',
      'require_once "api/services/BoardService.php";',
      'require_once "api/services/EngineService.php";',
      'require_once "api/services/DirectorService.php";',
      '$pdo = \\AgileForge\\Database::getPdo();',
      '$dirRole = $pdo->query("SELECT id, ai_provider, ai_model FROM roles WHERE id = \'director\'")->fetch(PDO::FETCH_ASSOC);',
      '$rm = new ReflectionMethod(\\AgileForge\\Database::class, "resolveModelId");',
      '$rm->setAccessible(true);',
      '$resolved = [',
      '  "argon"      => $rm->invoke(null, [], "google", "argon"),',
      '  "hydrogen"   => $rm->invoke(null, [], "google", "hydrogen"),',
      '  "flash38"    => $rm->invoke(null, [], "google", "gemini-3.8-flash"),',
      '  "pro25"      => $rm->invoke(null, [], "google", "gemini-2.5-pro"),',
      '  "flash25"    => $rm->invoke(null, [], "google", "gemini-2.5-flash"),',
      '];',
      '$pdo->exec("INSERT OR IGNORE INTO projects (id, name, path, active) VALUES (\'default\', \'Default\', \'.\', 1)");',
      '$pdo->exec("INSERT OR IGNORE INTO roles (id, name, alias, avatar, ai_provider, ai_model) VALUES (\'backend_architect\', \'Backend Architect\', \'Back\', \'⚙️\', \'google\', \'gemini-4-hydrogen\')");',
      '\\AgileForge\\Database::normalizeImported($pdo);',
      '$llm = new \\AgileForge\\Services\\LlmService();',
      '$costs = [',
      '  "gemini-4-argon"             => $llm->calculateCost("google", "gemini-4-argon", 1000000, 1000000),',
      '  "gemini-4-argon-xhigh"       => $llm->calculateCost("google", "gemini-4-argon-xhigh", 1000000, 1000000),',
      '  "gemini-4-hydrogen"          => $llm->calculateCost("google", "gemini-4-hydrogen", 1000000, 1000000),',
      '  "gemini-3.8-flash"           => $llm->calculateCost("google", "gemini-3.8-flash", 1000000, 1000000),',
      '  "gemini-2.5-pro"             => $llm->calculateCost("google", "gemini-2.5-pro", 1000000, 1000000),',
      '  "gemini-2.5-flash"           => $llm->calculateCost("google", "gemini-2.5-flash", 1000000, 1000000),',
      '];',
      '$norm25p = $llm->normalizeModel("google", "gemini-2.5-pro");',
      '$norm25f = $llm->normalizeModel("google", "gemini-2.5-flash");',
      '$norm31p = $llm->normalizeModel("google", "gemini-3.1-pro");',
      '$norm3f  = $llm->normalizeModel("google", "gemini-3-flash");',
      '$repo = new \\AgileForge\\Repository($pdo);',
      '$board = new \\AgileForge\\Services\\BoardService($repo);',
      '$eng = new \\AgileForge\\Services\\EngineService($repo);',
      '$director = new \\AgileForge\\Services\\DirectorService($repo, $board, $eng);',
      '$decls = $director->googleFunctionDeclarations();',
      '// Probar guardas de BUG-05 en DirectorService::executeTool',
      '$created = $director->executeTool("crear_historia", ["titulo" => "Historia Director Guard", "asignado_a" => "backend_architect"]);',
      '$tid = $created["id"] ?? "";',
      '$revBypass = $director->executeTool("actualizar_historia", ["task_id" => $tid, "estado" => "revision"]);',
      '$pdo->prepare("UPDATE tasks SET locked_by = ?, locked_at = CURRENT_TIMESTAMP WHERE id = ?")->execute(["backend_architect", $tid]);',
      '$lockBypass = $director->executeTool("actualizar_historia", ["task_id" => $tid, "titulo" => "Intento salto lock"]);',
      '$pdo->exec("INSERT OR IGNORE INTO projects (id, name, path, active) VALUES (\'other_proj\', \'Otro\', \'.\', 0)");',
      '$pdo->exec("INSERT INTO tasks (id, project_id, title, assigned_to, priority, status) VALUES (\'US-OTHER-1\', \'other_proj\', \'Ajena\', \'backend_architect\', \'media\', \'backlog\')");',
      '$crossUpd = $director->executeTool("actualizar_historia", ["task_id" => "US-OTHER-1", "titulo" => "Hack"]);',
      '$crossCom = $director->executeTool("comentar_historia", ["task_id" => "US-OTHER-1", "comentario" => "Nota"]);',
      'echo json_encode([',
      '  "director" => $dirRole,',
      '  "resolved" => $resolved,',
      '  "costs"    => $costs,',
      '  "norm25p"  => $norm25p,',
      '  "norm25f"  => $norm25f,',
      '  "norm31p"  => $norm31p,',
      '  "norm3f"   => $norm3f,',
      '  "decls"    => $decls,',
      '  "tid"      => $tid,',
      '  "revBypass" => $revBypass,',
      '  "lockBypass" => $lockBypass,',
      '  "crossUpd" => $crossUpd,',
      '  "crossCom" => $crossCom,',
      ']);',
    ].join("\n");
    const out = execFileSync(phpBin, ['-r', phpScript], {
      cwd: ROOT,
      env: { ...process.env, AGILEFORGE_DB_PATH: db },
      encoding: 'utf8',
    });
    const res = JSON.parse(out);

    // seedDirectorRole usa pack core director.md (google / gemini-4-argon)
    assert.equal(res.director.ai_provider, 'google');
    assert.equal(res.director.ai_model, 'gemini-4-argon');

    // resolveModelId y normalizeModel
    assert.deepEqual(res.resolved, {
      argon: 'gemini-4-argon',
      hydrogen: 'gemini-4-hydrogen',
      flash38: 'gemini-3.8-flash',
      pro25: 'gemini-2.5-pro',
      flash25: 'gemini-2.5-flash',
    });
    assert.equal(res.norm25p, 'gemini-2.5-pro');
    assert.equal(res.norm25f, 'gemini-2.5-flash');
    assert.equal(res.norm31p, 'gemini-3.1-pro-preview');
    assert.equal(res.norm3f, 'gemini-3-flash-preview');

    // Costos Gemini (1M input + 1M output = input_cost_per_m + output_cost_per_m)
    assert.equal(res.costs['gemini-4-argon'], 24.0);
    assert.equal(res.costs['gemini-4-argon-xhigh'], 24.0);
    assert.equal(res.costs['gemini-4-hydrogen'], 9.0);
    assert.equal(res.costs['gemini-3.8-flash'], 1.75);
    assert.equal(res.costs['gemini-2.5-pro'], 11.25);
    assert.equal(res.costs['gemini-2.5-flash'], 2.8);

    // googleFunctionDeclarations: parameters (no input_schema), sin enum vacíos, sin revision en actualizar_historia
    assert.ok(Array.isArray(res.decls) && res.decls.length >= 6);
    for (const d of res.decls) {
      assert.equal(d.input_schema, undefined, `${d.name} no debe exponer input_schema en Gemini`);
      assert.ok(d.parameters && d.parameters.type === 'object', `${d.name} debe tener parameters`);
      const props = d.parameters.properties;
      if (props !== undefined) {
        assert.ok(!Array.isArray(props), `${d.name}.parameters.properties no debe serializarse como array`);
        for (const [k, v] of Object.entries(props)) {
          if (v.enum !== undefined) {
            assert.ok(Array.isArray(v.enum) && v.enum.length > 0, `${d.name}.${k}.enum no debe estar vacío`);
            assert.equal(v.type, 'string', `${d.name}.${k}.enum solo debe enviarse en propiedades string en Gemini`);
          }
        }
      }
    }
    const updDecl = res.decls.find((d) => d.name === 'actualizar_historia');
    assert.ok(!updDecl.parameters.properties.estado.enum.includes('revision'));

    // BUG-05: guardas del Director respetan proyecto activo, locks y prohibición de mover directo a revision
    assert.ok(typeof res.tid === 'string' && res.tid.startsWith('US-'), 'crear_historia debe devolver id');
    assert.equal(res.revBypass.ok, false);
    assert.ok(!/no existe/i.test(res.revBypass.error || ''), `revBypass debe probar la historia real: ${res.revBypass.error}`);
    assert.equal(res.lockBypass.ok, false);
    assert.ok(!/no existe/i.test(res.lockBypass.error || ''), `lockBypass debe probar la historia real: ${res.lockBypass.error}`);
    assert.equal(res.crossUpd.ok, false);
    assert.equal(res.crossCom.ok, false);
    assert.match(res.crossCom.error || '', /pertenece al proyecto/i);
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
