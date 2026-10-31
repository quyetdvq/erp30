import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import AppDialogHost from '../AppDialogHost.vue'
import { clearApiErrors, errorNotices } from '@/services/errorBoundary'

/**
 * Mock the named NModal export actually used by AppDialogHost. A mount-level
 * stub by component name cannot reliably replace Naive UI's teleported modal.
 */
vi.mock('naive-ui', async () => {
  const { defineComponent } = await import('vue')
  return {
    NModal: defineComponent({
      name: 'NModalStub',
      props: {
        show: Boolean,
        zIndex: Number,
        closeOnEsc: Boolean,
        maskClosable: Boolean,
        trapFocus: Boolean,
        autoFocus: Boolean,
        blockScroll: Boolean,
      },
      emits: ['update:show'],
      template: '<div class="n-modal-stub"><slot /></div>',
    }),
  }
})

describe('AppDialogHost', () => {
  afterEach(() => clearApiErrors())
  it('forwards focus, Escape, scroll lock, layering and size to its NModal owner', () => {
    const wrapper = mount(AppDialogHost, {
      props: { open: true, size: 'lg', maskClosable: false, closeOnEsc: true },
      slots: { default: '<section role="dialog">Body</section>' },
    })
    const modal = wrapper.getComponent({ name: 'NModalStub' })
    expect(modal.props()).toMatchObject({
      show: true,
      zIndex: 900,
      closeOnEsc: true,
      maskClosable: false,
      trapFocus: true,
      autoFocus: true,
      blockScroll: true,
    })
    expect(wrapper.find('.ds-dialog-host--lg').exists()).toBe(true)
    wrapper.unmount()
  })

  it('delegates overlay dismissal without mutating the caller open state', () => {
    const wrapper = mount(AppDialogHost, { props: { open: true } })
    const modal = wrapper.getComponent({ name: 'NModalStub' })
    modal.vm.$emit('update:show', false)
    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(wrapper.props('open')).toBe(true)
    modal.vm.$emit('update:show', true)
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('honors caller Escape and mask-close guards', async () => {
    const wrapper = mount(AppDialogHost, {
      props: { open: true, closeOnEsc: false, maskClosable: false },
    })
    const modal = wrapper.getComponent({ name: 'NModalStub' })
    expect(modal.props()).toMatchObject({ closeOnEsc: false, maskClosable: false })
    await wrapper.setProps({ closeOnEsc: true, maskClosable: true, open: false })
    expect(modal.props()).toMatchObject({ show: false, closeOnEsc: true, maskClosable: true })
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.unmount()
  })

  it('never inserts global errors into a dialog, even with nested modals', () => {
    errorNotices.value = [{
      id: '422:invalid:1', status: 422, code: 'INVALID', message: 'Validation failed', requestId: '1',
    }]
    const first = mount(AppDialogHost, { props: { open: true }, slots: { default: '<section role="dialog">First</section>' } })
    const second = mount(AppDialogHost, { props: { open: true }, slots: { default: '<section role="dialog">Second</section>' } })
    expect(first.find('.api-error-notice-stack').exists()).toBe(false)
    expect(second.find('.api-error-notice-stack').exists()).toBe(false)
    expect(first.getComponent({ name: 'NModalStub' }).props('zIndex')).toBe(900)
    expect(second.getComponent({ name: 'NModalStub' }).props('zIndex')).toBe(900)
    second.unmount()
    first.unmount()
  })
})
