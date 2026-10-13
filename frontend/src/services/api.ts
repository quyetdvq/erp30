import axios from 'axios'

import { i18n } from '@/i18n'
import { reportApiError } from '@/services/errorBoundary'
import { useAuthStore } from '@/stores/auth'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'https://api.monmin.net',
  timeout: 10_000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

type RequestSession = {
  __dinkerpSessionVersion?: number
  __dinkerpCsrfEpoch?: number
  __dinkerpReleaseCsrfTurn?: () => void
}

function requestSessionVersion(config: RequestSession | undefined): number | undefined {
  return config?.__dinkerpSessionVersion
}

let csrfToken: string | null = null
let csrfEpoch = 0
let csrfBootstrap: Promise<string> | null = null
const mutationMethods = new Set(['post', 'put', 'patch', 'delete'])

// CodeIgniter regenerates the SESSION CSRF hash after each protected request.
// Concurrent POST lookups must not submit the same hash before either response
// can replace it. Hold this turn until the response/error interceptor runs.
let csrfTurn: Promise<void> = Promise.resolve()
async function acquireCsrfTurn(): Promise<() => void> {
  const previous = csrfTurn
  let release!: () => void
  csrfTurn = new Promise<void>((resolve) => {
    release = resolve
  })
  await previous
  return release
}

function releaseCsrfTurn(config: RequestSession | undefined): void {
  const release = config?.__dinkerpReleaseCsrfTurn
  if (release) {
    config.__dinkerpReleaseCsrfTurn = undefined
    release()
  }
}

/** Drop the session-bound token after Shield rotates or destroys the session. */
export function invalidateCsrfToken(): void {
  ++csrfEpoch
  csrfToken = null
  csrfBootstrap = null
}

export async function refreshCsrfToken(): Promise<string> {
  invalidateCsrfToken()
  return ensureCsrfToken()
}

function storeCsrfToken(response: { headers?: Record<string, unknown>; data?: unknown }): void {
  const header = response.headers?.['x-csrf-token']
  const payload = response.data as { data?: { csrf_token?: unknown } } | undefined
  const token =
    typeof header === 'string' && header !== ''
      ? header
      : typeof payload?.data?.csrf_token === 'string'
        ? payload.data.csrf_token
        : null
  if (token) csrfToken = token
}

async function ensureCsrfToken(): Promise<string> {
  if (csrfToken) return csrfToken
  if (!csrfBootstrap) {
    const version = useAuthStore().getSessionGeneration()
    const epoch = csrfEpoch
    const pending = apiClient.get('/api/v1/auth/csrf').then((response) => {
      if (version !== useAuthStore().getSessionGeneration() || epoch !== csrfEpoch) {
        throw new Error('Session changed while fetching CSRF token.')
      }
      storeCsrfToken(response)
      if (!csrfToken) throw new Error('CSRF bootstrap did not return a token.')
      return csrfToken
    })
    const cleanup = pending.finally(() => {
      // A superseded bootstrap must not clear a newer session's bootstrap.
      if (csrfBootstrap === cleanup) csrfBootstrap = null
    })
    csrfBootstrap = cleanup
  }
  return csrfBootstrap
}

apiClient.interceptors.request.use(async (config) => {
  const metadata = config as typeof config & RequestSession
  metadata.__dinkerpSessionVersion = useAuthStore().getSessionGeneration()
  config.headers.set('Accept-Language', i18n.global.locale.value)
  if (!config.headers.get('X-Request-ID')) {
    const requestId =
      typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `fe-${Date.now()}-${Math.random().toString(16).slice(2)}`
    config.headers.set('X-Request-ID', requestId)
  }
  if (!mutationMethods.has((config.method || '').toLowerCase())) {
    metadata.__dinkerpCsrfEpoch = csrfEpoch
    return config
  }

  // This also covers read-only POST projections such as sales-uom-options.
  // All protected requests must wait for the preceding response's rotated token.
  metadata.__dinkerpReleaseCsrfTurn = await acquireCsrfTurn()
  try {
    if (metadata.__dinkerpSessionVersion !== useAuthStore().getSessionGeneration()) {
      throw new Error('Session changed while waiting for the CSRF token.')
    }
    metadata.__dinkerpCsrfEpoch = csrfEpoch
    const token = await ensureCsrfToken()
    if (
      metadata.__dinkerpSessionVersion !== useAuthStore().getSessionGeneration() ||
      metadata.__dinkerpCsrfEpoch !== csrfEpoch
    ) {
      throw new Error('Session or CSRF token changed while preparing the request.')
    }
    config.headers.set('X-CSRF-TOKEN', token)
    return config
  } catch (error) {
    releaseCsrfTurn(metadata)
    throw error
  }
})

apiClient.interceptors.response.use(
  (response) => {
    const metadata = response.config as typeof response.config & RequestSession
    const method = (response.config.method || '').toLowerCase()
    try {
      // A late GET response might carry a CSRF hash from BEFORE a POST rotation.
      // Only the explicit bootstrap GET and protected request responses can
      // update the token shared by subsequent protected requests.
      if (
        metadata.__dinkerpSessionVersion === useAuthStore().getSessionGeneration() &&
        metadata.__dinkerpCsrfEpoch === csrfEpoch &&
        (mutationMethods.has(method) || response.config.url === '/api/v1/auth/csrf')
      ) {
        storeCsrfToken(response)
      }
      return response
    } finally {
      releaseCsrfTurn(metadata)
    }
  },
  (error: unknown) => {
    const config = axios.isAxiosError(error) ? error.config : undefined
    const metadata = config as (typeof config & RequestSession) | undefined
    const url = config?.url || ''
    const status = axios.isAxiosError(error) ? error.response?.status : undefined
    try {
      const auth = useAuthStore()
      const version = requestSessionVersion(metadata)
      // A late failure from a logged-out/superseded session must not revoke
      // the new user's permissions, CSRF token, or UI state.
      if (version !== undefined && version !== auth.getSessionGeneration()) {
        return Promise.reject(error)
      }
      // The CSRF hash also rotates for a valid POST that returns 409/422.
      // Preserve that returned hash instead of sending a stale one next time.
      if (
        axios.isAxiosError(error) &&
        error.response &&
        metadata?.__dinkerpCsrfEpoch === csrfEpoch &&
        mutationMethods.has((config?.method || '').toLowerCase())
      ) {
        storeCsrfToken(error.response)
      }
      // A 404 can mean an absent or invisible record, not that Base Permissions changed.
      // For an actual 403, refresh the authenticated snapshot once in the background;
      // never automatically retry a denied mutation.
      if (status === 403 && !url.startsWith('/api/v1/auth/')) {
        auth.markPermissionSnapshotStale()
        void auth.refreshBasePermissions().catch(() => undefined)
      }
      if (
        status === 401 &&
        (!url.startsWith('/api/v1/auth/') || (url === '/api/v1/auth/me' && auth.isAuthenticated))
      ) {
        invalidateCsrfToken()
        auth.expireSession()
      }
      if ((status === 403 || status === 419) && url !== '/api/v1/auth/csrf') {
        invalidateCsrfToken()
      }
      if (!(url.includes('/auth/me') && status === 401)) reportApiError(error)
      return Promise.reject(error)
    } finally {
      releaseCsrfTurn(metadata)
    }
  },
)
