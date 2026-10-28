import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '@/stores/notifications'
import { getInboxPage, getInboxSummary, type InboxNotification, type InboxPage } from '@/services/notificationInbox'

const session = vi.hoisted(() => ({ userId: '1', generation: 1 }))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: session.userId ? { id: session.userId } : null, getSessionGeneration: () => session.generation }),
}))
vi.mock('@/services/notificationInbox', () => ({
  getInboxPage: vi.fn<(...args: unknown[]) => Promise<unknown>>(), getInboxSummary: vi.fn<(...args: unknown[]) => Promise<unknown>>(), markInboxRead: vi.fn<(...args: unknown[]) => Promise<unknown>>(), markInboxAllRead: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))
function item(id: string): InboxNotification {
  return {
    id, type: 'workflow.action_required', priority: 'NORMAL',
    title_key: 'notifications.workflow.actionRequired.title',
    body_key: 'notifications.workflow.actionRequired.body',
    params: { resource_type: 'product', state_code: 'REVIEW' },
    target: { type: 'workflow_target', resource_type: 'product', id },
    read_at: null, created_at: '2026-10-26T08:00:00Z',
  }
}

beforeEach(() => {
  vi.resetAllMocks()
  session.userId = '1'; session.generation = 1
  setActivePinia(createPinia())
  vi.mocked(getInboxSummary).mockResolvedValue(3)
})

describe('NFX-A2 Inbox filters and keyset isolation', () => {
  it('uses a filter-scoped cursor when loading more and resets cursor on changed group or date', async () => {
    vi.mocked(getInboxPage)
      .mockResolvedValueOnce({ items: [item('5')], next_cursor: 'v2page' })
      .mockResolvedValueOnce({ items: [item('4')], next_cursor: null })
      .mockResolvedValueOnce({ items: [], next_cursor: null })
    const store = useNotificationsStore()
    const advanced = { eventGroup: 'workflow' as const, from: '2026-10-01', to: '2026-10-27' }
    await store.loadPage('unread', false, advanced)
    expect(store.nextCursor).toBe('v2page')
    await store.loadPage('unread', true)
    expect(vi.mocked(getInboxPage)).toHaveBeenNthCalledWith(2, 'unread', 'v2page', expect.any(AbortSignal), 20, advanced)
    expect(store.items.map(row => row.id)).toEqual(['5', '4'])
    await store.loadPage('unread', false, { ...advanced, eventGroup: 'import' })
    expect(vi.mocked(getInboxPage)).toHaveBeenNthCalledWith(3, 'unread', null, expect.any(AbortSignal), 20,
      { ...advanced, eventGroup: 'import' })
    expect(store.nextCursor).toBeNull()
  })

  it('keeps the bell preview on unfiltered legacy requests without altering Inbox filters', async () => {
    vi.mocked(getInboxPage).mockImplementation(async (_filter, _cursor, _signal, size) =>
      size === 5 ? { items: [item('10')], next_cursor: null } : { items: [item('9')], next_cursor: 'cursor2' })
    const store = useNotificationsStore()
    await store.loadPage('all', false, { eventGroup: 'workflow', from: null, to: null })
    await store.loadPreview()
    expect(vi.mocked(getInboxPage)).toHaveBeenNthCalledWith(2, 'all', null, expect.any(AbortSignal), 5)
    expect(store.eventGroup).toBe('workflow')
    expect(store.items[0]?.id).toBe('9')
    expect(store.nextCursor).toBe('cursor2')
  })

  it('rejects stale responses after rapid filter changes', async () => {
    let release!: (page: InboxPage) => void
    vi.mocked(getInboxPage)
      .mockImplementationOnce(() => new Promise<InboxPage>(resolve => { release = resolve }))
      .mockResolvedValueOnce({ items: [item('3')], next_cursor: null })
    const store = useNotificationsStore()
    const pending = store.loadPage('all', false, { eventGroup: 'import', from: null, to: null })
    await store.loadPage('all', false, { eventGroup: 'workflow', from: null, to: null })
    release({ items: [item('100')], next_cursor: 'oldcursor' })
    await pending
    expect(store.items.map(row => row.id)).toEqual(['3'])
    expect(store.eventGroup).toBe('workflow')
    expect(store.nextCursor).toBeNull()
  })

  it('clears advanced filters and in-flight results on logout/account change', async () => {
    let release!: (page: InboxPage) => void
    vi.mocked(getInboxPage).mockImplementationOnce(() => new Promise<InboxPage>(resolve => { release = resolve }))
    const store = useNotificationsStore()
    const pending = store.loadPage('unread', false, { eventGroup: 'workflow', from: '2026-10-01', to: '2026-10-27' })
    store.clearSession()
    session.userId = '2'; session.generation = 2
    release({ items: [item('99')], next_cursor: null })
    await pending
    expect(store.items).toEqual([])
    expect(store.eventGroup).toBe('all')
    expect(store.dateFrom).toBeNull()
    expect(store.dateTo).toBeNull()
  })

  it('removes duplicated rows returned by a racing keyset append', async () => {
    vi.mocked(getInboxPage)
      .mockResolvedValueOnce({ items: [item('4')], next_cursor: 'second' })
      .mockResolvedValueOnce({ items: [item('4'), item('3')], next_cursor: null })
    const store = useNotificationsStore()
    await store.loadPage('all')
    await store.loadPage('all', true)
    expect(store.items.map(row => row.id)).toEqual(['4', '3'])
  })
})
