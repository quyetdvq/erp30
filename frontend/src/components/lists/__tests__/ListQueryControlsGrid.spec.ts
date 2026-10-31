import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import { i18n } from '@/i18n'
import ListQueryControls from '@/components/lists/ListQueryControls.vue'
import { createListQuery } from '@/components/lists/listTypes'

describe('unified query grid', () => {
  it('renders slot filters before the actions row, without changing Apply or Refresh semantics', async () => {
    const wrapper = mount(ListQueryControls, {
      props: {
        modelValue: createListQuery({ q: 'initial', page_size: 25 }),
        showPageSize: false,
        extraFieldCount: 1,
        filters: [{ key: 'status', labelKey: 'supplier.status', type: 'select', options: [
          { value: 'ACTIVE', labelKey: 'status.active' },
        ] }],
      },
      slots: {
        filters: '<label class="list-query-controls__field list-query-controls__field--wide" id="company-filter">Company<input /></label>',
        actions: '<button type="button" id="refresh-action">Refresh</button>',
      },
      global: { plugins: [i18n] },
    })
    const form = wrapper.get('form.list-query-controls')
    const grid = wrapper.get('.list-query-controls__grid')
    const fields = grid.findAll('label.list-query-controls__field')
    expect(fields).toHaveLength(3)
    expect(fields[2]?.attributes('id')).toBe('company-filter')
    expect(grid.attributes('style')).toContain('--ds-query-columns')
    expect(wrapper.get('.list-query-controls__actions').element.previousElementSibling).toBe(grid.element)

    await wrapper.get('#refresh-action').trigger('click')
    expect(wrapper.emitted('apply')).toBeUndefined()
    await form.trigger('submit')
    expect(wrapper.emitted('apply')).toHaveLength(1)
    expect(wrapper.emitted('apply')?.[0]?.[0]).toMatchObject({ q: 'initial', page: 1, page_size: 25 })
  })

  it('recomputes visible desktop tracks when filters change and keeps reset independent', async () => {
    const wrapper = mount(ListQueryControls, {
      props: { modelValue: createListQuery(), showPageSize: false, filters: [] },
      global: { plugins: [i18n] },
    })
    expect(wrapper.get('.list-query-controls__grid').attributes('style')).not.toContain('repeat(')
    await wrapper.setProps({ filters: Array.from({ length: 6 }, (_, index) => ({
      key: `filter${index}`, labelKey: 'supplier.status', type: 'select' as const, options: [],
    })) })
    expect(wrapper.classes()).toContain('list-query-controls--dense')
    expect(wrapper.get('.list-query-controls__grid').attributes('style')).toContain('minmax(140px, 1fr)')
    await wrapper.get('.list-query-controls__actions button[type="button"]').trigger('click')
    expect(wrapper.emitted('reset')).toHaveLength(1)
    expect(wrapper.emitted('apply')).toBeUndefined()
  })
})
