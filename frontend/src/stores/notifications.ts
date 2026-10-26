import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import {
  getInboxPage, getInboxSummary, markInboxAllRead, markInboxRead,
  type InboxNotification,
} from '@/services/notificationInbox'

/** Server-backed M11 Inbox, never persisted locally. Aborts superseded sessions. */
export const useNotificationsStore = defineStore('notifications', () => {
  const unreadCount = ref(0)
  const items = ref<InboxNotification[]>([])
  const nextCursor = ref<string | null>(null)
  const filter = ref<'all' | 'unread'>('all')
  const busy = ref(false)
  const error = ref('')
  let generation = 0
  let controller: AbortController | null = null
  let summaryPending: Promise<void> | null = null
  let summarySource: AbortController | null = null

  function clearSession(): void {
    ++generation
    controller?.abort()
    summarySource?.abort()
    summarySource = null
    controller = null
    summaryPending = null
    items.value = []
    nextCursor.value = null
    unreadCount.value = 0
    filter.value = 'all'
    error.value = ''
    busy.value = false
  }

  function context(): { generation: number; session: number; userId: string } | null {
    const auth = useAuthStore()
    if (!auth.user) return null
    return { generation, session: auth.getSessionGeneration(), userId: String(auth.user.id) }
  }
  function current(ctx: { generation: number; session: number; userId: string }): boolean {
    const auth = useAuthStore()
    return generation === ctx.generation && auth.getSessionGeneration() === ctx.session && String(auth.user?.id) === ctx.userId
  }
  function nonAbort(errorValue: unknown): boolean {
    return !axios.isCancel(errorValue) && !(errorValue instanceof DOMException && errorValue.name === 'AbortError')
  }

  function refreshSummary(): Promise<void> {
    if (summaryPending) return summaryPending
    const ctx = context()
    if (!ctx) return Promise.resolve()
    const source = new AbortController()
    summarySource = source
    const pending = (async () => {
      try {
        const count = await getInboxSummary(source.signal)
        if (current(ctx)) unreadCount.value = count
      } catch (e) {
        if (current(ctx) && nonAbort(e)) error.value = 'summary'
      }
    })()
    summaryPending = pending
    void pending.finally(() => {
      if (summaryPending === pending) summaryPending = null
      if (summarySource === source) summarySource = null
    })
    return pending
  }

  async function loadPage(nextFilter: 'all' | 'unread' = filter.value, append = false): Promise<void> {
    const ctx = context()
    if (!ctx) return
    const cursor = append && nextFilter === filter.value ? nextCursor.value : null
    if (append && cursor === null) return
    controller?.abort()
    const source = new AbortController()
    controller = source
    const request = ++generation
    const updated = { ...ctx, generation: request }
    filter.value = nextFilter
    busy.value = true
    error.value = ''
    try {
      const page = await getInboxPage(nextFilter, cursor, source.signal)
      if (!current(updated)) return
      items.value = append ? [...items.value, ...page.items] : page.items
      nextCursor.value = page.next_cursor
    } catch (e) {
      if (current(updated) && nonAbort(e)) error.value = 'list'
    } finally {
      if (current(updated)) busy.value = false
      if (controller === source) controller = null
    }
  }

  async function markRead(id: string): Promise<void> {
    const ctx = context()
    if (!ctx) return
    await markInboxRead(id)
    if (!current(ctx)) return
    const item = items.value.find(row => row.id === id)
    if (item && item.read_at === null) {
      item.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      if (filter.value === 'unread') items.value = items.value.filter(row => row.id !== id)
    }
    await refreshSummary()
  }
  async function markAllRead(): Promise<void> {
    const ctx = context()
    if (!ctx) return
    await markInboxAllRead()
    if (!current(ctx)) return
    await Promise.all([loadPage(filter.value), refreshSummary()])
  }
  return { unreadCount, items, nextCursor, filter, busy, error,
    clearSession, refreshSummary, loadPage, markRead, markAllRead }
})
