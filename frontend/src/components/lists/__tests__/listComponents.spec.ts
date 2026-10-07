import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import { i18n } from '@/i18n'
import ListExportButton from '@/components/lists/ListExportButton.vue'
import ListPagination from '@/components/lists/ListPagination.vue'
import ListShell from '@/components/lists/ListShell.vue'
import ListTable from '@/components/lists/ListTable.vue'

describe('shared list components', () => {
  it('exposes distinct shell states and preserves data during refresh', () => {
    const wrapper = mount(ListShell, {
      props: { initialLoading: true },
      global: { plugins: [i18n] },
    })
    expect(wrapper.get('[role="status"]').text()).toContain('Đang tải')
    return wrapper
      .setProps({ initialLoading: false, hasData: true, refreshing: true, error: new Error('x') })
      .then(() => {
        expect(wrapper.get('[role="alert"]').text()).toContain('Không thể làm mới')
        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)
      })
  })

  it('renders canonical string values and accessible sortable headers', () => {
    setActivePinia(createPinia())
    const wrapper = mount(ListTable, {
      props: {
        rows: [{ id: '9007199254740993', amount: '1234.5' }],
        columns: [
          { key: 'id', labelKey: 'common.brandSubtitle' },
          { key: 'amount', labelKey: 'common.brandSubtitle', sortable: true, format: 'money' },
        ],
      },
      global: { plugins: [i18n] },
    })
    expect(wrapper.text()).toContain('9007199254740993')
    expect(wrapper.find('th[aria-sort="none"] button').exists()).toBe(true)
    expect(wrapper.find('table caption').text()).toContain('Danh sách')
  })

  it('uses backend total for pagination and emits page changes', async () => {
    const wrapper = mount(ListPagination, {
      props: { page: 2, pageSize: 50, total: 101 },
      global: { plugins: [i18n] },
    })
    expect(wrapper.text()).toContain('Trang 2/3')
    await wrapper.get('button[aria-label="Trang sau"]').trigger('click')
    expect(wrapper.emitted('update:page')?.[0]).toEqual([3])
  })

  it('only exposes export plumbing when a feature supplies an export action', async () => {
    const hidden = mount(ListExportButton, { global: { plugins: [i18n] } })
    expect(hidden.find('button').exists()).toBe(false)

    let called = 0
    const wrapper = mount(ListExportButton, {
      props: { exportAction: async () => ++called },
      global: { plugins: [i18n] },
    })
    await wrapper.get('button').trigger('click')
    expect(called).toBe(1)
    expect(wrapper.emitted('completed')?.[0]).toEqual([1])
  })
})
