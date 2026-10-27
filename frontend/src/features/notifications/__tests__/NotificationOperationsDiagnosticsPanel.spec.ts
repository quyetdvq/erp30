import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import type { NotificationOperationsDiagnostics, NotificationOperationsDiagnosticsQueue } from '@/services/notificationOperations'
import NotificationOperationsDiagnosticsPanel from '../pages/NotificationOperationsDiagnosticsPanel.vue'

const mocks = vi.hoisted(() => ({ fetch: vi.fn(), userId: '7', session: 1 }))
vi.mock('@/services/notificationOperations', () => ({ getNotificationOperationsDiagnostics: mocks.fetch }))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: { id: mocks.userId }, getSessionGeneration: () => mocks.session }),
}))
function queue(): NotificationOperationsDiagnosticsQueue {
  return {
    available: true, ready: 3, retry_ready: 1, retry_delayed: 1, dead: 1,
    expired_leases: 1, overdue_ready_15m: 1, overdue_ready_60m: 1,
    oldest_ready_minutes: 91,
    error_categories: { TRANSPORT: 1, OTHER: 1, UNSPECIFIED: 0 },
    findings: ['DEAD_PRESENT', 'EXPIRED_LEASE', 'OVERDUE_60M', 'OVERDUE_15M', 'RETRY_READY', 'RETRY_DELAYED'],
  }
}
function result(): NotificationOperationsDiagnostics {
  return {
    as_of_utc: '2026-10-27T10:00:00.000000Z',
    queues: { outbox: queue(), email: queue(), push: queue() },
    cron: { health: 'UNKNOWN', reason: 'TELEMETRY_UNAVAILABLE' },
  }
}
beforeEach(() => {
  mocks.fetch.mockReset().mockResolvedValue(result())
  mocks.userId = '7'
  mocks.session = 1
})
describe('E1.2B read-only Operations diagnostics panel', () => {
  it('renders safe queue metrics and normalized reasons, no raw receipt data or controls', async () => {
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.get('[data-testid="ops-diagnostic-outbox"]').text()).toContain('91')
    expect(wrapper.get('[data-testid="ops-diagnostic-email"]').text()).toContain('3')
    expect(wrapper.get('[data-testid="ops-diagnostic-push"]').text()).toContain('1')
    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('recipient_user_id')
    expect(wrapper.text()).not.toContain('last_error_code')
    wrapper.unmount()
  })

  it('keeps detail diagnostic read failures isolated to the panel', async () => {
    mocks.fetch.mockRejectedValue(new Error('UNKNOWN_HTTP_ERROR'))
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.get('[data-testid="ops-diagnostics-error"]').text()).toContain(
      i18n.global.t('notificationOperations.diagnostics.loadError'))
    expect(wrapper.findAll('[data-testid="ops-diagnostic-outbox"]')).toHaveLength(0)
    wrapper.unmount()
  })

  it('does not render sensitive server exception text in an error state', async () => {
    mocks.fetch.mockRejectedValue(new Error('SMTP_PASSWORD=super-secret recipient=private@example.test'))
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.get('[data-testid="ops-diagnostics-error"]').text()).toContain(
      i18n.global.t('notificationOperations.diagnostics.loadError'))
    expect(wrapper.text()).not.toContain('super-secret')
    expect(wrapper.text()).not.toContain('private@example.test')
    wrapper.unmount()
  })

  it('discards an in-flight response after the session generation changes', async () => {
    let resolve!: (data: NotificationOperationsDiagnostics) => void
    mocks.fetch.mockReturnValue(new Promise<NotificationOperationsDiagnostics>(done => { resolve = done }))
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    mocks.session += 1
    resolve(result())
    await flushPromises()
    expect(wrapper.findAll('[data-testid="ops-diagnostic-outbox"]')).toHaveLength(0)
    wrapper.unmount()
  })

  it('handles an unavailable queue without pretending it has counters', async () => {
    const data = result()
    data.queues.email = { ...queue(), available: false, findings: ['QUEUE_UNAVAILABLE'] }
    mocks.fetch.mockResolvedValue(data)
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.get('[data-testid="ops-diagnostic-email"]').text()).toContain(
      i18n.global.t('notificationOperations.unavailable'))
    expect(wrapper.get('[data-testid="ops-diagnostic-email"]').findAll('dd')).toHaveLength(0)
    wrapper.unmount()
  })

  it('does not claim healthy Cron when no queue warning is observed', async () => {
    const data = result()
    data.queues.outbox = {
      ...queue(), ready: 0, dead: 0, retry_ready: 0, retry_delayed: 0,
      overdue_ready_15m: 0, overdue_ready_60m: 0, expired_leases: 0,
      oldest_ready_minutes: null, findings: [], error_categories: {},
    }
    mocks.fetch.mockResolvedValue(data)
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.get('[data-testid="ops-diagnostic-outbox"]').text()).toContain(
      i18n.global.t('notificationOperations.diagnostics.noSignals'))
    expect(wrapper.text()).toContain(i18n.global.t('notificationOperations.diagnostics.cronDisclaimer'))
    wrapper.unmount()
  })

  it('discards any in-flight data after component unmount', async () => {
    let resolve!: (data: NotificationOperationsDiagnostics) => void
    mocks.fetch.mockReturnValue(new Promise<NotificationOperationsDiagnostics>(done => { resolve = done }))
    const wrapper = mount(NotificationOperationsDiagnosticsPanel, { global: { plugins: [i18n] } })
    await flushPromises()
    wrapper.unmount()
    resolve(result())
    await flushPromises()
    expect(mocks.fetch).toHaveBeenCalledTimes(1)
  })
})
