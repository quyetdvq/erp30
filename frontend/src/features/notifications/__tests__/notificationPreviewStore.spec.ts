import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '@/stores/notifications'
import { getInboxPage, getInboxSummary, markInboxRead, markInboxAllRead, type InboxNotification, type InboxPage } from '@/services/notificationInbox'

const session = vi.hoisted(() => ({ userId: '1', generation: 1 }))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: session.userId ? { id: session.userId } : null, getSessionGeneration: () => session.generation }),
}))
vi.mock('@/services/notificationInbox', () => ({
  getInboxPage: vi.fn(), getInboxSummary: vi.fn(), markInboxRead: vi.fn(), markInboxAllRead: vi.fn(),
}))

function item(id: string): InboxNotification {
  return {
    id, type: 'import.completed', priority: 'NORMAL',
    title_key: 'notifications.import.completed.title',
    body_key: 'notifications.import.completed.body',
    params: { success_rows: 1, error_rows: 0 },
    target: { type: 'import_job', id }, read_at: null,
    created_at: '2026-10-26T08:00:00Z',
  }
}

beforeEach(() => {
  vi.resetAllMocks()
  session.userId = '1'
  session.generation = 1
  setActivePinia(createPinia())
  vi.mocked(getInboxSummary).mockResolvedValue(3)
  vi.mocked(markInboxRead).mockResolvedValue(undefined)
  vi.mocked(markInboxAllRead).mockResolvedValue(undefined)
  vi.mocked(getInboxPage).mockImplementation(async (_filter, _cursor, _signal, size) =>
    size === 5
      ? { items: [item('2')], next_cursor: null }
      : { items: [item('1')], next_cursor: 'next-page' },
  )
})

describe('M11 NFX-A1 session-isolated independent bell preview', () => {
  it('does not reset Inbox filter, pagination or current items when opening bell', async () => {
    const store = useNotificationsStore()
    await store.loadPage('unread')
    await store.loadPreview()
    expect(store.filter).toBe('unread')
    expect(store.items.map(row => row.id)).toEqual(['1'])
    expect(store.nextCursor).toBe('next-page')
    expect(store.previewItems.map(row => row.id)).toEqual(['2'])
    expect(vi.mocked(getInboxPage)).toHaveBeenCalledWith('all', null, expect.any(AbortSignal), 5)
  })

  it('updates preview on explicit mark-read and never treats navigation as read', async () => {
    const store = useNotificationsStore()
    await store.loadPreview()
    expect(store.previewItems[0]?.read_at).toBeNull()
    await store.markRead('2')
    expect(markInboxRead).toHaveBeenCalledWith('2')
    expect(store.previewItems[0]?.read_at).not.toBeNull()
  })

  it('discards a late preview response after a session is cleared', async () => {
    let resolve!: (page: { items: InboxNotification[]; next_cursor: string | null }) => void
    vi.mocked(getInboxPage).mockImplementationOnce(() => new Promise<InboxPage>(done => { resolve = done }))
    const store = useNotificationsStore()
    const pending = store.loadPreview()
    store.clearSession()
    session.userId = '2'
    session.generation = 2
    resolve({ items: [item('99')], next_cursor: null })
    await pending
    expect(store.previewItems).toEqual([])
    expect(store.previewBusy).toBe(false)
  })
})
