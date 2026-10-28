import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import {
  getNotificationRuntimeConfig,
  updateNotificationRuntimeConfig,
  type NotificationRuntimeSnapshot,
} from '@/services/notificationRuntimeConfig'
import ConfigurationSettingsView from '../ConfigurationSettingsView.vue'

const permissions = vi.hoisted(() => ({ view: true, edit: true }))

vi.mock('@/composables/useBasePermission', () => ({
  useBasePermission: () => ({
    hasBasePermission: (_resource: string, action: string) =>
      action === 'view' ? permissions.view : permissions.edit,
  }),
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ getSessionGeneration: () => 1 }),
}))
vi.mock('@/services/notificationRuntimeConfig', () => ({
  getNotificationRuntimeConfig: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  updateNotificationRuntimeConfig: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))

const keys = [
  ['DINKERP_M11_SETTINGS_WRITE_MODE', 'administration'],
  ['DINKERP_M11_IMPORT_PRODUCER_ENABLED', 'producers'],
  ['DINKERP_M11_WORKFLOW_WRITE_MODE', 'administration'],
  ['DINKERP_M11_WORKFLOW_PRODUCER_ENABLED', 'producers'],
  ['DINKERP_NOTIFICATIONS_DELIVERY_ENABLED', 'delivery'],
  ['DINKERP_M11_EMAIL_QUEUE_ENABLED', 'email'],
  ['DINKERP_M11_EMAIL_SEND_ENABLED', 'email'],
  ['DINKERP_M11_PUSH_QUEUE_ENABLED', 'push'],
  ['DINKERP_M11_PUSH_SEND_ENABLED', 'push'],
  ['DINKERP_M11_TASKS_INAPP_ENABLED', 'scheduler'],
  ['DINKERP_M11_TASKS_PUSH_ENABLED', 'scheduler'],
  ['DINKERP_M11_TASKS_EMAIL_ENABLED', 'scheduler'],
] as const

function snapshot(): NotificationRuntimeSnapshot {
  return {
    revision: '12',
    items: keys.map(([key, section]) => ({ key, section, enabled: false, source: 'database' as const })),
  }
}

async function renderPage() {
  const wrapper = mount(ConfigurationSettingsView, { global: { plugins: [i18n] } })
  await flushPromises()
  return wrapper
}

function checkboxFor(wrapper: Awaited<ReturnType<typeof renderPage>>, key: string) {
  const label = wrapper.findAll('label.flag').find((element) => element.text().includes(key))
  expect(label, `missing switch ${key}`).toBeDefined()
  return label!.get('input[type="checkbox"]')
}

beforeEach(() => {
  vi.resetAllMocks()
  permissions.view = true
  permissions.edit = true
  vi.mocked(getNotificationRuntimeConfig).mockResolvedValue(snapshot())
  vi.mocked(updateNotificationRuntimeConfig).mockImplementation(async () => snapshot())
})

describe('M11 scheduler switches in /system/configuration-settings', () => {
  it('displays separate persisted In-App and Push scheduler switches with cron guidance', async () => {
    const wrapper = await renderPage()
    expect(checkboxFor(wrapper, 'DINKERP_M11_TASKS_INAPP_ENABLED').element).toBeTruthy()
    expect(checkboxFor(wrapper, 'DINKERP_M11_TASKS_PUSH_ENABLED').element).toBeTruthy()
    expect(checkboxFor(wrapper, 'DINKERP_M11_TASKS_EMAIL_ENABLED').element).toBeTruthy()
    expect(wrapper.text()).toContain('tasks:run')
    expect(vi.mocked(updateNotificationRuntimeConfig)).not.toHaveBeenCalled()
  })

  it('sends both scheduler controls through the same CAS update without enabling Push', async () => {
    const wrapper = await renderPage()
    await checkboxFor(wrapper, 'DINKERP_M11_TASKS_INAPP_ENABLED').setValue(true)
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true)
    await wrapper.get('.actions button:nth-of-type(2)').trigger('click')
    await flushPromises()
    expect(confirm).toHaveBeenCalledTimes(1)
    expect(updateNotificationRuntimeConfig).toHaveBeenCalledWith('12', expect.arrayContaining([
      { key: 'DINKERP_M11_TASKS_INAPP_ENABLED', enabled: true },
      { key: 'DINKERP_M11_TASKS_PUSH_ENABLED', enabled: false },
      { key: 'DINKERP_M11_TASKS_EMAIL_ENABLED', enabled: false },
    ]))
    confirm.mockRestore()
  })

  it('requires confirmation before registering automatic Push delivery', async () => {
    const wrapper = await renderPage()
    await checkboxFor(wrapper, 'DINKERP_M11_TASKS_PUSH_ENABLED').setValue(true)
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    await wrapper.get('.actions button:nth-of-type(2)').trigger('click')
    expect(updateNotificationRuntimeConfig).not.toHaveBeenCalled()
    confirm.mockRestore()
  })

  it('rejects automatic Email scheduling without operator confirmation', async () => {
    const wrapper = await renderPage()
    await checkboxFor(wrapper, 'DINKERP_M11_TASKS_EMAIL_ENABLED').setValue(true)
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    await wrapper.get('.actions button:nth-of-type(2)').trigger('click')
    expect(confirm).toHaveBeenCalledTimes(1)
    expect(updateNotificationRuntimeConfig).not.toHaveBeenCalled()
    confirm.mockRestore()
  })

  it('never permits modification when the user only has notification_setting.view', async () => {
    permissions.edit = false
    const wrapper = await renderPage()
    expect(checkboxFor(wrapper, 'DINKERP_M11_TASKS_INAPP_ENABLED').attributes('disabled')).toBeDefined()
    expect(checkboxFor(wrapper, 'DINKERP_M11_TASKS_PUSH_ENABLED').attributes('disabled')).toBeDefined()
    expect(checkboxFor(wrapper, 'DINKERP_M11_TASKS_EMAIL_ENABLED').attributes('disabled')).toBeDefined()
    expect(wrapper.get('.actions button:nth-of-type(2)').attributes('disabled')).toBeDefined()
  })
})
