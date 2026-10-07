import axios from 'axios'

import { i18n } from '@/i18n'
import { reportApiError } from '@/services/errorBoundary'

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
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    const url = axios.isAxiosError(error) ? error.config?.url || '' : ''
    const status = axios.isAxiosError(error) ? error.response?.status : undefined
    if (!(url.includes('/auth/me') && status === 401)) reportApiError(error)
    return Promise.reject(error)
  },
)
