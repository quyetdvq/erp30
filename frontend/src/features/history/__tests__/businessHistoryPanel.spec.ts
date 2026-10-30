import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import BusinessHistoryPanel from '@/features/history/components/BusinessHistoryPanel.vue'
import type { BusinessHistoryEvent } from '@/services/history'

const state = vi.hoisted(() => ({
  history: vi.fn<typeof import('@/services/history').customerHistory>(),
  supplier: vi.fn<typeof import('@/services/history').supplierHistory>(),
}))
vi.mock('@/services/history', () => ({ customerHistory: state.history, supplierHistory: state.supplier }))
vi.mock('@/services/errorBoundary', () => ({ reportApiError: vi.fn<typeof import('@/services/errorBoundary').reportApiError>() }))
vi.mock('@/composables/useFormatters', () => ({ useFormatters: () => ({
  formatDateTime: (_value: string) => '29/10/2026 18:11',
}) }))

const base: BusinessHistoryEvent = {
  id: '1', resource_type: 'customer', resource_id: '42', event_type: 'UPDATE',
  action_code: 'edit', status_before: 'ACTIVE', status_after: 'ACTIVE',
  changes: { phone: {}, customer_group_code: ['STANDARD', 'VIP1'] }, checkpoint: null,
  actor_user_id: '1', actor_type: 'USER', actor_username: 'thangdv',
  actor_full_name: 'Nguyễn Văn A', request_id: 'req-1', created_at: '2026-10-29T11:11:00Z',
}
function mountPanel() {
  return mount(BusinessHistoryPanel, {
    props: { customerId: '42' },
    global: { plugins: [i18n], stubs: { ListPagination: true } },
  })
}
beforeEach(() => { state.history.mockReset(); state.supplier.mockReset() })

describe('HIS-03.1 Customer embedded History render protection', () => {
  it('shows valid compact events and authorized presentation', async () => {
    state.history.mockResolvedValue({ data: [base], meta: { page: 1, page_size: 25, total: 1 } })
    const wrapper = mountPanel()
    await flushPromises()
    expect(wrapper.get('.business-history__header').text()).toContain(i18n.global.t('history.title'))
    expect(wrapper.text()).toContain('thangdv (Nguyễn Văn A)')
    expect(wrapper.text()).toContain('customer_group_code: STANDARD → VIP1')
    expect(wrapper.text()).not.toContain(i18n.global.t('history.invalidChangesFormat'))
    wrapper.unmount()
  })

  it('does not blank the Customer history tab if one event has an outdated diff', async () => {
    state.history.mockResolvedValue({ data: [
      { ...base, id: '2', changes: { phone: { changed: true } } as never },
      base,
    ], meta: { page: 1, page_size: 25, total: 2 } })
    const wrapper = mountPanel()
    await flushPromises()
    expect(wrapper.get('.business-history__header').text()).toContain(i18n.global.t('history.title'))
    expect(wrapper.findAll('.business-history__event')).toHaveLength(2)
    expect(wrapper.get('[data-testid="history-invalid-changes"]').text())
      .toContain(i18n.global.t('history.invalidChangesFormat'))
    expect(wrapper.text()).toContain('customer_group_code: STANDARD → VIP1')
    expect(wrapper.text()).not.toContain('changed: true')
    wrapper.unmount()
  })
})

describe('Supplier change history reuses CUS component', () => {
  it('loads only Supplier events through Supplier API and redacts PII', async () => {
    state.supplier.mockResolvedValue({ data: [{ ...base,
      resource_type: 'supplier', resource_id: '125', changes: { phone: {},
        supplier_group_code: ['STANDARD', 'STANDARD2'] },
    }], meta: { page: 1, page_size: 25, total: 1 } })
    const wrapper = mount(BusinessHistoryPanel, {
      props: { supplierId: '125' },
      global: { plugins: [i18n], stubs: { ListPagination: true } },
    })
    await flushPromises()
    expect(state.history).not.toHaveBeenCalled()
    expect(state.supplier).toHaveBeenCalledWith('125', 1, 25)
    expect(wrapper.text()).toContain('supplier_group_code: STANDARD → STANDARD2')
    expect(wrapper.text()).toContain(i18n.global.t('history.redactedChange'))
    wrapper.unmount()
  })

  it('rejects both/absent owners without loading another parent history', async () => {
    const wrapper = mount(BusinessHistoryPanel, {
      props: { supplierId: '125', customerId: '42' },
      global: { plugins: [i18n], stubs: { ListPagination: true } },
    })
    await flushPromises()
    expect(state.history).not.toHaveBeenCalled()
    expect(state.supplier).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(i18n.global.t('history.loadError'))
    wrapper.unmount()
  })
})
