export interface ApiErrorBody {
  error: {
    code: string
    message: string
    violations?: Record<string, string>
  }
}

export class ApiError extends Error {
  readonly code: string
  readonly status: number
  readonly violations: Record<string, string>

  constructor(status: number, body: ApiErrorBody | null) {
    super(body?.error.message ?? `Erreur inattendue (${status})`)
    this.name = 'ApiError'
    this.status = status
    this.code = body?.error.code ?? 'unknown'
    this.violations = body?.error.violations ?? {}
  }
}

interface ApiOptions {
  method?: string
  body?: unknown
}

/**
 * Single fetch wrapper for the whole app (same-origin through the Vite /api
 * proxy, session cookie included). Never use a raw fetch('/api/...') elsewhere.
 */
export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  let response: Response

  try {
    response = await fetch(path, {
      method: options.method ?? 'GET',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
    })
  } catch {
    throw new ApiError(0, {
      error: {
        code: 'network',
        message: 'Impossible de joindre le serveur. Vérifiez votre connexion.',
      },
    })
  }

  if (response.status === 204) {
    return undefined as T
  }

  const data = (await response.json().catch(() => null)) as ApiErrorBody | T | null

  if (!response.ok) {
    throw new ApiError(response.status, data as ApiErrorBody | null)
  }

  return data as T
}
