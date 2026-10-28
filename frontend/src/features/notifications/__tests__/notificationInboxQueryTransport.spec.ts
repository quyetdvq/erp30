import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getInboxPage } from '@/services/notificationInbox'
import { apiClient } from '@/services/api'

vi.mock('@/services/api', () => ({ apiClient: { get: vi.fn<(...args: unknown[]) => Promise<unknown>>() } }))
beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(apiClient.get).mockResolvedValue({ data: { data: { items: [], next_cursor: null } } } as never)
})

describe('NFX-A2 Inbox query transport compatibility', () => {
  it('retains legacy query shape for the unfiltered page and bell preview', async () => {
    await getInboxPage('all', null, undefined, 5)
    expect(vi.mocked(apiClient.get)).toHaveBeenCalledWith('/api/v1/notifications', {
      params: { filter: 'all', page_size: '5' }, signal: undefined,
    })
    await getInboxPage('unread', 'legacy-token')
    expect(vi.mocked(apiClient.get)).toHaveBeenLastCalledWith('/api/v1/notifications', {
      params: { filter: 'unread', page_size: '20', cursor: 'legacy-token' }, signal: undefined,
    })
  })

  it('passes canonical group and UTC range without raw SQL or free-form search', async () => {
    await getInboxPage('unread', 'v2-token', undefined, 20,
      { eventGroup: 'workflow', from: '2026-10-01', to: '2026-10-27' })
    expect(vi.mocked(apiClient.get)).toHaveBeenCalledWith('/api/v1/notifications', {
      params: {
        filter: 'unread', page_size: '20', cursor: 'v2-token',
        event_group: 'workflow', from: '2026-10-01', to: '2026-10-27',
      }, signal: undefined,
    })
  })
})
