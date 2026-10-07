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
})
