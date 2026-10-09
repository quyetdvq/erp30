import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'

import DecimalInput from '@/components/forms/DecimalInput.vue'
import { useDisplaySettingsStore } from '@/stores/displaySettings'

describe('DecimalInput', () => {
  it('keeps the API value canonical while formatting money for display', async () => {
    const wrapper = mount(DecimalInput, {
      props: { modelValue: '1234.5', kind: 'money' },
      global: { plugins: [createPinia()] },
    })
    const input = wrapper.find('input')

    expect(input.element.value).toBe('1,234.5')
    await input.trigger('focus')
    await input.setValue('1234.56')
    expect(input.element.value).toBe('1,234.56')
    const updates = wrapper.emitted('update:modelValue') ?? []
    expect(updates[updates.length - 1]).toEqual(['1234.56'])

    await input.trigger('blur')
    expect(input.element.value).toBe('1,234.56')
  })

  it('does not reinterpret a grouping separator while typing', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const displaySettings = useDisplaySettingsStore()
    displaySettings.activateUser(1)
    displaySettings.setLocalPreference('Display.money.decimalSeparator', ',')
    displaySettings.setLocalPreference('Display.money.groupSeparator', '.')

    const wrapper = mount(DecimalInput, {
      props: { modelValue: '', kind: 'money' },
      global: { plugins: [pinia] },
    })
    const input = wrapper.find('input')
    await input.trigger('focus')

    for (const digit of '15555555') {
      await input.setValue(`${input.element.value}${digit}`)
    }

    expect(input.element.value).toBe('15.555.555')
    const updates = wrapper.emitted('update:modelValue') ?? []
    expect(updates[updates.length - 1]).toEqual(['15555555'])
  })

  it('limits editable precision to the field scale', async () => {
    const wrapper = mount(DecimalInput, {
      props: { modelValue: '', kind: 'number', maxDecimals: 3 },
      global: { plugins: [createPinia()] },
    })
    const input = wrapper.find('input')

    await input.trigger('focus')
    await input.setValue('1.555555555555')

    expect(input.element.value).toBe('1.555')
    const updates = wrapper.emitted('update:modelValue') ?? []
    expect(updates[updates.length - 1]).toEqual(['1.555'])
  })
})
