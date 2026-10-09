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

let csrfToken: string | null = null
let csrfBootstrap: Promise<string> | null = null
const mutationMethods = new Set(['post', 'put', 'patch', 'delete'])

/** Drop the session-bound token after Shield rotates or destroys the session. */
export function invalidateCsrfToken(): void {
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
    csrfBootstrap = apiClient
      .get('/api/v1/auth/csrf')
      .then((response) => {
        storeCsrfToken(response)
        if (!csrfToken) throw new Error('CSRF bootstrap did not return a token.')
        return csrfToken
      })
      .finally(() => {
        csrfBootstrap = null
      })
  }
  return csrfBootstrap
}

apiClient.interceptors.request.use(async (config) => {
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
    storeCsrfToken(response)
    return response
  },
  (error: unknown) => {
    const url = axios.isAxiosError(error) ? error.config?.url || '' : ''
    const status = axios.isAxiosError(error) ? error.response?.status : undefined
    if (status === 403 || status === 404) {
      useAuthStore().markPermissionSnapshotStale()
    }
    if ((status === 403 || status === 419) && url !== '/api/v1/auth/csrf') {
      invalidateCsrfToken()
    }
    if (!(url.includes('/auth/me') && status === 401)) reportApiError(error)
    return Promise.reject(error)
  },
)
