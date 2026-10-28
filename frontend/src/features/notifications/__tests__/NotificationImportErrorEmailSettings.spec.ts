import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import NotificationPoliciesTab from '../pages/NotificationPoliciesTab.vue'
import NotificationPreferencesTab from '../pages/NotificationPreferencesTab.vue'
import type { NotificationPolicy, NotificationPreference } from '@/services/notificationSettings'

const mock = vi.hoisted(() => ({
  getPolicies: vi.fn<(...args: unknown[]) => Promise<unknown>>(), patchPolicies: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  getPreferences: vi.fn<(...args: unknown[]) => Promise<unknown>>(), patchPreferences: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))
// D1/C1 tests isolate consent and CAS behavior; D2 has dedicated real workspace-guard tests.
vi.mock('@/composables/useUnsavedChanges', () => ({ useUnsavedChanges: () => ({}) }))
vi.mock('@/services/notificationSettings', () => ({
  getNotificationPolicies: mock.getPolicies,
  patchNotificationPolicies: mock.patchPolicies,
  getNotificationPreferences: mock.getPreferences,
  patchNotificationPreferences: mock.patchPreferences,
}))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ getSessionGeneration: () => 1 }) }))
vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({ hasBasePermission: () => true }),
}))
vi.mock('../pages/PushDevicesPanel.vue', () => ({ default: { template: '<div />' } }))

const keys = ['import.completed_with_errors.email', 'import.failed.email'] as const
const policies: NotificationPolicy[] = keys.map(key => ({
  key, default_value: false, allow_user_override: true, mandatory: false,
  capability: 'AVAILABLE', producer_state: 'ACTIVE', revision: '1', override_epoch: '1',
}))
const preferences: NotificationPreference[] = keys.map(key => ({
  key, system_default: false, allow_user_override: true, user_override: null,
  effective_value: false, effective_source: 'SYSTEM_DEFAULT', override_epoch: '1',
  user_revision: '0', locked: false, capability: 'AVAILABLE', producer_state: 'ACTIVE',
}))

beforeEach(() => {
  mock.getPolicies.mockReset().mockResolvedValue(policies)
  mock.patchPolicies.mockReset().mockResolvedValue(policies)
  mock.getPreferences.mockReset().mockResolvedValue(preferences)
  mock.patchPreferences.mockReset().mockResolvedValue(preferences)
})

describe('NFX-C1 explicit per-Import-error email consent', () => {
  it('shows separate translated labels and disables admin system default for both email events', async () => {
    const wrapper = mount(NotificationPoliciesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const rows = wrapper.findAll('.m11-row')
    expect(rows).toHaveLength(2)
    expect(rows[0]?.text()).toContain(i18n.global.t('notificationSettings.keys.importCompletedWithErrorsEmail'))
    expect(rows[1]?.text()).toContain(i18n.global.t('notificationSettings.keys.importFailedEmail'))
    for (const row of rows) {
      expect(row.find('input[type="checkbox"]').attributes('disabled')).toBeDefined()
      expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.channelHelpSystem.email'))
    }
  })

  it('starts at Inherit (no email consent) independently for both new events', async () => {
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const rows = wrapper.findAll('.m11-row')
    expect(rows).toHaveLength(2)
    for (const row of rows) {
      expect((row.get('select').element as HTMLSelectElement).value).toBe('inherit')
      expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.channelHelp.email'))
    }
  })

  it('saves only the explicitly selected failure event opt-in, never the partial event', async () => {
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const failed = wrapper.findAll('.m11-row')[1]
    if (!failed) throw new Error('Missing failed email policy fixture')
    await failed.get('select').setValue('on')
    await failed.get('button').trigger('click')
    await flushPromises()
    expect(mock.patchPreferences).toHaveBeenCalledWith([{
      key: 'import.failed.email', value: true, expected_override_epoch: '1', expected_user_revision: '0',
    }])
    expect(mock.patchPreferences).toHaveBeenCalledTimes(1)
  })
})
