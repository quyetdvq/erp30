import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import AppButton from '../AppButton.vue'
import AppIconButton from '../AppIconButton.vue'

describe('AppButton', () => {
  it('uses non-submit native button semantics and renders a label', () => {
    const wrapper = mount(AppButton, { slots: { default: 'Save' } })
    expect(wrapper.get('button').attributes('type')).toBe('button')
    expect(wrapper.text()).toBe('Save')
    expect(wrapper.get('button').classes()).toContain('ds-button--secondary')
  })

  it('supports a form submit button only when explicitly requested', () => {
    const wrapper = mount(AppButton, { props: { type: 'submit', variant: 'primary' } })
    expect(wrapper.attributes('type')).toBe('submit')
    expect(wrapper.classes()).toContain('ds-button--primary')
  })

  it('prevents accidental submissions while loading', async () => {
    const click = vi.fn()
    const wrapper = mount(AppButton, { props: { loading: true }, attrs: { onClick: click } })
    expect(wrapper.attributes('disabled')).toBeDefined()
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.find('.ds-button__spinner').exists()).toBe(true)
    ;(wrapper.element as HTMLButtonElement).click()
    expect(click).not.toHaveBeenCalled()
  })

  it('has a real icon slot with consistent gap and decorative semantics', () => {
    const wrapper = mount(AppButton, {
      props: { variant: 'danger' },
      slots: { icon: '<svg aria-hidden="true"></svg>', default: 'Delete' },
    })
    expect(wrapper.find('.ds-button__icon').attributes('aria-hidden')).toBe('true')
    expect(wrapper.text()).toContain('Delete')
  })
})

describe('AppIconButton', () => {
  it('always exposes an accessible label and forwards clicks', async () => {
    const click = vi.fn()
    const wrapper = mount(AppIconButton, {
      props: { label: 'Close' },
      slots: { default: '<svg />' },
      attrs: { onClick: click },
    })
    expect(wrapper.get('button').attributes('aria-label')).toBe('Close')
    expect(wrapper.get('button').attributes('title')).toBe('Close')
    expect(wrapper.get('button').classes()).toContain('ds-button--icon-only')
    await wrapper.get('button').trigger('click')
    expect(click).toHaveBeenCalledTimes(1)
  })
})

describe('DS4 semantic action icons', () => {
  it('renders semantic icon, preserves native submit and disabled state', async () => {
    const wrapper = mount(AppButton, { props: { variant: 'primary', icon: 'save', type: 'submit' }, slots: { default: 'Save' } })
    expect(wrapper.get('button').attributes('type')).toBe('submit')
    expect(wrapper.get('button').attributes('data-ds-icon')).toBe('save')
    expect(wrapper.get('button').classes()).toContain('ds-button--primary')
    await wrapper.setProps({ loading: true })
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button').attributes('aria-busy')).toBe('true')
    expect(wrapper.find('.ds-button__spinner').exists()).toBe(true)
    expect(wrapper.get('button').attributes('data-ds-icon')).toBeUndefined()
    await wrapper.setProps({ loading: false, disabled: true })
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button').attributes('data-ds-icon')).toBe('save')
  })

  it('lets a visual icon slot take precedence over the CSS decoration', () => {
    const wrapper = mount(AppButton, {
      props: { icon: 'add' },
      slots: { icon: '<svg aria-hidden="true" />', default: 'Create' },
    })
    expect(wrapper.get('button').classes()).toContain('ds-button--icon-slot')
    expect(wrapper.get('button').attributes('data-ds-icon')).toBeUndefined()
    expect(wrapper.find('.ds-button__icon').exists()).toBe(true)
  })

  it('icon-only wrapper keeps an explicit accessible label and danger variant', () => {
    const wrapper = mount(AppIconButton, {
      props: { label: 'Delete', variant: 'danger-outline', size: 'sm' },
      slots: { default: '<svg aria-hidden="true" />' },
    })
    const button = wrapper.get('button')
    expect(button.attributes('aria-label')).toBe('Delete')
    expect(button.attributes('title')).toBe('Delete')
    expect(button.classes()).toContain('ds-button--icon-only')
    expect(button.classes()).toContain('ds-button--danger-outline')
    expect(button.classes()).toContain('ds-button--sm')
  })
})
