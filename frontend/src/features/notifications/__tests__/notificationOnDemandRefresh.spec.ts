import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '@/stores/notifications'
import { getInboxSummary, syncPendingNotifications } from '@/services/notificationInbox'

const ctx = vi.hoisted(() => ({ id: '4', session: 1 }))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: ctx.id ? { id: ctx.id } : null, getSessionGeneration: () => ctx.session }),
}))
vi.mock('@/services/notificationInbox', () => ({
  getInboxSummary: vi.fn<() => Promise<number>>(),
  syncPendingNotifications: vi.fn<() => Promise<{ status: string; processed: number }>>(),
  getInboxPage: vi.fn<() => Promise<unknown>>(),
  markInboxRead: vi.fn<() => Promise<void>>(),
  markInboxAllRead: vi.fn<() => Promise<void>>(),
}))

beforeEach(() => {
  vi.resetAllMocks()
  setActivePinia(createPinia())
  ctx.id = '4'
  ctx.session = 1
  vi.mocked(getInboxSummary).mockResolvedValue(2)
  vi.mocked(syncPendingNotifications).mockResolvedValue({ status: 'CHECKED', processed: 1 })
})

describe('M11 on-demand F5/navigation sync', () => {
  it('materializes pending events before reading the unread count', async () => {
    const timeline: string[] = []
    vi.mocked(syncPendingNotifications).mockImplementation(async () => { timeline.push('sync'); return { status: 'CHECKED', processed: 1 } })
    vi.mocked(getInboxSummary).mockImplementation(async () => { timeline.push('summary'); return 3 })
    const inbox = useNotificationsStore()
    await inbox.syncAndRefresh()
    expect(timeline).toEqual(['sync', 'summary'])
    expect(inbox.unreadCount).toBe(3)
  })

  it('does not overlap materialization and throttles navigation bursts', async () => {
    let release!: () => void
    vi.mocked(syncPendingNotifications).mockImplementationOnce(() => new Promise(resolve => {
      release = () => resolve({ status: 'CHECKED', processed: 0 })
    }))
    const inbox = useNotificationsStore()
    const first = inbox.syncAndRefresh()
    const second = inbox.syncAndRefresh()
    expect(syncPendingNotifications).toHaveBeenCalledTimes(1)
    release()
    await Promise.all([first, second])
    await inbox.syncAndRefresh()
    expect(syncPendingNotifications).toHaveBeenCalledTimes(1)
    expect(getInboxSummary).toHaveBeenCalled()
  })

  it('still refreshes the Inbox if optional on-demand dispatch fails', async () => {
    vi.mocked(syncPendingNotifications).mockRejectedValueOnce(new Error('TEMP_UNAVAILABLE'))
    const inbox = useNotificationsStore()
    await inbox.syncAndRefresh()
    expect(inbox.unreadCount).toBe(2)
    expect(inbox.error).toBe('sync')
  })

  it('discards old account responses and restarts after a session switch', async () => {
    let release!: () => void
    vi.mocked(syncPendingNotifications).mockImplementationOnce(() => new Promise(resolve => {
      release = () => resolve({ status: 'CHECKED', processed: 1 })
    }))
    const inbox = useNotificationsStore()
    const old = inbox.syncAndRefresh()
    inbox.clearSession()
    ctx.id = '5'
    ctx.session = 2
    release()
    await old
    expect(inbox.unreadCount).toBe(0)
    await inbox.syncAndRefresh()
    expect(syncPendingNotifications).toHaveBeenCalledTimes(2)
    expect(inbox.unreadCount).toBe(2)
  })
})
