import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { NSelect } from 'naive-ui'

import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import { normalizeSearchText, searchableSelectFilter } from '@/components/forms/searchableSelect'

describe('SearchableSelect', () => {
  it('matches option labels in real time without case sensitivity', () => {
    expect(normalizeSearchText('  NORTH ')).toBe('north')
    expect(searchableSelectFilter('north', { label: 'Organization North' })).toBe(true)
    expect(searchableSelectFilter('south', { label: 'Organization North' })).toBe(false)
  })

  it('emits canonical string IDs and preserves multiple selection values', async () => {
    const wrapper = mount(SearchableSelect, {
      props: {
        modelValue: '42',
        options: [{ value: '42', label: 'Organization North' }],
      },
    })
    await wrapper.getComponent(NSelect).vm.$emit('update:value', 42)
    const emittedSingle = wrapper.emitted('update:modelValue') ?? []
    expect(emittedSingle[emittedSingle.length - 1]).toEqual(['42'])

    const multiple = mount(SearchableSelect, {
      props: {
        multiple: true,
        options: [
          { value: '42', label: 'Organization North' },
          { value: '84', label: 'Organization South' },
        ],
      },
    })
    await multiple.getComponent(NSelect).vm.$emit('update:value', [42, '84'])
    const emittedMultiple = multiple.emitted('update:modelValue') ?? []
    expect(emittedMultiple[emittedMultiple.length - 1]).toEqual([['42', '84']])
  })
})
