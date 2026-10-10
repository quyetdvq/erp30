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

type RequestSession = { __dinkerpSessionVersion?: number; __dinkerpCsrfEpoch?: number }

function requestSessionVersion(config: RequestSession | undefined): number | undefined {
  return config?.__dinkerpSessionVersion
}

let csrfToken: string | null = null
let csrfEpoch = 0
let csrfBootstrap: Promise<string> | null = null
const mutationMethods = new Set(['post', 'put', 'patch', 'delete'])

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
  // Avoid ASI joining the previous numeric result to the next parenthesized cast.
  const metadata = config as typeof config & RequestSession
  metadata.__dinkerpSessionVersion = useAuthStore().getSessionGeneration()
  metadata.__dinkerpCsrfEpoch = csrfEpoch
  config.headers.set('Accept-Language', i18n.global.locale.value)
  if (!config.headers.get('X-Request-ID')) {
    const requestId =
      typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `fe-${Date.now()}-${Math.random().toString(16).slice(2)}`
    config.headers.set('X-Request-ID', requestId)
  }
  // The API enforces session CSRF for browser mutations in every deployed mode.
  // Do not key this security requirement off Vite's build mode: a misconfigured
  // live server can still report MODE=development while serving production traffic.
  if (mutationMethods.has((config.method || '').toLowerCase())) {
    config.headers.set('X-CSRF-TOKEN', await ensureCsrfToken())
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => {
    // Ignore session-bound tokens returned by a request from the previous user.
    const version = requestSessionVersion(response.config as unknown as RequestSession)
    const epoch = (response.config as unknown as RequestSession).__dinkerpCsrfEpoch
    if (version === useAuthStore().getSessionGeneration() && epoch === csrfEpoch) {
      storeCsrfToken(response)
    }
    return response
  },
  (error: unknown) => {
    const url = axios.isAxiosError(error) ? error.config?.url || '' : ''
    const status = axios.isAxiosError(error) ? error.response?.status : undefined
    const auth = useAuthStore()
    const version = requestSessionVersion(
      axios.isAxiosError(error) ? (error.config as unknown as RequestSession) : undefined,
    )
    // A late failure from a logged-out/superseded session must not revoke the new
    // user's permissions, CSRF token, or UI state.
    if (version !== undefined && version !== auth.getSessionGeneration()) {
      return Promise.reject(error)
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
  },
)
