import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { NSelect } from 'naive-ui'
import { describe, expect, it, vi } from 'vitest'

import RemoteMultiSearchableSelect from '@/components/forms/RemoteMultiSearchableSelect.vue'

type SearchAssignees = (q: string, page: number) => Promise<{
  options: { value: string; label: string }[]
  total: number
}>

describe('Workflow RemoteMultiSearchableSelect', () => {
  it('accepts up to 25 canonical string User IDs and rejects invalid selections', async () => {
    setActivePinia(createPinia())
    const wrapper = mount(RemoteMultiSearchableSelect, {
      props: { modelValue: ['3'], ariaLabel: 'Assignees', search: vi.fn<SearchAssignees>() },
    })
    const select = wrapper.getComponent(NSelect)
    await select.vm.$emit('update:value', [3, '4'])
    expect(wrapper.emitted('update:modelValue')).toEqual([[['3', '4']]])
    await select.vm.$emit('update:value', ['3', '3'])
    await select.vm.$emit('update:value', ['0'])
    await select.vm.$emit('update:value', Array.from({ length: 26 }, (_, index) => String(index + 1)))
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)
    wrapper.unmount()
  })

  it('fetches paginated users on open and merges selected labels', async () => {
    setActivePinia(createPinia())
    const search = vi.fn<SearchAssignees>().mockResolvedValue({
      options: [{ value: '4', label: 'User Four' }], total: 1,
    })
    const wrapper = mount(RemoteMultiSearchableSelect, {
      props: {
        modelValue: ['3'], ariaLabel: 'Assignees',
        selectedOptions: [{ value: '3', label: 'User Three' }], search,
      },
    })
    await wrapper.getComponent(NSelect).vm.$emit('update:show', true)
    await vi.waitFor(() => expect(wrapper.getComponent(NSelect).props('options')).toHaveLength(2))
    expect(search).toHaveBeenCalledWith('', 1)
    expect(wrapper.getComponent(NSelect).props('to')).toBe(false)
    const values = wrapper.getComponent(NSelect).props('options') as Array<{ value: string }>
    expect(values.map(option => option.value)).toEqual(['3', '4'])
    wrapper.unmount()
  })
  it('displays a useful empty result and keeps the popup within its modal container', async () => {
    setActivePinia(createPinia())
    const search = vi.fn<SearchAssignees>().mockResolvedValue({ options: [], total: 0 })
    const container = document.createElement('section')
    const wrapper = mount(RemoteMultiSearchableSelect, {
      props: {
        modelValue: [], ariaLabel: 'Assignees', search, popupTarget: container,
        emptyLabel: 'No active users',
      },
    })
    expect(wrapper.getComponent(NSelect).props('to')).toBe(container)
    await wrapper.getComponent(NSelect).vm.$emit('update:show', true)
    await vi.waitFor(() => expect(wrapper.get('.remote-select-feedback[role="status"]').text()).toContain('No active users'))
    expect(search).toHaveBeenCalledWith('', 1)
    wrapper.unmount()
  })

})
