// Coherencia del repositorio: versiones, manifiestos del plugin y datos que no deben publicarse.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { ROOT } from './helpers.mjs';

const read = (...p) => fs.readFileSync(path.join(ROOT, ...p), 'utf8');
const json = (...p) => JSON.parse(read(...p));

function walk(dir, ext, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (e.name === 'node_modules' || e.name === '.git') continue;
    const full = path.join(dir, e.name);
    if (e.isDirectory()) walk(full, ext, out);
    else if (e.name.endsWith(ext)) out.push(full);
  }
  return out;
}

test('la versión es la misma en plugins, marketplace, MCP y bundles', () => {
  const version = json('plugin', 'agileforge', '.claude-plugin', 'plugin.json').version;
  assert.match(version, /^\d+\.\d+\.\d+$/);
  assert.equal(json('plugin', 'agileforge-vista', '.claude-plugin', 'plugin.json').version, version);
  assert.equal(json('mcp-server', 'package.json').version, version);
  for (const p of json('.claude-plugin', 'marketplace.json').plugins) {
    if (p.version !== undefined) assert.equal(p.version, version, `marketplace: ${p.name}`);
  }
  for (const f of ['index.ts', 'viewer.ts']) {
    assert.ok(read('mcp-server', 'src', f).includes(`VERSION = '${version}'`), `mcp-server/src/${f}`);
  }
  for (const b of [['plugin', 'agileforge', 'server', 'agileforge-mcp.mjs'], ['plugin', 'agileforge-vista', 'server', 'agileforge-vista-mcp.mjs']]) {
    assert.ok(read(...b).includes(`"${version}"`), `bundle sin regenerar: ${b.join('/')} (npm run bundle)`);
  }
  assert.ok(read('CHANGELOG.md').includes(`## [${version}]`), 'CHANGELOG.md sin sección para esta versión');
});

test('cada skill y cada agente del plugin tiene name y description', () => {
  const files = [
    ...walk(path.join(ROOT, 'plugin', 'agileforge', 'skills'), 'SKILL.md'),
    ...walk(path.join(ROOT, 'plugin', 'agileforge', 'agents'), '.md'),
  ];
  assert.ok(files.length > 0);
  for (const f of files) {
    const m = fs.readFileSync(f, 'utf8').match(/^---\r?\n([\s\S]*?)\r?\n---/);
    assert.ok(m, `${path.relative(ROOT, f)}: sin frontmatter`);
    assert.match(m[1], /^name:\s*\S/m, `${path.relative(ROOT, f)}: falta name`);
    assert.match(m[1], /^description:\s*\S/m, `${path.relative(ROOT, f)}: falta description`);
  }
});

test('los agentes declaran sus herramientas de forma explícita', () => {
  for (const f of walk(path.join(ROOT, 'plugin', 'agileforge', 'agents'), '.md')) {
    const fm = fs.readFileSync(f, 'utf8').match(/^---\r?\n([\s\S]*?)\r?\n---/)[1];
    assert.match(fm, /^tools:/m, `${path.relative(ROOT, f)}: sin "tools" hereda TODAS las herramientas`);
  }
});

test('el plugin no trae un .mcp.json duplicado', () => {
  assert.ok(!fs.existsSync(path.join(ROOT, 'plugin', 'agileforge', '.mcp.json')), 'plugin/agileforge/.mcp.json duplica el servidor de plugin.json');
});

test('config/models.json y data/team.example.json son JSON válido y coherente', () => {
  const models = json('config', 'models.json');
  const example = json('data', 'team.example.json');
  assert.equal(example.roles.length, 8);
  assert.ok(models && typeof models === 'object');
  assert.doesNotMatch(read('data', 'team.example.json'), /[A-Za-z]:\\\\|xampp|@[a-z0-9-]+\.[a-z]{2,}/i, 'el ejemplo contiene rutas o correos personales');
});

test('git no versiona datos locales ni secretos', (t) => {
  const r = spawnSync('git', ['ls-files'], { cwd: ROOT, encoding: 'utf8' });
  if (r.status !== 0) return t.skip('no es un repositorio git');
  const tracked = r.stdout.split('\n');
  const forbidden = tracked.filter((f) => f === '.env' || f === 'data/team.json' || /\.sqlite(-wal|-shm)?$/.test(f) || f.startsWith('data/logs/') || f.startsWith('scratch/'));
  assert.deepEqual(forbidden, []);
});

test('todos los PHP y los módulos JS tienen sintaxis válida', () => {
  const php = process.env.AGILEFORGE_PHP || 'php';
  for (const f of ['api', 'bin', 'views'].flatMap((d) => (fs.existsSync(path.join(ROOT, d)) ? walk(path.join(ROOT, d), '.php') : [])).concat(walk(ROOT, '.php').filter((f) => path.dirname(f) === ROOT))) {
    const r = spawnSync(php, ['-l', f], { encoding: 'utf8' });
    assert.equal(r.status, 0, `${path.relative(ROOT, f)}: ${r.stdout}${r.stderr}`);
  }
  for (const f of walk(path.join(ROOT, 'assets', 'js'), '.js')) {
    const r = spawnSync(process.execPath, ['--check', f], { encoding: 'utf8' });
    assert.equal(r.status, 0, `${path.relative(ROOT, f)}: ${r.stderr}`);
  }
});

test('Etapa 2 D4: 28 avatares PNG (oscuro + -light) y soporte reactivo en roles.js y ui.js', () => {
  const roles = [
    'director', 'creative_director', 'lead_architect', 'backend_architect',
    'frontend_master', 'qa_security_guard', 'legal_counsel', 'project_manager',
    'tester', 'graph_curator', 'devops_release', 'data_performance',
    'mcp_integrator', 'a11y_ux_auditor',
  ];
  const pngSig = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  const avatarsDir = path.join(ROOT, 'assets', 'brand', 'avatars');
  for (const id of roles) {
    for (const file of [`${id}.png`, `${id}-light.png`]) {
      const full = path.join(avatarsDir, file);
      assert.ok(fs.existsSync(full), `falta avatar ${file}`);
      const buf = fs.readFileSync(full);
      assert.ok(buf.length > 100, `avatar vacío: ${file}`);
      assert.ok(buf.subarray(0, 8).equals(pngSig), `avatar no es PNG válido: ${file}`);
    }
  }

  const rolesJs = read('assets', 'js', 'roles.js');
  assert.match(rolesJs, /data-avatar-id=/);
  assert.match(rolesJs, /-light\.png/);
  assert.match(rolesJs, /data-tried-dark/);
  for (const id of ['graph_curator', 'devops_release', 'data_performance', 'mcp_integrator', 'a11y_ux_auditor', 'tester']) {
    assert.match(rolesJs, new RegExp(`\\b${id}\\s*:`), `falta SHORT_LABELS para ${id} en roles.js`);
  }

  const uiJs = read('assets', 'js', 'ui.js');
  assert.match(uiJs, /\.role-avatar\[data-avatar-id\]/);
  assert.match(uiJs, /-light\.png/);

  for (const slug of ['devops-release', 'data-performance', 'mcp-integrator', 'a11y-ux-auditor']) {
    assert.ok(fs.existsSync(path.join(ROOT, 'plugin', 'agileforge', 'agents', `${slug}.md`)), `falta subagente ${slug}.md`);
  }
});
