// tests/setup.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { ROOT } from './helpers.mjs';

test('doctor termina con código 0 en un entorno sano', () => {
  const php = process.env.AGILEFORGE_PHP || 'php';
  const res = spawnSync(process.execPath, [path.join(ROOT, 'bin', 'doctor.mjs')], {
    cwd: ROOT,
    encoding: 'utf8',
    env: { ...process.env, AGILEFORGE_PHP: php },
  });
  assert.equal(res.status, 0, `doctor falló con código ${res.status}:\n${res.stdout}\n${res.stderr}`);
  assert.match(res.stdout, /✓ Diagnóstico: Entorno saludable/);
});

test('la salida de setup y de doctor no contiene el valor de ningún token', () => {
  const php = process.env.AGILEFORGE_PHP || 'php';

  let tokens = [];
  const envFile = path.join(ROOT, '.env');
  if (fs.existsSync(envFile)) {
    const content = fs.readFileSync(envFile, 'utf8');
    for (const line of content.split(/\r?\n/)) {
      const m = line.match(/^\s*(AGILEFORGE_[A-Z_]*TOKEN)\s*=\s*(.+)$/);
      if (m && m[2].trim().length >= 16) {
        tokens.push(m[2].trim().replace(/^["']|["']$/g, ''));
      }
    }
  }
  if (process.env.AF_TEST_TOKEN) tokens.push(process.env.AF_TEST_TOKEN);
  if (process.env.AF_TEST_VIEW_TOKEN) tokens.push(process.env.AF_TEST_VIEW_TOKEN);
  tokens = [...new Set(tokens.filter((t) => t && t.length >= 16))];

  // 1. Ejecutar doctor
  const doc = spawnSync(process.execPath, [path.join(ROOT, 'bin', 'doctor.mjs')], {
    cwd: ROOT,
    encoding: 'utf8',
    env: { ...process.env, AGILEFORGE_PHP: php },
  });
  const docOut = (doc.stdout || '') + (doc.stderr || '');

  // 2. Ejecutar setup
  const set = spawnSync(process.execPath, [path.join(ROOT, 'bin', 'setup.mjs')], {
    cwd: ROOT,
    encoding: 'utf8',
    env: { ...process.env, AGILEFORGE_PHP: php },
  });
  const setOut = (set.stdout || '') + (set.stderr || '');

  for (const tok of tokens) {
    assert.ok(!docOut.includes(tok), `doctor imprimió el valor de un token: ${tok.slice(0, 6)}...`);
    assert.ok(!setOut.includes(tok), `setup imprimió el valor de un token: ${tok.slice(0, 6)}...`);
  }
});
