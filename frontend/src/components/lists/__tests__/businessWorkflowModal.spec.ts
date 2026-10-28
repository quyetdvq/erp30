import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { i18n } from '@/i18n'
import BusinessWorkflowAction from '@/components/lists/BusinessWorkflowAction.vue'

const calls = vi.hoisted(() => ({
  options: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  listing: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  candidates: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  replace: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  execute: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))
vi.mock('@/services/businessWorkflow', () => ({
  getBusinessWorkflowOptions: calls.options,
  getWorkflowNotificationAssignees: calls.listing,
  getWorkflowAssigneeCandidates: calls.candidates,
  replaceWorkflowNotificationAssignees: calls.replace,
  executeBusinessWorkflowOption: calls.execute,
}))
vi.mock('@/features/notifications/featureFlags', () => ({ inboxUiEnabled: true }))
vi.mock('@/services/errorBoundary', () => ({
  reportApiError: (_error: unknown): void => {},
  normalizeApiError: (_error: unknown): null => null,
}))

const RecipientPicker = defineComponent({
  name: 'RemoteMultiSearchableSelect',
  emits: ['update:modelValue'],
  template: '<button type="button" class="mock-pick" @click="$emit(\'update:modelValue\', [\'3\'])">Select User</button>',
})
const cleanups: Array<() => void> = []
function modal(): DOMWrapper<HTMLElement> { return new DOMWrapper(document.body) }
function view() {
  const wrapper = mount(BusinessWorkflowAction, {
    props: { resource: 'product', targetId: '9' },
    global: { plugins: [i18n], stubs: { RemoteMultiSearchableSelect: RecipientPicker } },
  })
  cleanups.push(() => wrapper.unmount())
  return wrapper
}
afterEach(() => { cleanups.splice(0).forEach(cleanup => cleanup()) })
beforeEach(() => {
  Object.values(calls).forEach(mock => mock.mockReset())
  calls.options.mockResolvedValue({
    mode: 'LIFECYCLE', current: 'DRAFT',
    options: [{ action_code: 'setstate', status: 'ACTIVE' }],
  })
  calls.listing.mockResolvedValue({ revision: '0', user_ids: [] })
  calls.candidates.mockResolvedValue({ data: [], meta: { page: 1, page_size: 25, total: 0 } })
  calls.replace.mockResolvedValue({ revision: '1', user_ids: ['3'] })
  calls.execute.mockResolvedValue(undefined)
})

describe('shared Workflow state transition dialog', () => {
  it('teleports the dialog outside a table cell with nowrap/scroll styling', async () => {
    const wrapper = view()
    await wrapper.get('button[aria-label]').trigger('click')
    await flushPromises()
    const backdrop = document.querySelector('.workflow-dialog-backdrop')
    expect(backdrop?.parentElement).toBe(document.body)
    expect(modal().find('.workflow-dialog').exists()).toBe(true)
  })
  it('can execute an authorized transition with no notification recipients', async () => {
    const wrapper = view()
    await wrapper.get('button[aria-label]').trigger('click')
    await flushPromises()
    expect(modal().text()).toContain(i18n.global.t('workflow.notificationAssignmentOptional'))
    expect(modal().find('.workflow-notify-editor').exists()).toBe(false)
    await modal().get('.workflow-select-label select').setValue('0')
    await modal().get('.workflow-dialog__footer .primary-button').trigger('click')
    await flushPromises()
    expect(calls.execute).toHaveBeenCalledWith('product', '9', 'LIFECYCLE', {
      action_code: 'setstate', status: 'ACTIVE',
    })
    expect(calls.replace).not.toHaveBeenCalled()
    expect(wrapper.emitted('changed')).toEqual([['ACTIVE']])
  })

  it('requires explicitly saving a changed notification recipient list before transition', async () => {
    const wrapper = view()
    await wrapper.get('button[aria-label]').trigger('click')
    await flushPromises()
    await modal().get('.workflow-select-label select').setValue('0')
    await modal().get('.workflow-notify-toggle').trigger('click')
    await flushPromises()
    await modal().get('.mock-pick').trigger('click')
    await flushPromises()
    const executeButton = modal().get('.workflow-dialog__footer .primary-button')
    expect(executeButton.attributes('disabled')).toBeDefined()
    expect(modal().get('.workflow-unsaved-notice').attributes('role')).toBe('alert')
    expect(calls.execute).not.toHaveBeenCalled()
    await modal().get('.workflow-notify-controls button').trigger('click')
    await flushPromises()
    expect(calls.replace).toHaveBeenCalledWith('product', '9', '0', ['3'])
    expect(executeButton.attributes('disabled')).toBeUndefined()
    await executeButton.trigger('click')
    await flushPromises()
    expect(calls.execute).toHaveBeenCalledTimes(1)
  })

  it('keeps the state transition available if notification assignment cannot load', async () => {
    calls.listing.mockRejectedValueOnce(new Error('notification unavailable'))
    const wrapper = view()
    await wrapper.get('button[aria-label]').trigger('click')
    await flushPromises()
    await modal().get('.workflow-notify-toggle').trigger('click')
    expect(modal().find('.workflow-notify-retry[role="alert"]').exists()).toBe(true)
    await modal().get('.workflow-select-label select').setValue('0')
    expect(modal().get('.workflow-dialog__footer .primary-button').attributes('disabled')).toBeUndefined()
  })
})
