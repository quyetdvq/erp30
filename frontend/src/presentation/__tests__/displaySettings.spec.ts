import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { client } from '@/api/generated/client.gen'
import { getDisplaySettingsRuntime } from '@/api/generated/sdk.gen'
import { i18n } from '@/i18n'
import {
  DEFAULT_RUNTIME,
  DISPLAY_SETTING_KEYS,
  DISPLAY_SETTINGS_STORAGE_PREFIX,
} from '@/services/displaySettings'
import { apiClient } from '@/services/api'
import { useDisplaySettingsStore } from '@/stores/displaySettings'
import {
  formatDate,
  formatDateTime,
  formatMoney,
  formatPercentage,
  formatQuantity,
  formatTime,
} from '@/presentation/formatters'

vi.mock('@/api/generated/sdk.gen', () => ({
  getDisplaySettingsRuntime: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))

const mockedRuntime = vi.mocked(getDisplaySettingsRuntime)

const runtime = {
  ...DEFAULT_RUNTIME,
  timezone: 'Asia/Ho_Chi_Minh',
  date: { ...DEFAULT_RUNTIME.date, format: 'DD/MM/YYYY', date_time_format: 'DD/MM/YYYY HH:mm' },
  number: {
    ...DEFAULT_RUNTIME.number,
    quantity_decimals: 3,
    percentage_decimals: 2,
    trailing_zeros: false,
  },
  money: { ...DEFAULT_RUNTIME.money, decimals_mode: 'CURRENCY_DEFAULT' },
}

describe('Display Settings runtime foundation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    window.localStorage.clear()
    mockedRuntime.mockReset()
    mockedRuntime.mockResolvedValue({ data: { data: runtime } } as never)
  })

  it('loads through the generated client and overlays all allowlisted settings', async () => {
    const store = useDisplaySettingsStore()
    expect(DISPLAY_SETTING_KEYS).toHaveLength(15)
    expect(client.getConfig().axios).toBe(apiClient)

    await store.load(7, '42')
    expect(mockedRuntime).toHaveBeenCalledWith(
      expect.objectContaining({
        query: { organization_id: '42' },
        throwOnError: true,
      }),
    )
    expect(store.serverRuntime.timezone).toBe('Asia/Ho_Chi_Minh')

    store.setLocalPreference('Display.timezone', 'UTC')
    store.setLocalPreference('Display.quantityDecimals', 1)
    expect(store.effectiveRuntime.timezone).toBe('UTC')
    expect(store.effectiveRuntime.number.quantity_decimals).toBe(1)
    expect(store.serverRuntime.number.quantity_decimals).toBe(3)
    expect(
      JSON.parse(window.localStorage.getItem(`${DISPLAY_SETTINGS_STORAGE_PREFIX}7`)!),
    ).toMatchObject({
      'Display.timezone': 'UTC',
      'Display.quantityDecimals': 1,
    })

    const allPreferences = {
      'Display.timezone': 'UTC',
      'Display.dateFormat': 'YYYY-MM-DD',
      'Display.dateTimeFormat': 'YYYY-MM-DD HH:mm:ss',
      'Display.timeFormat': 'HH:mm:ss',
      'Display.firstDayOfWeek': 'SUNDAY',
      'Display.number.decimalSeparator': ',',
      'Display.number.groupSeparator': '.',
      'Display.quantityDecimals': 4,
      'Display.percentageDecimals': 2,
      'Display.trailingZeros': true,
      'Display.money.decimalSeparator': ',',
      'Display.money.groupSeparator': '.',
      'Display.money.symbolMode': 'SYMBOL',
      'Display.money.symbolPosition': 'SUFFIX',
      'Display.money.decimalsMode': 'FIXED_2',
    } as const
    for (const key of DISPLAY_SETTING_KEYS) store.setLocalPreference(key, allPreferences[key])
    expect(Object.keys(store.localPreferences)).toHaveLength(15)
    expect(
      Object.keys(JSON.parse(window.localStorage.getItem(`${DISPLAY_SETTINGS_STORAGE_PREFIX}7`)!)),
    ).toHaveLength(15)
  })

  it('isolates user namespaces and clears active state on session change', () => {
    const store = useDisplaySettingsStore()
    store.activateUser(7)
    store.setLocalPreference('Display.dateFormat', 'YYYY-MM-DD')
    store.activateUser(8)
    expect(store.localPreferences).toEqual({})
    expect(store.effectiveRuntime.date.format).toBe('DD/MM/YYYY')

    store.clearSession()
    expect(store.activeUserId).toBeNull()
    expect(store.localPreferences).toEqual({})

    store.activateUser(7)
    expect(store.effectiveRuntime.date.format).toBe('YYYY-MM-DD')
  })

  it('formats presentation text without mutating canonical values or using business timezone', () => {
    const canonicalQuantity = '1234.56789'
    expect(formatQuantity(canonicalQuantity, runtime)).toBe('1,234.568')
    expect(canonicalQuantity).toBe('1234.56789')
    expect(formatPercentage('12.345', runtime)).toBe('12.35%')
    expect(formatMoney('1234.5', runtime)).toBe('1,234.5')
    expect(formatDate('2026-01-01', runtime)).toBe('01/01/2026')
    expect(formatDateTime('2026-01-01T00:00:00Z', runtime)).toBe('01/01/2026 07:00')
    expect(formatTime('12:34:56', runtime)).toBe('12:34')

    const otherTimezone = { ...runtime, timezone: 'America/New_York' }
    expect(formatDate('2026-01-01', otherTimezone)).toBe('01/01/2026')
    expect(formatDateTime('2026-01-01T00:00:00Z', otherTimezone)).toBe('31/12/2025 19:00')
    expect(i18n.global.locale.value).toBe('vi')
    expect(window.localStorage.getItem('ui.locale')).toBeNull()
  })
})
