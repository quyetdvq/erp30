import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import { i18n } from '@/i18n'
import { nextTick } from 'vue'
import ListExportButton from '@/components/lists/ListExportButton.vue'
import ListPagination from '@/components/lists/ListPagination.vue'
import ListShell from '@/components/lists/ListShell.vue'
import ListTable from '@/components/lists/ListTable.vue'
import TableDetailModal from '@/components/lists/TableDetailModal.vue'
import TableRowActions from '@/components/lists/TableRowActions.vue'

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

  it('retries failed initial loading, and retains old rows when refreshing fails', async () => {
    const wrapper = mount(ListShell, {
      props: { error: new Error('unavailable'), retryable: true },
      slots: { default: '<p class="saved-row">Cached row</p>' },
      global: { plugins: [i18n] },
    })
    expect(wrapper.get('[role="alert"]').text()).toContain('Không thể tải')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('retry')?.length).toBe(1)

    await wrapper.setProps({ hasData: true, refreshing: true, emptyDataset: true })
    expect(wrapper.get('.saved-row').text()).toBe('Cached row')
    expect(wrapper.find('.list-shell__refreshing').exists()).toBe(true)
    expect(wrapper.find('button').exists()).toBe(false)

    await wrapper.setProps({ refreshing: false })
    expect(wrapper.get('[role="alert"]').text()).toContain('Không thể làm mới')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('retry')?.length).toBe(2)
  })

  it('disables all row mutations and sort controls during a pending request', async () => {
    setActivePinia(createPinia())
    const actions = mount(TableRowActions, {
      props: {
        busy: true,
        showView: true,
        showEdit: true,
        showToggle: true,
        showDelete: true,
        active: true,
      },
      global: { plugins: [i18n] },
    })
    const buttons = actions.findAll('button')
    expect(buttons).toHaveLength(4)
    expect(buttons.every((button) => button.attributes('disabled') !== undefined)).toBe(true)
    await buttons[0]!.trigger('click')
    expect(actions.emitted('view')).toBeUndefined()
    await actions.setProps({ busy: false })
    expect(actions.get('button').attributes('disabled')).toBeUndefined()

    const table = mount(ListTable, {
      props: {
        rows: [{ id: '1', name: 'Sample' }],
        columns: [{ key: 'name', labelKey: 'people.name', sortable: true }],
        busy: true,
      },
      global: { plugins: [i18n] },
    })
    expect(table.get('th button').attributes('disabled')).toBeDefined()
    await table.get('th button').trigger('click')
    expect(table.emitted('sort-change')).toBeUndefined()
    await table.setProps({ busy: false })
    await table.get('th button').trigger('click')
    expect(table.emitted('sort-change')?.[0]).toEqual(['name'])
  })

  it('focuses a detail modal and restores keyboard focus after Escape closes it', async () => {
    const opener = document.createElement('button')
    document.body.append(opener)
    opener.focus()
    const wrapper = mount(TableDetailModal, {
      attachTo: document.body,
      props: {
        open: false,
        title: 'Record details',
        fields: [{ key: 'id', label: 'ID', value: '31' }],
      },
      global: { plugins: [i18n] },
    })
    await wrapper.setProps({ open: true })
    await nextTick()
    expect(document.activeElement).toBe(wrapper.get('.table-detail-modal__close').element)
    await wrapper
      .get('.table-detail-modal__close')
      .trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(document.activeElement).toBe(wrapper.get('.table-detail-modal__footer button').element)
    await wrapper.get('.table-detail-modal__footer button').trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(wrapper.get('.table-detail-modal__close').element)
    await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
    expect(wrapper.emitted('close')).toHaveLength(1)
    await wrapper.setProps({ open: false })
    await nextTick()
    expect(document.activeElement).toBe(opener)
    wrapper.unmount()
    opener.remove()
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
    expect(wrapper.get('tbody td').attributes('style')).toContain('text-align: start')
  })

  it('hides actions without an authoritative permission snapshot', () => {
    setActivePinia(createPinia())
    const wrapper = mount(TableRowActions, {
      props: {
        showView: true,
        showEdit: true,
        viewPermission: 'product.view',
        editPermission: 'product.edit',
      },
      global: { plugins: [i18n] },
    })
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('right-aligns shared actions columns unless the caller overrides them', () => {
    setActivePinia(createPinia())
    const wrapper = mount(ListTable, {
      props: {
        rows: [{ id: '1', actions: '' }],
        columns: [{ key: 'actions', labelKey: 'people.actions' }],
      },
      global: { plugins: [i18n] },
    })
    expect(wrapper.get('th').attributes('style')).toContain('text-align: end')
    expect(wrapper.get('td').attributes('style')).toContain('text-align: end')
  })

  it('renders status columns as accessible state icons', () => {
    const wrapper = mount(ListTable, {
      props: {
        rows: [{ id: '1', status: 'ACTIVE' }],
        columns: [{ key: 'status', labelKey: 'locations.status' }],
      },
      global: { plugins: [i18n] },
    })

    const status = wrapper.get('[role="img"]')
    expect(status.classes()).toContain('table-status--active')
    expect(status.attributes('aria-label')).toBe('Đang hoạt động')
    expect(status.find('.sr-only').text()).toBe('Đang hoạt động')
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
