import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import HistoryMonitorView from '@/features/history/pages/HistoryMonitorView.vue'
import type { BusinessHistoryEvent } from '@/services/history'

const state = vi.hoisted(() => ({
  allowed: true,
  customerVisible: false,
  supplierVisible: false,
  monitor: vi.fn<typeof import('@/services/history').monitorBusinessHistory>(),
}))
vi.mock('@/services/history', () => ({ monitorBusinessHistory: state.monitor }))
// Assert that the monitor mounts the SAME CustomerDetailView via an explicit ID.
// Stub the canonical modal's service internals, not an invented audit modal.
vi.mock('@/features/customer/pages/CustomerDetailView.vue', async () => {
  const { defineComponent, h } = await import('vue')
  return { default: defineComponent({
    name: 'CustomerDetailView',
    props: { customerId: { type: String, required: false } },
    emits: ['close', 'saved'],
    setup(props, { emit }) {
      return () => h('section', { 'data-testid': 'canonical-customer-modal', role: 'dialog' }, [
        h('span', { 'data-testid': 'canonical-customer-id' }, props.customerId || ''),
        h('button', { type: 'button', onClick: () => emit('close') }, 'Close customer'),
      ])
    },
  }) }
})
vi.mock('@/features/supplier/pages/SupplierDetailView.vue', async () => {
  const { defineComponent, h } = await import('vue')
  return { default: defineComponent({
    name: 'SupplierDetailView',
    props: { supplierId: { type: String, required: false } },
    emits: ['close', 'saved'],
    setup(props, { emit }) {
      return () => h('section', { 'data-testid': 'canonical-supplier-modal', role: 'dialog' }, [
        h('span', { 'data-testid': 'canonical-supplier-id' }, props.supplierId || ''),
        h('button', { type: 'button', onClick: () => emit('close') }, 'Close supplier'),
      ])
    },
  }) }
})
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({
  user: { id: '1' }, getSessionGeneration: () => 1,
}) }))
vi.mock('@/composables/useBasePermission', () => ({ useBasePermission: () => ({
  snapshotState: { value: 'ready' },
  hasBasePermission: (resource: string, action: string) =>
    state.allowed && ((resource === 'business_history_monitor' && action === 'view') ||
      (state.customerVisible && resource === 'customer' && action === 'view') ||
      (state.supplierVisible && resource === 'supplier' && action === 'view')),
  hasBasePermissionKey: (key: string) => state.allowed &&
    (key === 'business_history_monitor.view' || (state.customerVisible && key === 'customer.view') ||
      (state.supplierVisible && key === 'supplier.view')),
}) }))
vi.mock('@/composables/useFormatters', () => ({ useFormatters: () => ({
  formatDateTime: (_raw: string) => '29/10/2026 16:52',
}) }))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: vi.fn<typeof import('@/services/errorBoundary').reportApiError>() }))

const event: BusinessHistoryEvent = {
  id: '70000000000001', resource_type: 'customer', resource_id: '42', event_type: 'UPDATE',
  action_code: 'edit', status_before: 'ACTIVE', status_after: 'ACTIVE',
  changes: { phone: {}, customer_group_code: ['STANDARD', 'VIP1'] },
  resource_code: 'CUS-0042', resource_name: 'Công ty ABC',
  checkpoint: null, actor_user_id: '1', actor_type: 'USER',
  actor_username: 'thangdv', actor_full_name: 'Nguyễn Văn A',
  request_id: 'req-1', created_at: '2026-10-29T09:52:19Z',
}

const cleanups: Array<() => void> = []
function view() {
  // Focus restoration requires a connected trigger. Mount in the real jsdom
  // document, matching the browser and the shared TableDetailModal tests.
  const host = document.createElement('div')
  document.body.appendChild(host)
  const wrapper = mount(HistoryMonitorView, {
    attachTo: host,
    global: { plugins: [i18n], stubs: { NIcon: true, EyeOutline: true, Refresh: true } },
  })
  cleanups.push(() => { wrapper.unmount(); host.remove() })
  return wrapper
}
function documentView(): DOMWrapper<HTMLElement> { return new DOMWrapper(document.body) }

beforeEach(() => {
  state.allowed = true
  state.customerVisible = false
  state.supplierVisible = false
  state.monitor.mockReset()
  state.monitor.mockResolvedValue({ data: [event], meta: { page: 1, page_size: 25, total: 1 } })
})
afterEach(() => { cleanups.splice(0).forEach(cleanup => cleanup()) })

describe('HIS-02.1 System History Monitor standard detail UI', () => {
  it('shows the standard VIEW eye action and teleports the shared modal to body', async () => {
    const wrapper = view()
    await flushPromises()
    const eye = wrapper.get('.table-row-actions button[aria-label]')
    expect(eye.attributes('aria-label')).toBe(i18n.global.t('history.monitorDetails'))
    // A real keyboard/pointer activation focuses the connected action first.
    // Vue Test Utils trigger('click') alone does not provide that browser step.
    ;(eye.element as HTMLButtonElement).focus()
    expect(document.activeElement).toBe(eye.element)
    await eye.trigger('click')
    await flushPromises()
    const dialog = documentView().get('[role="dialog"][aria-modal="true"]')
    expect(dialog.element.closest('.modal-backdrop')?.parentElement).toBe(document.body)
    expect(wrapper.find('.history-monitor-details').exists()).toBe(false)
    expect(dialog.text()).toContain('thangdv (Nguyễn Văn A)')
    expect(dialog.text()).toContain('29/10/2026 16:52')
    expect(dialog.text()).toContain('phone:')
    expect(dialog.text()).toContain('Đã thay đổi')
    expect(dialog.text()).not.toContain('0912345678')
    await dialog.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(documentView().find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(eye.element)
  })

  it('keeps the modal open and fails closed if an old History payload reaches the frontend', async () => {
    state.monitor.mockResolvedValue({
      data: [{ ...event, changes: { phone: { changed: true }, address: { before: 'private', after: 'private' } } as never }],
      meta: { page: 1, page_size: 25, total: 1 },
    })
    const wrapper = view()
    await flushPromises()
    await wrapper.get('.table-row-actions button[aria-label]').trigger('click')
    await flushPromises()
    const dialog = documentView().get('[role="dialog"][aria-modal="true"]')
    expect(dialog.text()).toContain(i18n.global.t('history.invalidChangesFormat'))
    expect(dialog.text()).not.toContain('private')
    expect(dialog.text()).toContain('thangdv (Nguyễn Văn A)')
    expect(wrapper.text()).toContain('CUS-0042')
    await dialog.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(documentView().find('[role="dialog"]').exists()).toBe(false)
  })

  it('closes the modal when the user reloads the monitor page', async () => {
    const wrapper = view()
    await flushPromises()
    await wrapper.get('.table-row-actions button[aria-label]').trigger('click')
    await flushPromises()
    expect(documentView().find('[role="dialog"]').exists()).toBe(true)
    // Submit the form, not just the button: VTU's synthetic click does not
    // guarantee the native submit default action in jsdom.
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(state.monitor).toHaveBeenCalledTimes(2)
    expect(documentView().find('[role="dialog"]').exists()).toBe(false)
  })

  it('restores focus to the view icon when the standard close button is clicked', async () => {
    const wrapper = view()
    await flushPromises()
    const eye = wrapper.get('.table-row-actions button[aria-label]')
    ;(eye.element as HTMLButtonElement).focus()
    await eye.trigger('click')
    await flushPromises()
    await documentView().get('.modal-card-button-close').trigger('click')
    await flushPromises()
    expect(documentView().find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(eye.element)
  })

  it('provides Customer code/name from the monitor page but keeps the profile icon permission-gated', async () => {
    const wrapper = view()
    await flushPromises()
    expect(wrapper.text()).toContain('CUS-0042')
    expect(wrapper.text()).toContain('Công ty ABC')
    expect(wrapper.findAll('.table-row-actions button[aria-label]')).toHaveLength(1)
  })

  it('opens the existing Customer detail component with the persisted ID, without inventing a history detail modal', async () => {
    state.customerVisible = true
    const wrapper = view()
    await flushPromises()
    const actions = wrapper.findAll('.table-row-actions button[aria-label]')
    expect(actions).toHaveLength(2)
    expect(actions[1]!.attributes('aria-label')).toBe(i18n.global.t('history.monitorOpenObject'))
    await actions[1]!.trigger('click')
    await flushPromises()
    expect(documentView().get('[data-testid="canonical-customer-id"]').text()).toBe('42')
    expect(documentView().find('.table-detail-modal').exists()).toBe(false)
    await documentView().get('[data-testid="canonical-customer-modal"] button').trigger('click')
    await flushPromises()
    expect(documentView().find('[data-testid="canonical-customer-modal"]').exists()).toBe(false)
  })

  it('does not grant Supplier profile VIEW through Customer profile permission', async () => {
    state.customerVisible = true
    state.monitor.mockResolvedValue({ data: [{ ...event, resource_type: 'supplier',
      resource_code: 'SUP-0042', resource_name: 'Supplier Example' }],
      meta: { page: 1, page_size: 25, total: 1 } })
    const wrapper = view()
    await flushPromises()
    expect(wrapper.findAll('.table-row-actions button[aria-label]')).toHaveLength(1)
    expect(documentView().find('[data-testid="canonical-customer-modal"]').exists()).toBe(false)
  })

  it('closes the canonical Customer modal when filters refresh the page', async () => {
    state.customerVisible = true
    const wrapper = view()
    await flushPromises()
    await wrapper.get('.table-row-actions button[aria-label="' + i18n.global.t('history.monitorOpenObject') + '"]').trigger('click')
    await flushPromises()
    expect(documentView().find('[data-testid="canonical-customer-modal"]').exists()).toBe(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(documentView().find('[data-testid="canonical-customer-modal"]').exists()).toBe(false)
  })

  it('does not fetch or render details without the dedicated SYSTEM permission', async () => {
    state.allowed = false
    const wrapper = view()
    await flushPromises()
    expect(state.monitor).not.toHaveBeenCalled()
    expect(wrapper.find('.table-row-actions').exists()).toBe(false)
    expect(documentView().find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain(i18n.global.t('history.monitorRestricted'))
  })
  it('opens the canonical Supplier modal only with independent supplier.view', async () => {
    state.monitor.mockResolvedValue({ data: [{ ...event, resource_type: 'supplier',
      resource_id: '125', resource_code: 'SUP-0125', resource_name: 'Supplier ABC' }],
    meta: { page: 1, page_size: 25, total: 1 } })
    state.supplierVisible = true
    const wrapper = view()
    await flushPromises()
    const buttons = wrapper.findAll('.table-row-actions button')
    expect(buttons.length).toBeGreaterThanOrEqual(2)
    await buttons[buttons.length - 1]!.trigger('click')
    await flushPromises()
    expect(documentView().get('[data-testid="canonical-supplier-id"]').text()).toBe('125')
    expect(documentView().find('[data-testid="canonical-customer-modal"]').exists()).toBe(false)
  })

  it('does not expose Supplier open action with SYSTEM monitor permission alone', async () => {
    state.monitor.mockResolvedValue({ data: [{ ...event, resource_type: 'supplier',
      resource_id: '125', resource_code: 'SUP-0125', resource_name: 'Supplier ABC' }],
    meta: { page: 1, page_size: 25, total: 1 } })
    const wrapper = view()
    await flushPromises()
    const buttons = wrapper.findAll('.table-row-actions button')
    expect(buttons).toHaveLength(1) // event detail only, no Supplier link
  })
})
