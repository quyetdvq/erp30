import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import NotificationPoliciesTab from '../pages/NotificationPoliciesTab.vue'
import NotificationPreferencesTab from '../pages/NotificationPreferencesTab.vue'
import type { NotificationPolicy, NotificationPreference } from '@/services/notificationSettings'
import {
  groupedNotificationRows, isExplicitConsentChannel, notificationChannel,
  policyCanSubmit, policyChanged, preferenceChanged, preferenceSelection, preferenceSourceLabel, notificationLabelKey,
} from '../notificationSettingsPresentation'

const mock = vi.hoisted(() => ({
  getPolicies: vi.fn<(...args: unknown[]) => Promise<unknown>>(), patchPolicies: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  getPreferences: vi.fn<(...args: unknown[]) => Promise<unknown>>(), patchPreferences: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  generation: 1, editAllowed: true,
}))
// D1/C1 tests isolate consent and CAS behavior; D2 has dedicated real workspace-guard tests.
vi.mock('@/composables/useUnsavedChanges', () => ({ useUnsavedChanges: () => ({}) }))
vi.mock('@/services/notificationSettings', () => ({
  getNotificationPolicies: mock.getPolicies,
  patchNotificationPolicies: mock.patchPolicies,
  getNotificationPreferences: mock.getPreferences,
  patchNotificationPreferences: mock.patchPreferences,
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ getSessionGeneration: () => mock.generation }),
}))
vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({ hasBasePermission: () => mock.editAllowed }),
}))
vi.mock('../pages/PushDevicesPanel.vue', () => ({
  default: { props: ['consented'], template: '<div data-testid="push-consent">{{ consented }}</div>' },
}))

const keys = [
  'import.completed.in_app', 'import.completed_with_errors.in_app', 'import.failed.in_app',
  'workflow.action_required.in_app', 'security.permission_changed.in_app',
  'import.completed.email', 'import.completed_with_errors.email', 'import.failed.email',
  'import.completed.push', 'import.completed_with_errors.push', 'import.failed.push', 'workflow.action_required.push',
]
function policy(key: string, updates: Partial<NotificationPolicy> = {}): NotificationPolicy {
  return {
    key, default_value: key.endsWith('.in_app'), allow_user_override: true,
    mandatory: false, capability: 'AVAILABLE', producer_state: 'ACTIVE',
    revision: '5', override_epoch: '2', ...updates,
  }
}
function preference(key: string, updates: Partial<NotificationPreference> = {}): NotificationPreference {
  return {
    key, system_default: key.endsWith('.in_app'), allow_user_override: true,
    user_override: null, effective_value: key.endsWith('.in_app'),
    effective_source: 'SYSTEM_DEFAULT', override_epoch: '2', user_revision: '0',
    locked: false, capability: 'AVAILABLE', producer_state: 'ACTIVE', ...updates,
  }
}
function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => { resolve = done })
  return { promise, resolve }
}

beforeEach(() => {
  mock.generation = 1
  mock.editAllowed = true
  mock.getPolicies.mockReset().mockResolvedValue(keys.map((key) => policy(key)))
  mock.patchPolicies.mockReset()
  mock.getPreferences.mockReset().mockResolvedValue(keys.map((key) => preference(key)))
  mock.patchPreferences.mockReset()
})

describe('NFX-D1 channel and consent presentation helpers', () => {
  it('groups every key without dropping unknown future policies', () => {
    const grouped = groupedNotificationRows(keys.concat('future.sms').map(key => ({ key })))
    expect(grouped.map(({ channel, items }) => [channel, items.length])).toEqual([
      ['in_app', 5], ['email', 3], ['push', 4], ['other', 1],
    ])
    expect(notificationChannel('future.sms')).toBe('other')
    expect(notificationLabelKey('import.failed.email')).toBe('importFailedEmail')
    expect(notificationLabelKey('future.sms')).toBeNull()
    expect(isExplicitConsentChannel('import.failed.email')).toBe(true)
    expect(isExplicitConsentChannel('import.completed.push')).toBe(true)
    expect(isExplicitConsentChannel('import.failed.in_app')).toBe(false)
  })

  it('distinguishes Inherit from explicit opt-in and refuses unsafe policy defaults', () => {
    const row = preference('import.failed.email')
    expect(preferenceSelection(null)).toBe('inherit')
    expect(preferenceChanged(row, 'inherit')).toBe(false)
    expect(preferenceChanged(row, 'on')).toBe(true)
    expect(preferenceChanged(row, 'invalid')).toBe(false)
    const adminRow = policy('import.failed.email')
    expect(policyCanSubmit(adminRow, { default_value: true, allow_user_override: true })).toBe(false)
    expect(policyCanSubmit(adminRow, { default_value: false, allow_user_override: false })).toBe(true)
    expect(policyChanged(adminRow, { default_value: false, allow_user_override: true })).toBe(false)
    expect(preferenceSourceLabel('UNTRUSTED_SERVER_CODE')).toBe('sourceUnknown')
  })
})

describe('NFX-D1 personal notifications UX', () => {
  it('groups all twelve policies, clarifies opt-in and renders translated effective sources', async () => {
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.findAll('.m11-group')).toHaveLength(3)
    expect(wrapper.findAll('.m11-row')).toHaveLength(12)
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.channels.email'))
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.sourceSystem'))
    const emailRow = wrapper.findAll('.m11-row')[5]
    expect(emailRow?.get('select').find('option[value="inherit"]').text())
      .toBe(i18n.global.t('notificationSettings.noConsentInherit'))
    expect(emailRow?.get('button').attributes('disabled')).toBeDefined()
  })

  it('never treats an unsaved Push draft as device registration consent', async () => {
    const pushed = preference('import.completed.push', { user_override: null, effective_value: false })
    mock.getPreferences.mockResolvedValue([pushed])
    const success = preference('import.completed.push', {
      user_override: true, effective_value: true, effective_source: 'USER', user_revision: '1',
    })
    const receipt = deferred<NotificationPreference[]>()
    mock.patchPreferences.mockReturnValue(receipt.promise)
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const row = wrapper.get('.m11-row')
    expect(wrapper.get('[data-testid="push-consent"]').text()).toBe('false')
    await row.get('select').setValue('on')
    expect(wrapper.get('[data-testid="push-consent"]').text()).toBe('false')
    expect(row.get('button').attributes('disabled')).toBeUndefined()
    await row.get('button').trigger('click')
    expect(mock.patchPreferences).toHaveBeenCalledWith([{
      key: 'import.completed.push', value: true,
      expected_override_epoch: '2', expected_user_revision: '0',
    }])
    expect(row.get('button').attributes('disabled')).toBeDefined()
    receipt.resolve([success])
    await flushPromises()
    expect(wrapper.get('[data-testid="push-consent"]').text()).toBe('true')
    expect(row.get('button').attributes('disabled')).toBeDefined()
  })

  it('disables locked and unavailable preferences without accidentally granting consent', async () => {
    mock.getPreferences.mockResolvedValue([
      preference('import.failed.email', {
        allow_user_override: false, locked: true, effective_source: 'SYSTEM_LOCKED',
      }),
      preference('security.permission_changed.in_app', {
        capability: 'UNAVAILABLE', locked: true, effective_source: 'CHANNEL_UNAVAILABLE',
      }),
    ])
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    for (const row of wrapper.findAll('.m11-row')) {
      expect(row.get('select').attributes('disabled')).toBeDefined()
      expect(row.get('button').attributes('disabled')).toBeDefined()
    }
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.sourceLocked'))
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.unavailable'))
    expect(mock.patchPreferences).not.toHaveBeenCalled()
  })

  it('reconciles the saved user revision, then uses it for the next CAS update', async () => {
    mock.getPreferences.mockResolvedValue([preference('import.failed.email')])
    mock.patchPreferences
      .mockResolvedValueOnce([preference('import.failed.email', {
        user_override: true, effective_value: true, user_revision: '1', effective_source: 'USER',
      })])
      .mockResolvedValueOnce([preference('import.failed.email', {
        user_override: false, effective_value: false, user_revision: '2', effective_source: 'USER',
      })])
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const row = wrapper.get('.m11-row')
    await row.get('select').setValue('on')
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.unsaved'))
    await row.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).not.toContain(i18n.global.t('notificationSettings.unsaved'))
    await row.get('select').setValue('off')
    await row.get('button').trigger('click')
    await flushPromises()
    expect(mock.patchPreferences).toHaveBeenCalledTimes(2)
    expect(mock.patchPreferences).toHaveBeenLastCalledWith([{
      key: 'import.failed.email', value: false,
      expected_override_epoch: '2', expected_user_revision: '1',
    }])
  })

  it('keeps an unsaved draft on a 409; explicit reload obtains fresh server revisions', async () => {
    mock.getPreferences.mockResolvedValueOnce([preference('import.failed.email')])
      .mockResolvedValueOnce([preference('import.failed.email', {
        user_override: false, effective_value: false, user_revision: '3', effective_source: 'USER',
      })])
    const conflict = Object.assign(new Error('conflict'), {
      isAxiosError: true, response: { status: 409 },
    })
    mock.patchPreferences.mockRejectedValue(conflict)
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const row = wrapper.get('.m11-row')
    await row.get('select').setValue('on')
    await row.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(i18n.global.t('notificationSettings.conflict'))
    expect((row.get('select').element as HTMLSelectElement).value).toBe('on')
    expect(row.get('button').attributes('disabled')).toBeUndefined()
    await wrapper.get('.m11-heading button').trigger('click')
    await flushPromises()
    expect((row.get('select').element as HTMLSelectElement).value).toBe('off')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(row.get('button').attributes('disabled')).toBeDefined()
  })

  it('ignores a response received after the authenticated session changes', async () => {
    mock.getPreferences.mockResolvedValue([preference('import.failed.email')])
    const receipt = deferred<NotificationPreference[]>()
    mock.patchPreferences.mockReturnValue(receipt.promise)
    const wrapper = mount(NotificationPreferencesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    await wrapper.get('select').setValue('on')
    await wrapper.get('.m11-row button').trigger('click')
    mock.generation = 2
    receipt.resolve([preference('import.failed.email', { user_override: true, user_revision: '1' })])
    await flushPromises()
    expect(wrapper.find('[role="status"]').text()).not.toContain(i18n.global.t('notificationSettings.saved'))
  })
})

describe('NFX-D1 system policy UX', () => {
  it('groups policies and does not let the admin enable Email/Push defaults', async () => {
    const wrapper = mount(NotificationPoliciesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.findAll('.m11-group')).toHaveLength(3)
    expect(wrapper.findAll('.m11-row')).toHaveLength(12)
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.channelHelpSystem.email'))
    for (const row of wrapper.findAll('.m11-row').slice(5)) {
      expect(row.get('input[type="checkbox"]').attributes('disabled')).toBeDefined()
      expect(row.get('button').attributes('disabled')).toBeDefined()
    }
  })

  it('saves only a changed system policy with CAS and reconciles the revision', async () => {
    const base = policy('import.failed.email')
    mock.getPolicies.mockResolvedValue([base])
    mock.patchPolicies.mockResolvedValue([policy(base.key, { allow_user_override: false, revision: '6', override_epoch: '3' })])
    const wrapper = mount(NotificationPoliciesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    const row = wrapper.get('.m11-row')
    expect(row.get('button').attributes('disabled')).toBeDefined()
    await row.findAll('input[type="checkbox"]')[1]?.setValue(false)
    expect(row.get('button').attributes('disabled')).toBeUndefined()
    await row.get('button').trigger('click')
    await flushPromises()
    expect(mock.patchPolicies).toHaveBeenCalledWith([{
      key: base.key, default_value: false, allow_user_override: false, expected_revision: '5',
    }])
    expect(row.get('button').attributes('disabled')).toBeDefined()
    // Server revision updates must be preserved for future saves.
    await row.findAll('input[type="checkbox"]')[1]?.setValue(true)
    await row.get('button').trigger('click')
    await flushPromises()
    expect(mock.patchPolicies).toHaveBeenLastCalledWith([{
      key: base.key, default_value: false, allow_user_override: true, expected_revision: '6',
    }])
  })

  it('respects read-only PBAC without sending writes', async () => {
    mock.editAllowed = false
    const wrapper = mount(NotificationPoliciesTab, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(wrapper.text()).toContain(i18n.global.t('notificationSettings.readOnly'))
    for (const row of wrapper.findAll('.m11-row')) {
      expect(row.get('button').attributes('disabled')).toBeDefined()
      for (const input of row.findAll('input')) expect(input.attributes('disabled')).toBeDefined()
    }
    expect(mock.patchPolicies).not.toHaveBeenCalled()
  })
})
