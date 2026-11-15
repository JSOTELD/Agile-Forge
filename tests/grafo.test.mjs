// Curador de grafos: bitácora del tablero → vault de Obsidian (scripts/bitacora.mjs) y contrato del agente.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { api, action, newTask, BASE, TOKEN, VIEW_TOKEN, ROOT } from './helpers.mjs';
import { normalizeRole } from '../plugin/agileforge/scripts/bitacora.mjs';

const SCRIPT = path.join(ROOT, 'plugin', 'agileforge', 'scripts', 'bitacora.mjs');
const sha = (s) => crypto.createHash('sha256').update(s).digest('hex').slice(0, 16);
const read = (...p) => fs.readFileSync(path.join(...p), 'utf8');

/** Ejecuta la bitácora aislada: sin .env del repo ni ~/.agileforge del usuario. */
function bitacora(args, { token = VIEW_TOKEN, tokenKey = 'AGILEFORGE_VIEW_TOKEN' } = {}) {
  const home = fs.mkdtempSync(path.join(os.tmpdir(), 'af-bit-home-'));
  const env = { HOME: home, USERPROFILE: home, AGILEFORGE_API_URL: `${BASE}/api`, AGILEFORGE_BITACORA_INTERVAL_MS: '0' };
  for (const k of ['PATH', 'Path', 'SystemRoot', 'TMPDIR', 'TMP', 'TEMP']) if (process.env[k] !== undefined) env[k] = process.env[k];
  if (token) env[tokenKey] = token;
  const r = spawnSync(process.execPath, [SCRIPT, ...args], { cwd: home, env, encoding: 'utf8' });
  fs.rmSync(home, { recursive: true, force: true });
  return { code: r.status, out: `${r.stdout}${r.stderr}` };
}

function tmpVault() {
  return path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'af-vault-')), 'vault');
}

test('bitácora: con el token de vista vuelca historias, decisiones y roles enlazados, y neutraliza el contenido hostil', async () => {
  const t = await newTask({ title: 'Historia para la bitácora [[trampa]] <b>', user_story: 'Como curador quiero la bitácora' });
  const com = await api('POST', `index.php/tasks/${t.id}/comments`, { role: 'backend_architect', content: 'Hola <script>alert(1)</script> ![[Secreto]]\n# Título falso', name: 'Backend' });
  assert.ok([200, 201].includes(com.status), com.text.slice(0, 200));
  assert.equal((await action('claim_task', { task_id: t.id, role_key: 'backend_architect' })).status, 200);
  const ev = { files_changed: [path.join(ROOT, 'api', 'Repository.php'), 'api/no-existe.php', 'api/Repository.php (migración v5)'], tests_run: ['node tests/run.mjs → ok'], summary: 'Entrega para comprobar la bitácora del curador' };
  assert.equal((await action('submit_for_review', { task_id: t.id, role_key: 'backend_architect', evidence: ev })).status, 200);
  const adr = await api('POST', 'index.php/decisions', { title: 'Decisión enlazada a la historia', context: `La historia ${t.id} necesita un grafo`, decision: 'Usar graphify para el grafo', consequences: 'El vault enlaza historia y decisión', status: 'accepted' });
  assert.ok([200, 201].includes(adr.status), adr.text.slice(0, 200));
  const adrId = adr.json.decision.id;

  const vault = tmpVault();
  // Nota de código como la que escribe `graphify export obsidian`
  fs.mkdirSync(path.join(vault, 'codigo'), { recursive: true });
  fs.writeFileSync(path.join(vault, 'codigo', 'Repository.php.md'), '---\nsource_file: "api/Repository.php"\ntype: "code"\n---\n\n# Repository.php\n');
  fs.writeFileSync(path.join(vault, 'codigo', '_COMMUNITY_Repository.md'), '# Repository\n');

  const r = bitacora(['--vault', vault, '--root', ROOT]);
  assert.equal(r.code, 0, r.out);
  assert.match(r.out, /token: vista/);
  assert.ok(!r.out.includes(VIEW_TOKEN) && !r.out.includes(TOKEN), 'la salida no muestra tokens');

  const story = read(vault, 'bitacora', 'historias', `${t.id}.md`);
  assert.match(story, /^---\nid: "US-/);
  assert.match(story, /generado_por: agileforge-bitacora/);
  assert.match(story, /\[\[bitacora\/roles\/backend_architect\|/);
  assert.match(story, /\[\[codigo\/Repository\.php\|api\/Repository\.php\]\]/, 'la ruta absoluta se enlaza con la nota del código');
  assert.match(story, /`api\/no-existe\.php`/, 'un archivo sin nota queda como texto');
  assert.match(story, /\[\[codigo\/Repository\.php\|api\/Repository\.php\]\] \(migración v5\)/, 'una ruta con anotación también se enlaza');
  const body = story.slice(story.indexOf('\n---\n', 4) + 5); // el frontmatter guarda el texto tal cual (YAML entre comillas)
  assert.doesNotMatch(body, /<script>|<b>/, 'sin HTML activo en el cuerpo');
  assert.doesNotMatch(body, /\[\[trampa\]\]|!\[\[Secreto\]\]/, 'sin wikilinks ni transclusiones del contenido');
  assert.doesNotMatch(body, /^# Título falso/m, 'el contenido no crea títulos');
  assert.doesNotMatch(story, /prompt_template|WORKSPACE|Actúa como/, 'no vuelca el prompt ni rutas del workspace');

  const decision = read(vault, 'bitacora', 'decisiones', `${adrId}.md`);
  assert.ok(decision.includes(`[[bitacora/historias/${t.id}|${t.id}]]`), 'la mención de la historia es un enlace');
  assert.ok(read(vault, 'bitacora', 'roles', 'backend_architect.md').includes(`[[bitacora/historias/${t.id}|`));
  assert.ok(fs.existsSync(path.join(vault, 'bitacora', '_indice.md')));
  assert.ok(fs.existsSync(path.join(vault, 'fuentes', '_plantilla.md')));
  const home = read(vault, 'Inicio.md');
  assert.match(home, /\[\[codigo\/_COMMUNITY_Repository\|Repository\]\]/);
  assert.match(home, /\[\[bitacora\/_indice\|/);
  fs.rmSync(path.dirname(vault), { recursive: true, force: true });
});

test('bitácora: incremental, no pisa notas ajenas ni editadas y limpia las que ya no existen', async () => {
  await newTask({ title: 'Historia para el modo incremental' });
  const vault = tmpVault();
  assert.equal(bitacora(['--vault', vault]).code, 0);

  const second = bitacora(['--vault', vault]);
  assert.equal(second.code, 0, second.out);
  assert.match(second.out, /escritas 0 /, 'sin cambios no reescribe nada');

  // 1) una nota generada que alguien editó  2) un archivo propio en una ruta que la bitácora querría usar
  // 3) una nota generada de una historia que ya no existe
  const manifestPath = path.join(vault, '.agileforge-bitacora.json');
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const edited = Object.keys(manifest.files).find((f) => f.startsWith('bitacora/historias/'));
  fs.appendFileSync(path.join(vault, edited), '\nMi anotación\n');
  fs.rmSync(path.join(vault, 'fuentes', '_plantilla.md'));
  delete manifest.files['fuentes/_plantilla.md'];
  fs.writeFileSync(path.join(vault, 'fuentes', '_plantilla.md'), 'mi plantilla');
  const ghost = 'bitacora/historias/US-99999.md';
  fs.writeFileSync(path.join(vault, ghost), 'vieja');
  manifest.files[ghost] = { hash: sha('vieja') };
  fs.writeFileSync(manifestPath, JSON.stringify(manifest));
  fs.writeFileSync(path.join(vault, 'notas', 'Idea.md'), '# Idea mía');
  fs.writeFileSync(path.join(vault, 'fuentes', 'graphify.md'), '---\ntitulo: "Graphify"\nautor: "Graphify Labs"\nanio: "2026"\nurl: "https://github.com/Graphify-Labs/graphify"\n---\n');

  const third = bitacora(['--vault', vault]);
  assert.equal(third.code, 0, third.out);
  assert.match(third.out, /editadas a mano[^\n]*bitacora\/historias\//);
  assert.match(third.out, /archivos tuyos[^\n]*fuentes\/_plantilla\.md/);
  assert.match(third.out, /eliminadas 1/);
  assert.match(read(vault, edited), /Mi anotación/);
  assert.equal(read(vault, 'fuentes', '_plantilla.md'), 'mi plantilla');
  assert.ok(!fs.existsSync(path.join(vault, ghost)));
  assert.equal(read(vault, 'notas', 'Idea.md'), '# Idea mía');
  assert.match(read(vault, 'fuentes', '_indice.md'), /Graphify Labs \(2026\)\. \*Graphify\*\..*\[\[fuentes\/graphify\|ficha\]\]/);
  assert.match(read(vault, 'Inicio.md'), /\[\[notas\/Idea\|Idea\]\]/);

  // Si el vault pasa a otro proyecto, las notas generadas del anterior (incluidas las de sprint) se retiran
  const m2 = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  m2.project = 'otro-proyecto';
  const oldSprint = 'bitacora/sprints/Sprint-99.md';
  fs.writeFileSync(path.join(vault, oldSprint), 'sprint viejo');
  m2.files[oldSprint] = { hash: sha('sprint viejo'), keep: true };
  fs.writeFileSync(manifestPath, JSON.stringify(m2));
  const fourth = bitacora(['--vault', vault]);
  assert.equal(fourth.code, 0, fourth.out);
  assert.ok(!fs.existsSync(path.join(vault, oldSprint)), 'la instantánea de sprint del otro proyecto se retira');
  fs.rmSync(path.dirname(vault), { recursive: true, force: true });
});

test('bitácora: --dry-run no escribe y las rutas peligrosas o sin token fallan', () => {
  const vault = tmpVault();
  const dry = bitacora(['--vault', vault, '--dry-run']);
  assert.equal(dry.code, 0, dry.out);
  assert.match(dry.out, /simulación/);
  assert.ok(!fs.existsSync(vault), 'la simulación no crea el vault');

  const repoRoot = bitacora(['--vault', ROOT, '--root', ROOT]);
  assert.equal(repoRoot.code, 2, 'no escribe en la raíz de un repositorio');
  assert.equal(bitacora([]).code, 2, 'sin --vault es un error de uso');
  assert.equal(bitacora(['--vault', vault, '--codigo', '../fuera']).code, 2);

  const noToken = bitacora(['--vault', vault], { token: null });
  assert.equal(noToken.code, 1);
  assert.match(noToken.out, /401/);
  assert.ok(!fs.existsSync(path.join(vault, 'bitacora')), 'sin acceso no escribe nada');
  fs.rmSync(path.dirname(vault), { recursive: true, force: true });
});

test('curador de grafos: no toca historias, su comando y su plantilla existen', () => {
  const md = read(ROOT, 'plugin', 'agileforge', 'agents', 'graph-curator.md');
  const fm = md.match(/^---\r?\n([\s\S]*?)\r?\n---/)[1];
  assert.match(fm, /^name: graph-curator$/m);
  for (const t of ['claim_task', 'submit_for_review', 'review_task', 'update_task_status', 'create_task', 'start_sprint', 'forget']) {
    assert.doesNotMatch(fm, new RegExp(`__${t}$`, 'm'), `el curador no debe tener ${t}`);
  }
  assert.match(md, /GRAPHIFY_BACKEND=none/, 'cero tokens por defecto');
  const cmd = read(ROOT, 'plugin', 'agileforge', 'commands', 'grafo.md');
  assert.match(cmd, /agileforge:graph-curator/);
  for (const ref of cmd.match(/\$\{CLAUDE_PLUGIN_ROOT\}\/[\w./-]+/g)) {
    assert.ok(fs.existsSync(path.join(ROOT, 'plugin', 'agileforge', ref.replace('${CLAUDE_PLUGIN_ROOT}/', ''))), `${ref} existe`);
  }
  assert.match(read(ROOT, '.gitignore'), /^\.agileforge\/vault\/$/m, 'el vault (copia del tablero) no se publica');
  assert.match(read(ROOT, '.gitignore'), /^graphify-out\/$/m);
  assert.equal(normalizeRole('chief_architect'), 'lead_architect');
  assert.equal(normalizeRole('backend'), 'backend_architect');
  assert.equal(normalizeRole('frontend'), 'frontend_master');
  assert.equal(normalizeRole('qa_security'), 'qa_security_guard');
  assert.equal(normalizeRole('legal_compliance'), 'legal_counsel');
});
