import { beforeEach, describe, expect, it, vi } from 'vitest'

const http = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/api', () => ({ apiClient: http }))

import { getMyWorkflowPage } from '@/services/myWorkflow'

beforeEach(() => {
  http.get.mockReset()
  http.get.mockResolvedValue({ data: { data: { items: [], next_cursor: null, execution_enabled: true } } })
})

describe('NFX-B read-only Workflow action transport', () => {
  it('sends only a fixed resource filter and bounded page size, never an actor or action', async () => {
    const signal = new AbortController().signal
    await getMyWorkflowPage('product', null, signal)
    expect(http.get).toHaveBeenCalledWith('/api/v1/my-work', {
      params: { resource_type: 'product', page_size: '10' }, signal,
    })
  })

  it('passes opaque keyset cursor only for the next read page', async () => {
    await getMyWorkflowPage('province', 'SIGNED_CURSOR')
    expect(http.get).toHaveBeenCalledWith('/api/v1/my-work', {
      params: { resource_type: 'province', page_size: '10', cursor: 'SIGNED_CURSOR' }, signal: undefined,
    })
  })
})
