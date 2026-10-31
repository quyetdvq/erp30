import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

import ApiErrorNoticeStack from '../ApiErrorNoticeStack.vue'
import { clearApiErrors, errorNotices } from '@/services/errorBoundary'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('naive-ui', async () => {
  const { defineComponent } = await import('vue')
  return {
    NAlert: defineComponent({
      name: 'NAlertStub',
      props: { type: String, closable: Boolean },
      emits: ['close'],
      template: '<article class="n-alert-stub"><button type="button" @click="$emit(\'close\')">Dismiss</button><slot /></article>',
    }),
    NIcon: defineComponent({ template: '<span><slot /></span>' }),
  }
})

describe('ApiErrorNoticeStack', () => {
  afterEach(() => { clearApiErrors(); document.body.innerHTML = '' })

  it('teleports exactly one notice stack to body and preserves code/message/requestId', async () => {
    const target = document.createElement('div')
    document.body.appendChild(target)
    const wrapper = mount(ApiErrorNoticeStack, { attachTo: target })
    expect(document.querySelector('[data-test="global-api-notices"]')).toBeNull()
    errorNotices.value = [{ id: '422:BAD_CUSTOMER:1', status: 422, code: 'BAD_CUSTOMER', message: 'Validation failed', requestId: 'req-1' }]
    await nextTick()
    const stacks = document.querySelectorAll('body > .api-error-notice-stack')
    expect(stacks.length).toBe(1)
    expect(stacks[0]?.textContent).toContain('BAD_CUSTOMER')
    expect(stacks[0]?.textContent).toContain('Validation failed')
    expect(stacks[0]?.textContent).toContain('req-1')
    expect(stacks[0]?.getAttribute('aria-live')).toBe('assertive')
    wrapper.unmount()
  })

  it('owns severity classes for both alert types, independent of Naive UI internal modifiers', async () => {
    const target = document.createElement('div')
    document.body.appendChild(target)
    const wrapper = mount(ApiErrorNoticeStack, { attachTo: target })
    errorNotices.value = [{ id: '422:BAD_CUSTOMER:severity', status: 422, code: 'BAD_CUSTOMER', message: 'Invalid customer', requestId: 'severity' }]
    await nextTick()
    expect(document.querySelector('.n-alert-stub.api-error-notice--error')).not.toBeNull()
    expect(document.querySelector('.n-alert-stub.api-error-notice--warning')).toBeNull()
    errorNotices.value = [{ id: '403:FORBIDDEN:severity', status: 403, code: 'FORBIDDEN', message: 'Forbidden', requestId: 'severity' }]
    await nextTick()
    expect(document.querySelector('.n-alert-stub.api-error-notice--warning')).not.toBeNull()
    expect(document.querySelector('.n-alert-stub.api-error-notice--error')).toBeNull()
    expect(wrapper.findComponent({ name: 'NAlertStub' }).props('type')).toBe('warning')
    wrapper.unmount()
  })

  it('keeps the same box style and allows dismissing an error without closing its form', async () => {
    const target = document.createElement('div')
    document.body.appendChild(target)
    const wrapper = mount(ApiErrorNoticeStack, { attachTo: target })
    errorNotices.value = [{ id: '403:FORBIDDEN:2', status: 403, code: 'FORBIDDEN', message: 'Permission denied', requestId: 'req-2' }]
    await nextTick()
    expect(wrapper.findComponent({ name: 'NAlertStub' }).props()).toMatchObject({ type: 'warning', closable: true })
    const close = document.querySelector<HTMLButtonElement>('.n-alert-stub button')
    expect(close).not.toBeNull()
    close?.click()
    await nextTick()
    expect(errorNotices.value).toHaveLength(0)
    expect(document.querySelector('[data-test="global-api-notices"]')).toBeNull()
    wrapper.unmount()
  })
})
