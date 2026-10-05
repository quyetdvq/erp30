import axios from 'axios'

import { i18n } from '@/i18n'

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
