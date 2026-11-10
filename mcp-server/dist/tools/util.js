export function ok(data) {
    const text = typeof data === 'string' ? data : JSON.stringify(data);
    return { content: [{ type: 'text', text }] };
}
export function fail(err) {
    const msg = err instanceof Error ? err.message : String(err);
    return { content: [{ type: 'text', text: `Error: ${msg}` }], isError: true };
}
/** Ejecuta y devuelve JSON compacto; cualquier excepción pasa a isError con mensaje accionable. */
export async function run(fn, strip = []) {
    try {
        const data = await fn();
        if (data && typeof data === 'object' && !Array.isArray(data)) {
            for (const k of strip)
                delete data[k];
        }
        return ok(data);
    }
    catch (e) {
        return fail(e);
    }
}
export const READ_ONLY = { readOnlyHint: true, openWorldHint: false };
export const WRITE = { readOnlyHint: false, destructiveHint: false, openWorldHint: false };
