import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { i18n } from '@/i18n'
import NotificationsInboxView from '../pages/NotificationsInboxView.vue'

const mock = vi.hoisted(() => ({
  auth: { user: { id: '1' } as { id: string } | null },
  inbox: null as {
    filter: string; eventGroup: string; dateFrom: string | null; dateTo: string | null;
    items: unknown[]; unreadCount: number; nextCursor: string | null; busy: boolean; error: string;
    loadPage: ReturnType<typeof vi.fn>; markRead: ReturnType<typeof vi.fn>;
    markAllRead: ReturnType<typeof vi.fn>;
  } | null,
}))
vi.mock('@/features/notifications/featureFlags', () => ({ inboxUiEnabled: true }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => mock.auth }))
vi.mock('@/stores/notifications', () => ({ useNotificationsStore: () => mock.inbox }))

function view() {
  return mount(NotificationsInboxView, {
    global: { plugins: [i18n] },
  })
}

beforeEach(() => {
  mock.auth.user = { id: '1' }
  mock.inbox = reactive({
    filter: 'all', eventGroup: 'all', dateFrom: null, dateTo: null,
    items: [], unreadCount: 0, nextCursor: null, busy: false, error: '',
    loadPage: vi.fn().mockResolvedValue(undefined), markRead: vi.fn(), markAllRead: vi.fn(),
  })
})

describe('NFX-A2 Inbox filter controls', () => {
  it('applies event group + a complete UTC date range with a single request', async () => {
    const wrapper = view()
    await wrapper.get('[data-testid="inbox-event-group"]').setValue('workflow')
    await wrapper.get('[data-testid="inbox-from"]').setValue('2026-10-01')
    await wrapper.get('[data-testid="inbox-to"]').setValue('2026-10-27')
    await wrapper.get('[data-testid="inbox-apply"]').trigger('click')
    expect(mock.inbox?.loadPage).toHaveBeenLastCalledWith('all', false, {
      eventGroup: 'workflow', from: '2026-10-01', to: '2026-10-27',
    })
  })

  it('does not submit incomplete or oversized ranges', async () => {
    const wrapper = view()
    await wrapper.get('[data-testid="inbox-from"]').setValue('2026-10-01')
    expect(wrapper.get('[data-testid="inbox-apply"]').attributes('disabled')).toBeDefined()
    await wrapper.get('[data-testid="inbox-to"]').setValue('2027-11-01')
    expect(wrapper.get('[data-testid="inbox-apply"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[role="alert"]').text()).toContain(i18n.global.t('notificationsInbox.dateRangeError'))
  })

  it('clears applied filters on reset and displays a distinct empty-filter state', async () => {
    const wrapper = view()
    await wrapper.get('[data-testid="inbox-event-group"]').setValue('workflow')
    await wrapper.get('[data-testid="inbox-reset"]').trigger('click')
    expect(mock.inbox?.loadPage).toHaveBeenLastCalledWith('all', false, { eventGroup: 'all', from: null, to: null })
    if (!mock.inbox) throw new Error('Missing reactive Inbox test double')
    mock.inbox.eventGroup = 'workflow'
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain(i18n.global.t('notificationsInbox.noMatches'))
  })
})
