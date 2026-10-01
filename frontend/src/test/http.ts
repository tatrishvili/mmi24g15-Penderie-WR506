import { vi } from 'vitest'

export interface StubResponse {
  status: number
  body: unknown
}

/**
 * Replaces global fetch with a programmable stub.
 * The handler receives the request URL (and init) and answers { status, body }.
 */
export function stubFetch(handler: (url: string, init?: RequestInit) => StubResponse): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: unknown, init?: RequestInit) => {
      const { status, body } = handler(String(url), init)

      return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
      }
    }),
  )
}

export function jsonBody(init?: RequestInit): Record<string, unknown> {
  return JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>
}

export function unauthorized(): StubResponse {
  return {
    status: 401,
    body: { error: { code: 'unauthenticated', message: 'Authentication required.' } },
  }
}
