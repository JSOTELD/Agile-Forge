export type ToolResult = { content: { type: 'text'; text: string }[]; isError?: boolean };

export function ok(data: unknown): ToolResult {
  const text = typeof data === 'string' ? data : JSON.stringify(data);
  return { content: [{ type: 'text', text }] };
}

export function fail(err: unknown): ToolResult {
  const msg = err instanceof Error ? err.message : String(err);
  return { content: [{ type: 'text', text: `Error: ${msg}` }], isError: true };
}

/** Ejecuta y devuelve JSON compacto; cualquier excepción pasa a isError con mensaje accionable. */
export async function run(fn: () => Promise<unknown>, strip: string[] = []): Promise<ToolResult> {
  try {
    const data: any = await fn();
    if (data && typeof data === 'object' && !Array.isArray(data)) {
      for (const k of strip) delete data[k];
    }
    return ok(data);
  } catch (e) {
    return fail(e);
  }
}

export const READ_ONLY = { readOnlyHint: true, openWorldHint: false } as const;
export const WRITE = { readOnlyHint: false, destructiveHint: false, openWorldHint: false } as const;
