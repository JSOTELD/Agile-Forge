#!/usr/bin/env node
// Pruebas de contrato de AgileForge. Uso: node tests/run.mjs
//
// Levanta la API en un puerto libre contra una base SQLite TEMPORAL (AGILEFORGE_DB_PATH),
// ejecuta tests/*.test.mjs con el runner nativo de Node y lo apaga todo al terminar.
// Nunca toca data/agileforge.sqlite. Sin dependencias: solo Node >= 18 y PHP >= 8.0 con pdo_sqlite.
// En Windows con XAMPP:  set AGILEFORGE_PHP=D:\xampp\php\php.exe
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const php = process.env.AGILEFORGE_PHP || 'php';
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'agileforge-test-'));
const dbPath = path.join(tmp, 'contract.sqlite');
// TMPDIR/TMP/TEMP propios: el límite de peticiones por minuto de la API se cuenta en la carpeta temporal,
// así las pruebas no comparten contador con el tablero real ni con una corrida anterior.
const env = { ...process.env, AGILEFORGE_DB_PATH: dbPath, TMPDIR: tmp, TMP: tmp, TEMP: tmp };

function fail(msg) {
  console.error(`\n[tests] ${msg}`);
  process.exit(1);
}

function freePort() {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.on('error', reject);
    srv.listen(0, '127.0.0.1', () => {
      const { port } = srv.address();
      srv.close(() => resolve(port));
    });
  });
}

function readEnvFile() {
  const file = path.join(root, '.env');
  const vars = {};
  if (!fs.existsSync(file)) return vars;
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (m) vars[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
  return vars;
}

async function waitForServer(base, child) {
  for (let i = 0; i < 60; i++) {
    if (child.exitCode !== null) fail('El servidor PHP terminó antes de arrancar.');
    try {
      await fetch(`${base}/api/index.php/state`);
      return;
    } catch {
      await new Promise((r) => setTimeout(r, 250));
    }
  }
  fail('El servidor PHP no respondió en 15 s.');
}

// 1. PHP disponible
const ver = spawnSync(php, ['-r', 'echo PHP_VERSION, extension_loaded("pdo_sqlite") ? "" : " SIN_PDO_SQLITE";'], { encoding: 'utf8' });
if (ver.error || ver.status !== 0) fail(`No se pudo ejecutar PHP ("${php}"). Define AGILEFORGE_PHP con la ruta de php.`);
if (ver.stdout.includes('SIN_PDO_SQLITE')) fail('PHP no tiene la extensión pdo_sqlite activada.');

// 2. Base temporal sembrada con el equipo de ejemplo (migrate crea .env solo si no existe)
const mig = spawnSync(php, [path.join(root, 'bin', 'migrate.php')], { cwd: root, env, encoding: 'utf8' });
if (mig.status !== 0) fail(`bin/migrate.php falló:\n${mig.stdout}\n${mig.stderr}`);
if (!fs.existsSync(dbPath)) fail('La migración no creó la base temporal: AGILEFORGE_DB_PATH no se está respetando.');

const tokens = readEnvFile();
if (!tokens.AGILEFORGE_API_TOKEN || !tokens.AGILEFORGE_VIEW_TOKEN) fail('Faltan AGILEFORGE_API_TOKEN / AGILEFORGE_VIEW_TOKEN en .env.');

// 3. Servidor PHP embebido
const port = await freePort();
const base = `http://127.0.0.1:${port}`;
const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', root, path.join(root, 'router.php')], { cwd: root, env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = '';
server.stderr.on('data', (d) => { serverLog += d; });

let code = 1;
try {
  await waitForServer(base, server);
  const files = fs.readdirSync(path.join(root, 'tests')).filter((f) => f.endsWith('.test.mjs')).sort().map((f) => path.join(root, 'tests', f));
  // Asíncrono a propósito: con spawnSync nadie leería el stderr de PHP y el servidor se bloquearía al llenarse el pipe.
  code = await new Promise((resolve) => {
    const run = spawn(process.execPath, ['--test', '--test-concurrency=1', ...files], {
      cwd: root,
      stdio: 'inherit',
      env: {
        ...env,
        AF_TEST_BASE: base,
        AF_TEST_TOKEN: tokens.AGILEFORGE_API_TOKEN,
        AF_TEST_VIEW_TOKEN: tokens.AGILEFORGE_VIEW_TOKEN,
        AF_TEST_ROOT: root,
      },
    });
    run.on('exit', (c) => resolve(c ?? 1));
    run.on('error', () => resolve(1));
  });
  const phpErrors = serverLog.split('\n').filter((l) => /PHP (Warning|Notice|Deprecated|Fatal)|Uncaught/.test(l));
  if (phpErrors.length) {
    console.error(`\n[tests] PHP emitió ${phpErrors.length} avisos o errores durante las pruebas:\n${[...new Set(phpErrors)].slice(0, 10).join('\n')}`);
    code = code || 1;
  }
} finally {
  server.kill();
  fs.rmSync(tmp, { recursive: true, force: true });
}
process.exit(code);
