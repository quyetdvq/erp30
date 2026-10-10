import axios from 'axios'
import { ref } from 'vue'

export type ApiErrorNotice = {
  id: string
  status: 401 | 403 | 404 | 409 | 422 | 500
  code: string | null
  message: string | null
  requestId: string | null
}

export const errorNotices = ref<ApiErrorNotice[]>([])
const reportedErrors = new WeakMap<object, ApiErrorNotice>()

function headerValue(headers: unknown, name: string): string | null {
  if (!headers) return null
  if (typeof (headers as { get?: (key: string) => string | null }).get === 'function') {
    return (headers as { get: (key: string) => string | null }).get(name) || null
  }
  const record = headers as Record<string, unknown>
  const value = record[name] ?? record[name.toLowerCase()] ?? record[name.toUpperCase()]
  return typeof value === 'string' ? value : null
}

function payloadOf(error: unknown): Record<string, unknown> {
  if (!axios.isAxiosError(error)) return {}
  const data = error.response?.data
  if (!data || typeof data !== 'object') return {}
  return data as Record<string, unknown>
}

export function normalizeApiError(error: unknown): ApiErrorNotice | null {
  if (!axios.isAxiosError(error) || axios.isCancel(error)) return null
  const status = error.response?.status
  if (![401, 403, 404, 409, 422, 500].includes(status || 0)) return null
  const payload = payloadOf(error)
  const nested = payload.error && typeof payload.error === 'object' ? payload.error : {}
  const nestedRecord = nested as Record<string, unknown>
  const code = payload.code || nestedRecord.code
  const message = payload.message || nestedRecord.message
  const requestId =
    headerValue(error.response?.headers, 'x-request-id') ||
    (typeof payload.request_id === 'string' ? payload.request_id : null) ||
    (typeof (payload.meta as { request_id?: unknown } | undefined)?.request_id === 'string'
      ? (payload.meta as { request_id: string }).request_id
      : null)
  const id = `${status}:${String(code || 'unknown')}:${requestId || Date.now()}`
  return {
    id,
    status: status as ApiErrorNotice['status'],
    code: typeof code === 'string' ? code : null,
    message: typeof message === 'string' ? message : null,
    requestId,
  }
}

export function reportApiError(error: unknown): ApiErrorNotice | null {
  if (error !== null && typeof error === 'object') {
    const existing = reportedErrors.get(error)
    if (existing) return existing
  }
  const notice = normalizeApiError(error)
  if (!notice) return null
  if (error !== null && typeof error === 'object') reportedErrors.set(error, notice)
  if (errorNotices.value.some((candidate) => candidate.id === notice.id)) return notice
  errorNotices.value = [...errorNotices.value.slice(-2), notice]
  return notice
}

export function dismissApiError(id: string): void {
  errorNotices.value = errorNotices.value.filter((notice) => notice.id !== id)
}

export function clearApiErrors(): void {
  errorNotices.value = []
}
