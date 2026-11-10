// Utilidades compartidas por las pruebas de contrato. Las variables AF_TEST_* las define tests/run.mjs.
import http from 'node:http';

export const BASE = process.env.AF_TEST_BASE;
export const TOKEN = process.env.AF_TEST_TOKEN;
export const VIEW_TOKEN = process.env.AF_TEST_VIEW_TOKEN;
export const ROOT = process.env.AF_TEST_ROOT;

if (!BASE || !TOKEN) {
  throw new Error('Ejecuta las pruebas con: node tests/run.mjs');
}

/** Petición a la API. Devuelve { status, json, text }. token: null = sin cabecera de autenticación. */
export async function api(method, route, body, token = TOKEN) {
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(`${BASE}/api/${route}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* respuesta no JSON */ }
  return { status: res.status, json, text };
}

/** Acción del despachador api/tasks.php. */
export function action(name, params = {}, token = TOKEN) {
  return api('POST', 'tasks.php', { action: name, ...params }, token);
}

/** Petición con cabeceras arbitrarias (fetch no deja cambiar Host). */
export function rawGet(route, headers) {
  const url = new URL(`${BASE}/api/${route}`);
  return new Promise((resolve, reject) => {
    const req = http.request({ host: url.hostname, port: url.port, path: url.pathname, method: 'GET', headers }, (res) => {
      res.resume();
      res.on('end', () => resolve(res.statusCode));
    });
    req.on('error', reject);
    req.end();
  });
}

let seq = 0;
/** Crea una historia de prueba y devuelve su id. */
export async function newTask(overrides = {}) {
  seq += 1;
  const r = await action('create_task', {
    title: `Historia de contrato ${seq}`,
    assigned_to: 'backend_architect',
    story_points: 3,
    priority: 'media',
    user_story: 'Como equipo quiero una historia de prueba',
    dod: 'La prueba pasa',
    client: 'web',
    ...overrides,
  });
  if (r.status !== 201 || !r.json?.task?.id) throw new Error(`create_task falló: ${r.status} ${r.text.slice(0, 200)}`);
  return r.json.task;
}
