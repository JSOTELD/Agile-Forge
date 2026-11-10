#!/usr/bin/env node
/**
 * AgileForge - Diagnóstico Único de Salud del Entorno (RFC-002 §2.3)
 * Uso: npm run doctor
 * Solo lectura: no modifica ningún archivo ni base de datos.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

let hasError = false;

function pass(title, detail = '') {
  console.log(`  ✓ ${title}${detail ? ` (${detail})` : ''}`);
}

function warn(title, detail = '', action = '') {
  console.log(`  ⚠ ${title}${detail ? ` (${detail})` : ''}`);
  if (action) {
    console.log(`    ↳ Acción: ${action}`);
  }
}

function fail(title, detail = '', action = '') {
  hasError = true;
  console.log(`  ✗ ${title}${detail ? ` (${detail})` : ''}`);
  if (action) {
    console.log(`    ↳ Acción: ${action}`);
  }
}

console.log('\n========================================================================');
console.log('🩺 AgileForge Doctor - Diagnóstico del Entorno');
console.log('========================================================================\n');

// 1. Runtime Node.js
console.log('[1/7] Runtime Node.js');
const [nodeMajor, nodeMinor] = process.versions.node.split('.').map(Number);
if (nodeMajor > 22 || (nodeMajor === 22 && nodeMinor >= 13)) {
  pass(`Versión de Node.js: v${process.versions.node}`, '>= 22.13.0 requerido');
} else {
  fail(
    `Versión de Node.js: v${process.versions.node}`,
    'incompatible',
    'Actualiza Node.js a la versión LTS recomendada (>= 22.13.0) desde https://nodejs.org/'
  );
}

// 2. Runtime PHP y extensiones (fase híbrida)
console.log('\n[2/7] Runtime PHP (Fase Híbrida)');
function findPhp() {
  if (process.env.AGILEFORGE_PHP) {
    if (fs.existsSync(process.env.AGILEFORGE_PHP)) {
      return process.env.AGILEFORGE_PHP;
    }
    try {
      const r = spawnSync(process.env.AGILEFORGE_PHP, ['-v'], { stdio: 'ignore' });
      if (r.status === 0) return process.env.AGILEFORGE_PHP;
    } catch {}
  }

  try {
    const r = spawnSync('php', ['-v'], { stdio: 'ignore' });
    if (r.status === 0) return 'php';
  } catch {}

  const candidates = process.platform === 'win32'
    ? [
        'C:\\xampp\\php\\php.exe',
        'D:\\xampp\\php\\php.exe',
        'C:\\php\\php.exe',
        'C:\\Program Files\\PHP\\php.exe',
      ]
    : [
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/homebrew/bin/php',
      ];

  for (const c of candidates) {
    if (fs.existsSync(c)) return c;
  }
  return null;
}

const php = findPhp();
if (!php) {
  fail(
    'Ejecutable PHP',
    'no encontrado',
    'Instala PHP 8.0+ o define la variable AGILEFORGE_PHP con la ruta completa a php.exe'
  );
} else {
  const verCheck = spawnSync(php, ['-r', 'echo PHP_VERSION;'], { encoding: 'utf8' });
  if (verCheck.status !== 0 || verCheck.error) {
    fail('Ejecución de PHP', 'falló al invocar php', 'Verifica que PHP tenga permisos de ejecución y PATH válido.');
  } else {
    const phpVer = verCheck.stdout.trim();
    const [pMaj] = phpVer.split('.').map(Number);
    if (pMaj >= 8) {
      pass(`Versión de PHP: v${phpVer}`, php);
    } else {
      fail(`Versión de PHP: v${phpVer}`, 'inferior a 8.0', 'Actualiza PHP a la versión 8.0 o superior.');
    }

    // Extensiones requeridas
    const modCheck = spawnSync(php, ['-m'], { encoding: 'utf8' });
    const mods = (modCheck.stdout || '').toLowerCase();
    for (const ext of ['pdo_sqlite', 'mbstring', 'curl']) {
      if (mods.includes(ext.toLowerCase())) {
        pass(`Extensión PHP: ${ext}`, 'habilitada');
      } else {
        fail(
          `Extensión PHP: ${ext}`,
          'no habilitada',
          `Habilita extension=${ext} en tu archivo php.ini y reinicia el servicio.`
        );
      }
    }
  }
}

// 3. Almacenamiento y Base de Datos SQLite
console.log('\n[3/7] Almacenamiento y Base de Datos SQLite');
const dataDir = path.join(ROOT, 'data');
if (fs.existsSync(dataDir)) {
  try {
    fs.accessSync(dataDir, fs.constants.R_OK | fs.constants.W_OK);
    pass('Directorio data/', 'accesible con permisos de lectura/escritura');
  } catch {
    fail('Directorio data/', 'sin permisos de lectura/escritura', 'Asigna permisos de lectura/escritura a data/');
  }
} else {
  fail('Directorio data/', 'no existe', 'Ejecuta npm run setup para inicializar las carpetas requeridas.');
}

const dbPath = process.env.AGILEFORGE_DB_PATH
  ? (path.isAbsolute(process.env.AGILEFORGE_DB_PATH)
      ? process.env.AGILEFORGE_DB_PATH
      : path.join(ROOT, process.env.AGILEFORGE_DB_PATH))
  : path.join(dataDir, 'agileforge.sqlite');

if (fs.existsSync(dbPath)) {
  try {
    fs.accessSync(dbPath, fs.constants.R_OK | fs.constants.W_OK);
    pass(`Base de datos SQLite: ${path.relative(ROOT, dbPath)}`, 'presente y con permisos');
  } catch {
    fail('Base de datos SQLite', 'sin permisos de lectura/escritura', `Asigna permisos a ${dbPath}`);
  }

  if (php) {
    const walCheck = spawnSync(
      php,
      ['-r', `$pdo = new PDO('sqlite:${dbPath.replace(/\\/g, '/')}'); echo $pdo->query('PRAGMA journal_mode')->fetchColumn();`],
      { encoding: 'utf8' }
    );
    const jMode = (walCheck.stdout || '').trim().toLowerCase();
    if (jMode === 'wal') {
      pass('Modo SQLite WAL', 'activo (PRAGMA journal_mode = wal)');
    } else {
      warn(
        'Modo SQLite WAL',
        `modo actual: ${jMode || 'desconocido'}`,
        'Ejecuta npm run setup para activar el modo WAL y optimizar concurrencia.'
      );
    }
  }
} else {
  fail(
    'Base de datos SQLite',
    'no encontrada',
    'Ejecuta npm run setup para crear e inicializar la base de datos.'
  );
}

// 4. Servidor MCP y Bundles
console.log('\n[4/7] Servidor MCP y Bundles de Plugin');
const bundleOfficial = path.join(ROOT, 'plugin', 'agileforge', 'server', 'agileforge-mcp.mjs');
const bundleVista = path.join(ROOT, 'plugin', 'agileforge-vista', 'server', 'agileforge-vista-mcp.mjs');
const mcpDist = path.join(ROOT, 'mcp-server', 'dist', 'index.js');

if (fs.existsSync(bundleOfficial)) {
  pass('Bundle oficial de plugin', 'plugin/agileforge/server/agileforge-mcp.mjs');
} else {
  fail(
    'Bundle oficial de plugin',
    'falta bundle',
    'Ejecuta npm run setup o ejecuta npm run build && npm run bundle en mcp-server/'
  );
}

if (fs.existsSync(bundleVista)) {
  pass('Bundle de plugin Vista (solo lectura)', 'plugin/agileforge-vista/server/agileforge-vista-mcp.mjs');
} else {
  fail(
    'Bundle de plugin Vista',
    'falta bundle',
    'Ejecuta npm run setup o ejecuta npm run build && npm run bundle en mcp-server/'
  );
}

if (fs.existsSync(mcpDist)) {
  pass('Servidor MCP compilado', 'mcp-server/dist/index.js');
} else {
  fail(
    'Servidor MCP compilado',
    'falta build',
    'Ejecuta npm run build en la carpeta mcp-server/'
  );
}

// Coherencia de versiones
try {
  const pJson = JSON.parse(fs.readFileSync(path.join(ROOT, 'plugin', 'agileforge', '.claude-plugin', 'plugin.json'), 'utf8'));
  const mJson = JSON.parse(fs.readFileSync(path.join(ROOT, 'mcp-server', 'package.json'), 'utf8'));
  if (pJson.version === mJson.version) {
    pass(`Coherencia de versión: v${pJson.version}`, 'plugin.json y mcp-server/package.json coinciden');
  } else {
    fail(
      'Coherencia de versión',
      `plugin.json (${pJson.version}) != mcp-server (${mJson.version})`,
      'Sincroniza la versión en plugin.json y mcp-server/package.json.'
    );
  }
} catch (e) {
  fail('Lectura de manifiestos', e.message, 'Verifica la integridad de los archivos de configuración.');
}

// 5. Tokens y Seguridad
console.log('\n[5/7] Tokens y Seguridad');
const envFile = path.join(ROOT, '.env');
if (fs.existsSync(envFile)) {
  pass('Archivo .env', 'presente en la raíz');
  let envContent = '';
  try {
    envContent = fs.readFileSync(envFile, 'utf8');
  } catch {}

  function getEnvVal(k) {
    const m = envContent.match(new RegExp(`^\\s*${k}\\s*=\\s*(.*)$`, 'm'));
    return m ? m[1].trim().replace(/^["']|["']$/g, '') : '';
  }

  const apiTok = getEnvVal('AGILEFORGE_API_TOKEN');
  if (apiTok && !/^(tu_|your_|changeme|<)/i.test(apiTok)) {
    pass('Token de API (AGILEFORGE_API_TOKEN)', `configurado (${apiTok.length} caracteres; origen: .env)`);
  } else {
    fail(
      'Token de API (AGILEFORGE_API_TOKEN)',
      'falta o está vacío',
      'Ejecuta npm run setup para generar un token criptográfico seguro.'
    );
  }

  const viewTok = getEnvVal('AGILEFORGE_VIEW_TOKEN');
  if (viewTok && !/^(tu_|your_|changeme|<)/i.test(viewTok)) {
    pass('Token de Vista (AGILEFORGE_VIEW_TOKEN)', `configurado (${viewTok.length} caracteres; origen: .env)`);
  } else {
    fail(
      'Token de Vista (AGILEFORGE_VIEW_TOKEN)',
      'falta o está vacío',
      'Ejecuta npm run setup para generar un token de vista seguro.'
    );
  }
} else {
  fail(
    'Archivo .env',
    'no existe',
    'Ejecuta npm run setup para inicializar el entorno y generar los tokens.'
  );
}

// 6. Conectividad de Proveedores IA (Informativo, nunca ✗)
console.log('\n[6/7] Proveedores de Inteligencia Artificial (Informativo)');
const envVars = fs.existsSync(envFile)
  ? Object.fromEntries(
      fs.readFileSync(envFile, 'utf8')
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter((l) => l && !l.startsWith('#') && l.includes('='))
        .map((l) => {
          const i = l.indexOf('=');
          return [l.slice(0, i).trim(), l.slice(i + 1).trim().replace(/^["']|["']$/g, '')];
        })
    )
  : {};

const providers = [
  { name: 'Anthropic Claude', key: 'ANTHROPIC_API_KEY' },
  { name: 'OpenAI GPT', key: 'OPENAI_API_KEY' },
  { name: 'Google Gemini', key: 'GEMINI_API_KEY' },
  { name: 'DeepSeek', key: 'DEEPSEEK_API_KEY' },
];

for (const p of providers) {
  const val = envVars[p.key] || process.env[p.key];
  if (val && !/^(tu_|your_|changeme|<)/i.test(val)) {
    pass(`Proveedor ${p.name}`, `clave configurada (${val.length} caracteres)`);
  } else {
    warn(`Proveedor ${p.name}`, 'sin clave configurada en .env');
  }
}

// Comprobación de IA Local (Ollama/Colibri) con timeout de 500ms
try {
  const res = await fetch('http://127.0.0.1:11434', { signal: AbortSignal.timeout(500) });
  if (res.ok || res.status === 200) {
    pass('IA Local (Ollama/Colibri)', 'respondiendo en http://127.0.0.1:11434');
  } else {
    warn('IA Local (Ollama/Colibri)', `puerto 11434 respondió código HTTP ${res.status}`);
  }
} catch {
  warn('IA Local (Ollama/Colibri)', 'no detectada en puerto 11434 (opcional)');
}
console.log('    ℹ Nota: El modo plugin en Claude Code usa el modelo del cliente; las claves en .env son solo para el panel web.');

// 7. Estado del Panel Web (Informativo, ⚠ si no está arrancado)
console.log('\n[7/7] Estado del Panel Web');
const port = process.env.AGILEFORGE_PORT || envVars.AGILEFORGE_PORT || '8080';
const host = process.env.AGILEFORGE_HOST || envVars.AGILEFORGE_HOST || '127.0.0.1';
const panelUrl = `http://${host}:${port}`;

try {
  const res = await fetch(panelUrl, { signal: AbortSignal.timeout(600) });
  if (res.status < 500) {
    pass('Panel Web', `respondiendo en ${panelUrl}`);
  } else {
    warn('Panel Web', `código HTTP ${res.status} en ${panelUrl}`);
  }
} catch {
  warn('Panel Web', `no detectado en ${panelUrl}`, 'Inicia el panel con "npm start" cuando desees usar la interfaz visual.');
}

console.log('\n========================================================================');
if (hasError) {
  console.log('✗ Diagnóstico: Se encontraron problemas que requieren atención.');
  console.log('  Revisa las acciones indicadas arriba para resolverlos.\n');
  process.exit(1);
} else {
  console.log('✓ Diagnóstico: Entorno saludable y listo para operar.');
  console.log('========================================================================\n');
  process.exit(0);
}
