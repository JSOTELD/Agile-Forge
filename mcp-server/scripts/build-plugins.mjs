// Genera los bundles de los plugins con esbuild. Uso: npm run bundle
import { build } from 'esbuild';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const plugins = path.resolve(root, '..', 'plugin');

const targets = [
  { entry: 'src/index.ts', out: path.join(plugins, 'agileforge', 'server', 'agileforge-mcp.mjs') },
  { entry: 'src/viewer.ts', out: path.join(plugins, 'agileforge-vista', 'server', 'agileforge-vista-mcp.mjs') },
];

// Cabecera: aviso (el shebang lo conserva esbuild desde el entry) + require() para dependencias CommonJS dentro del bundle ESM.
const banner = [
  '// archivo generado — no editar; npm run bundle (mcp-server/scripts/build-plugins.mjs)',
  "import { createRequire as __afCreateRequire } from 'node:module';",
  'const require = __afCreateRequire(import.meta.url);',
].join('\n');

for (const t of targets) {
  fs.mkdirSync(path.dirname(t.out), { recursive: true });
  await build({
    entryPoints: [path.join(root, t.entry)],
    outfile: t.out,
    bundle: true,
    format: 'esm',
    platform: 'node',
    target: 'node18',
    minify: true,
    legalComments: 'none',
    banner: { js: banner },
    logLevel: 'warning',
  });
  fs.chmodSync(t.out, 0o755);
  console.log(`${path.relative(path.resolve(root, '..'), t.out)}  ${(fs.statSync(t.out).size / 1024).toFixed(0)} KB`);
}
