import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import AppModal from '../AppModal.vue'

// Render the content owned by AppDialogHost without relying on NModal's
// browser Teleport. Its actual portal and focus props have separate tests.
vi.mock('naive-ui', async () => {
  const { defineComponent } = await import('vue')
  return {
    NModal: defineComponent({
      name: 'NModalStub',
      props: { show: Boolean },
      template: '<div class="modal-host-stub"><slot /></div>',
    }),
  }
})
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

describe('AppModal shared Close X', () => {
  it('renders one semantic, non-submit Close button and retains close events', async () => {
    const wrapper = mount(AppModal, { props: { open: true, title: 'Record details' } })
    const x = wrapper.get<HTMLButtonElement>('button.modal-card-button-close')
    expect(x.attributes('type')).toBe('button')
    expect(x.attributes('aria-label')).toBe('common.close')
    expect(x.element.childElementCount).toBe(0) // CSS mask draws the only X
    await x.trigger('click')
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })

  it('honors showClose=false for in-progress confirmations', () => {
    const wrapper = mount(AppModal, { props: { open: true, title: 'Busy', showClose: false } })
    expect(wrapper.find('button.modal-card-button-close').exists()).toBe(false)
    wrapper.unmount()
  })
})
