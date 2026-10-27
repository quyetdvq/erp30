import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import { useWorkspaceStore } from '@/stores/workspace'
import NotificationPreferencesTab from '../pages/NotificationPreferencesTab.vue'
import NotificationPoliciesTab from '../pages/NotificationPoliciesTab.vue'
import type { NotificationPreference, NotificationPolicy } from '@/services/notificationSettings'

const mock = vi.hoisted(() => ({
  route: { name: 'display-preferences', path: '/profile/display-preferences' },
  getPreferences: vi.fn(), patchPreferences: vi.fn(),
  getPolicies: vi.fn(), patchPolicies: vi.fn(),
}))
vi.mock('vue-router', () => ({ useRoute: () => mock.route }))
vi.mock('@/services/notificationSettings', () => ({
  getNotificationPreferences: mock.getPreferences,
  patchNotificationPreferences: mock.patchPreferences,
  getNotificationPolicies: mock.getPolicies,
  patchNotificationPolicies: mock.patchPolicies,
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ getSessionGeneration: () => 1 }),
}))
vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({ hasBasePermission: () => true }),
}))
vi.mock('../pages/PushDevicesPanel.vue', () => ({ default: { template: '<div />' } }))

function preference(key: string, changes: Partial<NotificationPreference> = {}): NotificationPreference {
  return {
    key, system_default: false, allow_user_override: true,
    user_override: null, effective_value: false, effective_source: 'SYSTEM_DEFAULT',
    override_epoch: '2', user_revision: '0', locked: false,
    capability: 'AVAILABLE', producer_state: 'ACTIVE', ...changes,
  }
}
function policy(key: string, changes: Partial<NotificationPolicy> = {}): NotificationPolicy {
  return {
    key, default_value: false, allow_user_override: true,
    mandatory: false, capability: 'AVAILABLE', producer_state: 'ACTIVE',
    revision: '5', override_epoch: '2', ...changes,
  }
}

const activeWrappers: Array<{ unmount: () => void }> = []
function tracked<T extends { unmount: () => void }>(wrapper: T): T {
  activeWrappers.push(wrapper)
  return wrapper
}
function firesBeforeUnload(): boolean {
  const event = new Event('beforeunload', { cancelable: true })
  window.dispatchEvent(event)
  return event.defaultPrevented
}

beforeEach(() => {
  setActivePinia(createPinia())
  mock.route.name = 'display-preferences'
  mock.route.path = '/profile/display-preferences'
  mock.getPreferences.mockReset().mockResolvedValue([preference('import.failed.email')])
  mock.patchPreferences.mockReset()
  mock.getPolicies.mockReset().mockResolvedValue([policy('import.failed.email')])
  mock.patchPolicies.mockReset()
})
afterEach(() => {
  for (const wrapper of activeWrappers.splice(0)) wrapper.unmount()
})

describe('NFX-D2 unsaved Notification drafts use the existing ERP navigation guard', () => {
  it('personal: dirty on edit, clean on revert, protected before unload and cleaned on unmount', async () => {
    const workspace = useWorkspaceStore()
    const wrapper = tracked(mount(NotificationPreferencesTab, { global: { plugins: [i18n] } }))
    await flushPromises()
    expect(workspace.isDirty('display-preferences')).toBe(false)
    expect(firesBeforeUnload()).toBe(false)
    await wrapper.get('select').setValue('on')
    expect(workspace.isDirty('display-preferences')).toBe(true)
    expect(firesBeforeUnload()).toBe(true)
    await wrapper.get('select').setValue('inherit')
    expect(workspace.isDirty('display-preferences')).toBe(false)
    expect(firesBeforeUnload()).toBe(false)
    await wrapper.get('select').setValue('on')
    expect(workspace.isDirty('display-preferences')).toBe(true)
    wrapper.unmount()
    activeWrappers.splice(activeWrappers.indexOf(wrapper), 1)
    expect(workspace.isDirty('display-preferences')).toBe(false)
    expect(firesBeforeUnload()).toBe(false)
  })

  it('personal: successful CAS ACK clears dirty, while HTTP 409 keeps the draft protected', async () => {
    const workspace = useWorkspaceStore()
    mock.patchPreferences.mockResolvedValueOnce([preference('import.failed.email', {
      user_override: true, effective_value: true, effective_source: 'USER', user_revision: '1',
    })])
    const wrapper = tracked(mount(NotificationPreferencesTab, { global: { plugins: [i18n] } }))
    await flushPromises()
    await wrapper.get('select').setValue('on')
    expect(workspace.isDirty('display-preferences')).toBe(true)
    await wrapper.get('.m11-row button').trigger('click')
    await flushPromises()
    expect(workspace.isDirty('display-preferences')).toBe(false)
    await wrapper.get('select').setValue('off')
    mock.patchPreferences.mockRejectedValueOnce(Object.assign(new Error('409'), {
      isAxiosError: true, response: { status: 409 },
    }))
    await wrapper.get('.m11-row button').trigger('click')
    await flushPromises()
    expect((wrapper.get('select').element as HTMLSelectElement).value).toBe('off')
    expect(workspace.isDirty('display-preferences')).toBe(true)
    expect(firesBeforeUnload()).toBe(true)
  })

  it('personal: saving one of two edited rows keeps the route dirty until both are acknowledged', async () => {
    const workspace = useWorkspaceStore()
    mock.getPreferences.mockResolvedValue([
      preference('import.failed.email'), preference('import.completed_with_errors.email'),
    ])
    mock.patchPreferences.mockImplementation(async (changes: Array<{ key: string }>) => [
      preference(changes[0]!.key, {
        user_override: true, effective_value: true, effective_source: 'USER', user_revision: '1',
      }),
    ])
    const wrapper = tracked(mount(NotificationPreferencesTab, { global: { plugins: [i18n] } }))
    await flushPromises()
    const rows = wrapper.findAll('.m11-row')
    await rows[0]!.get('select').setValue('on')
    await rows[1]!.get('select').setValue('on')
    expect(workspace.isDirty('display-preferences')).toBe(true)
    await rows[0]!.get('button').trigger('click')
    await flushPromises()
    expect(workspace.isDirty('display-preferences')).toBe(true)
    await rows[1]!.get('button').trigger('click')
    await flushPromises()
    expect(workspace.isDirty('display-preferences')).toBe(false)
    expect(mock.patchPreferences).toHaveBeenCalledTimes(2)
  })

  it('admin: policy drafts enter/leave dirty state without any write or privilege change', async () => {
    const workspace = useWorkspaceStore()
    mock.route.name = 'display-settings'
    mock.route.path = '/system/display-settings'
    const wrapper = tracked(mount(NotificationPoliciesTab, { global: { plugins: [i18n] } }))
    await flushPromises()
    expect(workspace.isDirty('display-settings')).toBe(false)
    const input = wrapper.findAll('.m11-row input[type="checkbox"]')[1]!
    await input.setValue(false)
    expect(workspace.isDirty('display-settings')).toBe(true)
    expect(firesBeforeUnload()).toBe(true)
    await input.setValue(true)
    expect(workspace.isDirty('display-settings')).toBe(false)
    expect(firesBeforeUnload()).toBe(false)
    expect(mock.patchPolicies).not.toHaveBeenCalled()
  })
})
