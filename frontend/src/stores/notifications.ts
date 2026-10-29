import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import {
  getInboxPage, getInboxSummary, markInboxAllRead, markInboxRead, syncPendingNotifications,
  type InboxNotification, type InboxAdvancedFilters, type InboxEventGroup,
} from '@/services/notificationInbox'

/** Server-backed M11 Inbox, never persisted locally. Aborts superseded sessions. */
export const useNotificationsStore = defineStore('notifications', () => {
  const unreadCount = ref(0)
  const items = ref<InboxNotification[]>([])
  const nextCursor = ref<string | null>(null)
  const previewItems = ref<InboxNotification[]>([])
  const previewBusy = ref(false)
  const previewError = ref('')
  const filter = ref<'all' | 'unread'>('all')
  const eventGroup = ref<InboxEventGroup>('all')
  const dateFrom = ref<string | null>(null)
  const dateTo = ref<string | null>(null)
  const busy = ref(false)
  const error = ref('')
  let generation = 0
  let listGeneration = 0
  let previewGeneration = 0
  let controller: AbortController | null = null
  let previewController: AbortController | null = null
  let summaryPending: Promise<void> | null = null
  let summarySource: AbortController | null = null
  let syncPending: Promise<void> | null = null
  let lastSyncAt = 0

  function clearSession(): void {
    ++generation
    ++listGeneration
    ++previewGeneration
    controller?.abort()
    previewController?.abort()
    summarySource?.abort()
    summarySource = null
    controller = null
    previewController = null
    summaryPending = null
    syncPending = null
    lastSyncAt = 0
    items.value = []
    previewItems.value = []
    previewBusy.value = false
    previewError.value = ''
    nextCursor.value = null
    unreadCount.value = 0
    filter.value = 'all'
    eventGroup.value = 'all'
    dateFrom.value = null
    dateTo.value = null
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

  /** F5/route/focus: bounded POST for pending events, THEN read-only summary.
   * Failure of the optional sync never prevents regular Inbox refresh.
   * Session fencing prevents late responses leaking between signed-in Users.
   */
  function syncAndRefresh(): Promise<void> {
    if (syncPending) return syncPending
    const ctx = context()
    if (!ctx) return Promise.resolve()
    const now = Date.now()
    if (now - lastSyncAt < 3000) return refreshSummary()
    lastSyncAt = now
    const pending = (async () => {
      try {
        await syncPendingNotifications()
      } catch (e) {
        if (current(ctx) && nonAbort(e)) error.value = 'sync'
      }
      if (current(ctx)) await refreshSummary()
    })()
    syncPending = pending
    void pending.finally(() => { if (syncPending === pending) syncPending = null })
    return pending
  }

  async function loadPage(
    nextFilter: 'all' | 'unread' = filter.value,
    append = false,
    selection?: InboxAdvancedFilters,
  ): Promise<void> {
    const ctx = context()
    if (!ctx) return
    const requested = selection ?? { eventGroup: eventGroup.value, from: dateFrom.value, to: dateTo.value }
    const unchanged = nextFilter === filter.value && requested.eventGroup === eventGroup.value
      && requested.from === dateFrom.value && requested.to === dateTo.value
    const cursor = append && unchanged ? nextCursor.value : null
    if (append && (cursor === null || !unchanged)) return
    controller?.abort()
    const source = new AbortController()
    controller = source
    const request = ++listGeneration
    filter.value = nextFilter
    eventGroup.value = requested.eventGroup
    dateFrom.value = requested.from
    dateTo.value = requested.to
    if (!append) nextCursor.value = null
    busy.value = true
    error.value = ''
    try {
      const page = await getInboxPage(nextFilter, cursor, source.signal, 20, requested)
      if (!current(ctx) || listGeneration !== request) return
      if (append) {
        // Keyset pages must not duplicate visible rows if an Inbox action races with loading.
        const existing = new Set(items.value.map(row => row.id))
        items.value = [...items.value, ...page.items.filter(row => !existing.has(row.id))]
      } else {
        items.value = page.items
      }
      nextCursor.value = page.next_cursor
    } catch (e) {
      if (current(ctx) && listGeneration === request && nonAbort(e)) error.value = 'list'
    } finally {
      if (current(ctx) && listGeneration === request) busy.value = false
      if (controller === source) controller = null
    }
  }

  /** Independent five-item preview: opening the bell must not reset Inbox filters/cursor. */
  async function loadPreview(): Promise<void> {
    const ctx = context()
    if (!ctx) return
    previewController?.abort()
    const source = new AbortController()
    const request = ++previewGeneration
    previewController = source
    previewBusy.value = true
    previewError.value = ''
    try {
      const page = await getInboxPage('all', null, source.signal, 5)
      if (!current(ctx) || previewGeneration !== request) return
      previewItems.value = page.items
    } catch (e) {
      if (current(ctx) && previewGeneration === request && nonAbort(e)) previewError.value = 'preview'
    } finally {
      if (current(ctx) && previewGeneration === request) previewBusy.value = false
      if (previewController === source) previewController = null
    }
  }

  async function markRead(id: string): Promise<void> {
    const ctx = context()
    if (!ctx) return
    await markInboxRead(id)
    if (!current(ctx)) return
    const item = items.value.find(row => row.id === id)
    const preview = previewItems.value.find(row => row.id === id)
    if ((item && item.read_at === null) || (preview && preview.read_at === null)) {
      const readAt = new Date().toISOString()
      if (item) item.read_at = readAt
      if (preview) preview.read_at = readAt
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
    const refreshes = [loadPage(filter.value), refreshSummary()]
    if (previewItems.value.length > 0) refreshes.push(loadPreview())
    await Promise.all(refreshes)
  }
  return { unreadCount, items, nextCursor, filter, eventGroup, dateFrom, dateTo, busy, error,
    previewItems, previewBusy, previewError, loadPreview,
    clearSession, refreshSummary, syncAndRefresh, loadPage, markRead, markAllRead }
})
