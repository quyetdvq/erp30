import axios from 'axios'

import { i18n } from '@/i18n'
import { reportApiError } from '@/services/errorBoundary'
import { useAuthStore } from '@/stores/auth'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://api.monmin.net',
  timeout: 10_000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  config.headers.set('Accept-Language', i18n.global.locale.value)
  if (!config.headers.get('X-Request-ID')) {
    const requestId =
      typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `fe-${Date.now()}-${Math.random().toString(16).slice(2)}`
    config.headers.set('X-Request-ID', requestId)
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    const url = axios.isAxiosError(error) ? error.config?.url || '' : ''
    const status = axios.isAxiosError(error) ? error.response?.status : undefined
    if (status === 403 || status === 404) {
      useAuthStore().markPermissionSnapshotStale()
    }
    if (!(url.includes('/auth/me') && status === 401)) reportApiError(error)
    return Promise.reject(error)
  },
)
