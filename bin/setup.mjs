#!/usr/bin/env node
/**
 * AgileForge - Script de Preparación e Instalación Idempotente (RFC-002 §2.2)
 * Uso: npm run setup
 */
import { spawnSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// 1. Validar runtime de Node.js (mínimo 22.13.0)
const [major, minor] = process.versions.node.split('.').map(Number);
if (major < 22 || (major === 22 && minor < 13)) {
  console.error(`✗ Error: Se requiere Node.js >= 22.13.0 (versión detectada: v${process.versions.node}).`);
  console.error('  Descarga la versión LTS recomendada desde https://nodejs.org/');
  process.exit(1);
}

// 2. Localizar PHP (AGILEFORGE_PHP -> PATH -> rutas habituales)
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
  console.error('✗ Error: No se encontró el ejecutable de PHP (se requiere PHP 8.0+ para la fase híbrida).');
  console.error('  Instala PHP o XAMPP desde:');
  console.error('    - PHP oficial: https://www.php.net/downloads');
  console.error('    - XAMPP: https://www.apachefriends.org/');
  console.error('  O define la variable de entorno AGILEFORGE_PHP con la ruta completa a php.exe.');
  process.exit(1);
}

console.log('[*] Verificando entorno de AgileForge...');

// 3. Gestión idempotente de .env (nunca imprime tokens)
const envFile = path.join(ROOT, '.env');
const envExampleFile = path.join(ROOT, '.env.example');

function parseEnvVars(content) {
  const vars = {};
  for (const line of content.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx !== -1) {
      const key = trimmed.slice(0, idx).trim();
      const val = trimmed.slice(idx + 1).trim().replace(/^["']|["']$/g, '');
      vars[key] = val;
    }
  }
  return vars;
}

function isPlaceholderToken(val) {
  return !val || /^(tu_|your_|changeme|<)/i.test(val);
}

if (fs.existsSync(envFile)) {
  let content = fs.readFileSync(envFile, 'utf8');
  const vars = parseEnvVars(content);
  let modified = false;

  if (isPlaceholderToken(vars.AGILEFORGE_API_TOKEN)) {
    const newToken = crypto.randomBytes(32).toString('hex');
    if (/^\s*AGILEFORGE_API_TOKEN\s*=/m.test(content)) {
      content = content.replace(/^\s*AGILEFORGE_API_TOKEN\s*=.*$/m, `AGILEFORGE_API_TOKEN=${newToken}`);
    } else {
      content += `\nAGILEFORGE_API_TOKEN=${newToken}`;
    }
    modified = true;
  }

  if (isPlaceholderToken(vars.AGILEFORGE_VIEW_TOKEN)) {
    const newToken = crypto.randomBytes(32).toString('hex');
    if (/^\s*AGILEFORGE_VIEW_TOKEN\s*=/m.test(content)) {
      content = content.replace(/^\s*AGILEFORGE_VIEW_TOKEN\s*=.*$/m, `AGILEFORGE_VIEW_TOKEN=${newToken}`);
    } else {
      content += `\nAGILEFORGE_VIEW_TOKEN=${newToken}`;
    }
    modified = true;
  }

  if (modified) {
    fs.writeFileSync(envFile, content, { encoding: 'utf8', mode: 0o600 });
    console.log('[+] .env actualizado con tokens de acceso seguros.');
  } else {
    console.log('[*] .env existente: se conserva intacto.');
  }
} else {
  let baseContent = '';
  if (fs.existsSync(envExampleFile)) {
    baseContent = fs.readFileSync(envExampleFile, 'utf8');
  }
  const apiToken = crypto.randomBytes(32).toString('hex');
  const viewToken = crypto.randomBytes(32).toString('hex');

  let newContent = baseContent;
  if (/^\s*AGILEFORGE_API_TOKEN\s*=/m.test(newContent)) {
    newContent = newContent.replace(/^\s*AGILEFORGE_API_TOKEN\s*=.*$/m, `AGILEFORGE_API_TOKEN=${apiToken}`);
  } else {
    newContent += `\nAGILEFORGE_API_TOKEN=${apiToken}`;
  }

  if (/^\s*AGILEFORGE_VIEW_TOKEN\s*=/m.test(newContent)) {
    newContent = newContent.replace(/^\s*AGILEFORGE_VIEW_TOKEN\s*=.*$/m, `AGILEFORGE_VIEW_TOKEN=${viewToken}`);
  } else {
    newContent += `\nAGILEFORGE_VIEW_TOKEN=${viewToken}`;
  }

  if (!/^\s*AGILEFORGE_DB_PATH\s*=/m.test(newContent)) {
    newContent += '\nAGILEFORGE_DB_PATH=data/agileforge.sqlite';
  }
  if (!/^\s*AGILEFORGE_ENV\s*=/m.test(newContent)) {
    newContent += '\nAGILEFORGE_ENV=local';
  }

  fs.writeFileSync(envFile, newContent, { encoding: 'utf8', mode: 0o600 });
  console.log('[+] .env creado con tokens de acceso criptográficamente seguros.');
}

// 4. Crear estructura de almacenamiento local
for (const sub of ['data', 'data/backup', 'data/logs']) {
  const dir = path.join(ROOT, sub);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

// 5. Dependencias y compilación de mcp-server
const mcpDir = path.join(ROOT, 'mcp-server');
if (fs.existsSync(mcpDir)) {
  const nodeModulesDir = path.join(mcpDir, 'node_modules');
  const npmCmd = process.platform === 'win32' ? 'npm.cmd' : 'npm';
  
  if (!fs.existsSync(nodeModulesDir)) {
    console.log('[*] Instalando dependencias de mcp-server...');
    const ci = spawnSync(npmCmd, ['ci'], { cwd: mcpDir, stdio: 'inherit' });
    if (ci.status !== 0) {
      // Fallback a npm install si npm ci falla
      spawnSync(npmCmd, ['install'], { cwd: mcpDir, stdio: 'inherit' });
    }
  }

  // Verificar si los bundles existen; si faltan, construirlos
  const bundle1 = path.join(ROOT, 'plugin', 'agileforge', 'server', 'agileforge-mcp.mjs');
  const bundle2 = path.join(ROOT, 'plugin', 'agileforge-vista', 'server', 'agileforge-vista-mcp.mjs');
  const dist = path.join(mcpDir, 'dist', 'index.js');
  if (!fs.existsSync(bundle1) || !fs.existsSync(bundle2) || !fs.existsSync(dist)) {
    console.log('[*] Compilando TypeScript y generando bundles de plugin...');
    spawnSync(npmCmd, ['run', 'build'], { cwd: mcpDir, stdio: 'inherit' });
    spawnSync(npmCmd, ['run', 'bundle'], { cwd: mcpDir, stdio: 'inherit' });
  }
}

// 6. Inicializar esquema de SQLite (aditivo, nunca --force)
const migrateScript = path.join(ROOT, 'bin', 'migrate.php');
if (fs.existsSync(migrateScript)) {
  const mig = spawnSync(php, [migrateScript], {
    cwd: ROOT,
    encoding: 'utf8',
    env: { ...process.env, AGILEFORGE_PHP: php },
  });
  if (mig.status !== 0) {
    console.error(`✗ Error en la migración de la base de datos:\n${mig.stdout}\n${mig.stderr}`);
    process.exit(1);
  }
}

console.log('\n========================================================================');
console.log('✓ AgileForge configurado correctamente.');
console.log('========================================================================');
console.log('Siguientes pasos:');
console.log('  1. Diagnóstico de salud del entorno:');
console.log('     npm run doctor');
console.log('');
console.log('  2. Iniciar el panel web:');
console.log('     npm start');
console.log('     (Acceso local en http://127.0.0.1:8080)');
console.log('');
console.log('  3. Instalar el plugin en Claude Code:');
console.log('     claude plugin install agileforge');
console.log('     (O para desarrollo local: claude plugin dev ./plugin/agileforge)');
console.log('========================================================================\n');
