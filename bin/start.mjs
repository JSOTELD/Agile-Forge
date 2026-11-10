#!/usr/bin/env node
/**
 * AgileForge - Arranque del Panel Web (PHP Embebido)
 * Uso: npm start
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function findPhp() {
  if (process.env.AGILEFORGE_PHP) {
    return process.env.AGILEFORGE_PHP;
  }
  // Verificar en rutas habituales de Windows
  if (process.platform === 'win32') {
    const candidates = [
      'C:\\xampp\\php\\php.exe',
      'D:\\xampp\\php\\php.exe',
      'C:\\php\\php.exe',
      'C:\\Program Files\\PHP\\php.exe',
    ];
    for (const c of candidates) {
      if (fs.existsSync(c)) return c;
    }
  }
  return 'php';
}

const php = findPhp();
const host = process.env.AGILEFORGE_HOST || '127.0.0.1';
const port = process.env.AGILEFORGE_PORT || '8080';

console.log(`[AgileForge] Iniciando servidor web embebido en http://${host}:${port} ...`);
console.log('[AgileForge] Presiona Ctrl+C para detener el servidor.\n');

const child = spawn(php, ['-S', `${host}:${port}`, '-t', ROOT, path.join(ROOT, 'router.php')], {
  cwd: ROOT,
  stdio: 'inherit',
});

child.on('error', (err) => {
  console.error(`[AgileForge] Error al iniciar PHP ("${php}"): ${err.message}`);
  process.exit(1);
});

child.on('exit', (code) => {
  process.exit(code ?? 0);
});

for (const sig of ['SIGINT', 'SIGTERM']) {
  process.on(sig, () => {
    child.kill(sig);
  });
}
