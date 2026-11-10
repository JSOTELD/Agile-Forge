/** Cliente con escritura (POST). Solo lo importan el bundle completo y los adaptadores. */
import { ApiError, ReadClient } from './client.js';

export class WriteClient extends ReadClient {
  async post<T = any>(route: string, body: unknown, timeoutMs = this.timeoutMs): Promise<T> {
    const url = this.urlFor(route);
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: this.headers({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(body ?? {}),
        signal: AbortSignal.timeout(timeoutMs),
      });
      return (await this.settle(res, url)) as T;
    } catch (err: any) {
      throw err instanceof ApiError ? err : this.netError(err, url);
    }
  }
}
