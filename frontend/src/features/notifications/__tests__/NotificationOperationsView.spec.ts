import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import NotificationOperationsView from '../pages/NotificationOperationsView.vue'
import type { NotificationOperationsOverview } from '@/services/notificationOperations'

const mock = vi.hoisted(() => ({ get: vi.fn(), diagnostics: vi.fn(), viewAllowed: true, userId: '7', session: 1 }))
vi.mock('@/services/notificationOperations', () => ({
  getNotificationOperationsOverview: mock.get, getNotificationOperationsDiagnostics: mock.diagnostics,
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { id: mock.userId }, getSessionGeneration: () => mock.session }),
}))
vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({ hasBasePermission: (resource: string, action: string) =>
    mock.viewAllowed && resource === 'notification_operations' && action === 'view' }),
}))
function snapshot(): NotificationOperationsOverview {
  return {
    as_of_utc: '2026-10-27T07:00:00.000000Z',
    queues: {
      outbox: { available: true, status_counts: { PROCESSED: 8, PENDING: 1 }, expired_leases: 0, overdue_ready_15m: 1 },
      email: { available: true, status_counts: { SENT: 5 }, expired_leases: 0, overdue_ready_15m: 0 },
      push: { available: true, status_counts: { CANCELLED: 1, SENT: 1 }, expired_leases: 0, overdue_ready_15m: 0 },
    },
    runtime_switches: [
      { key: 'DINKERP_M11_TASKS_INAPP_ENABLED', section: 'scheduler', enabled: true, source: 'database' },
      { key: 'DINKERP_M11_TASKS_EMAIL_ENABLED', section: 'scheduler', enabled: true, source: 'database' },
    ],
    cron: { health: 'UNKNOWN', reason: 'TELEMETRY_UNAVAILABLE', last_tick_at: null },
    telemetry_available: false,
  }
}

beforeEach(() => {
  mock.get.mockReset().mockResolvedValue(snapshot())
  mock.diagnostics.mockReset().mockRejectedValue(new Error('DIAGNOSTICS_UNAVAILABLE'))
  mock.viewAllowed = true
  mock.userId = '7'
  mock.session = 1
})

describe('E1.1 aggregate-only operations view', () => {
  it('renders all three queues and warnings but never claims Cron is healthy', async () => {
    const wrapper = mount(NotificationOperationsView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.find('[data-testid="ops-queue-outbox"]').text()).toContain('8')
    expect(wrapper.find('[data-testid="ops-queue-email"]').text()).toContain('5')
    expect(wrapper.find('[data-testid="ops-queue-push"]').text()).toContain('1')
    expect(wrapper.find('[data-testid="ops-cron"]').text()).toContain(i18n.global.t('notificationOperations.unknown'))
    expect(wrapper.find('[data-testid="ops-runtime"]').text()).toContain(i18n.global.t('notificationOperations.runtimeNote'))
    expect(wrapper.text()).not.toContain('recipient_user_id')
    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(mock.get).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('does not fetch data for an account without presentation permission', async () => {
    mock.viewAllowed = false
    const wrapper = mount(NotificationOperationsView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(mock.get).not.toHaveBeenCalled()
    expect(wrapper.get('[role="alert"]').text()).toContain(i18n.global.t('notificationOperations.notAuthorized'))
    wrapper.unmount()
  })

  it('handles API failure without rendering stale global data, and retries on demand', async () => {
    mock.get.mockRejectedValueOnce(new Error('REQUEST_FAILED')).mockResolvedValueOnce(snapshot())
    const wrapper = mount(NotificationOperationsView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.findAll('[data-testid="ops-queue-outbox"]')).toHaveLength(0)
    expect(wrapper.get('[role="alert"]').text()).toContain(i18n.global.t('notificationOperations.loadError'))
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(mock.get).toHaveBeenCalledTimes(2)
    expect(wrapper.findAll('[data-testid="ops-queue-outbox"]')).toHaveLength(1)
    wrapper.unmount()
  })

  it('shows an unavailable queue without inventing counters or reporting success', async () => {
    const result = snapshot()
    result.queues.email = { available: false, status_counts: {}, overdue_ready_15m: 0, expired_leases: 0 }
    mock.get.mockResolvedValue(result)
    const wrapper = mount(NotificationOperationsView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.get('[data-testid="ops-queue-email"]').text()).toContain(i18n.global.t('notificationOperations.unavailable'))
    expect(wrapper.get('[data-testid="ops-queue-email"]').findAll('dd')).toHaveLength(0)
    wrapper.unmount()
  })

  it('ignores an in-flight snapshot after unmount rather than exposing stale data', async () => {
    let finish!: (value: NotificationOperationsOverview) => void
    mock.get.mockReturnValue(new Promise<NotificationOperationsOverview>(resolve => { finish = resolve }))
    const wrapper = mount(NotificationOperationsView, { global: { plugins: [i18n] } })
    await flushPromises()
    wrapper.unmount()
    finish(snapshot())
    await flushPromises()
    expect(mock.get).toHaveBeenCalledTimes(1)
  })
})
