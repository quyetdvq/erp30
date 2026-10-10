import axios from 'axios'
import { beforeEach, describe, expect, it } from 'vitest'

import {
  clearApiErrors,
  normalizeApiError,
  reportApiError,
  errorNotices,
} from '@/services/errorBoundary'

describe('API error boundary', () => {
  beforeEach(() => clearApiErrors())

  it('preserves status, stable code, and request correlation id', () => {
    const error = new axios.AxiosError('Conflict')
    error.response = {
      status: 409,
      statusText: 'Conflict',
      headers: { 'x-request-id': 'req-123' },
      config: error.config as never,
      data: { error: { code: 'RESOURCE_CONFLICT', message: 'Conflict details' } },
    }

    expect(normalizeApiError(error)).toMatchObject({
      status: 409,
      code: 'RESOURCE_CONFLICT',
      message: 'Conflict details',
      requestId: 'req-123',
    })
    reportApiError(error)
    reportApiError(error)
    expect(errorNotices.value).toHaveLength(1)
  })

  it('reads canonical meta.request_id and deduplicates a reported Axios error object', () => {
    const err = new axios.AxiosError('Forbidden')
    err.response = {
      status: 403,
      statusText: 'Forbidden',
      headers: {},
      config: err.config as never,
      data: { error: { code: 'DENIED' }, meta: { request_id: 'req-meta' } },
    }
    const first = reportApiError(err)
    const second = reportApiError(err)
    expect(first).toBe(second)
    expect(first?.requestId).toBe('req-meta')
    expect(errorNotices.value).toHaveLength(1)
  })

  it('preserves the Policy scope conflict code for FE-030 draft handling', () => {
    const error = new axios.AxiosError('Policy scope conflict')
    error.response = {
      status: 409,
      statusText: 'Conflict',
      headers: {},
      config: error.config as never,
      data: { error: { code: 'POLICY_SCOPE_ALREADY_EXISTS', details: [{ field: 'scope' }] } },
    }

    expect(normalizeApiError(error)).toMatchObject({
      status: 409,
      code: 'POLICY_SCOPE_ALREADY_EXISTS',
    })
  })

  it.each([401, 403, 404, 409, 422] as const)(
    'normalizes protected API status %s without dropping the stable code',
    (status) => {
      const error = new axios.AxiosError(`HTTP ${status}`)
      error.response = {
        status,
        statusText: 'API error',
        headers: {},
        config: error.config as never,
        data: { code: `ERR_${status}`, message: 'localized detail' },
      }

      expect(normalizeApiError(error)).toMatchObject({
        status,
        code: `ERR_${status}`,
        message: 'localized detail',
      })
    },
  )
})
