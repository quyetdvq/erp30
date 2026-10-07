import { getDisplaySettingsRuntime } from '@/api/generated/sdk.gen'
import { client } from '@/api/generated/client.gen'
import type { DisplaySettingsRuntime } from '@/api/generated/types.gen'
import { i18n } from '@/i18n'
import { apiClient } from '@/services/api'

export const DISPLAY_SETTING_KEYS = [
  'Display.timezone',
  'Display.dateFormat',
  'Display.dateTimeFormat',
  'Display.timeFormat',
  'Display.firstDayOfWeek',
  'Display.number.decimalSeparator',
  'Display.number.groupSeparator',
  'Display.quantityDecimals',
  'Display.percentageDecimals',
  'Display.trailingZeros',
  'Display.money.decimalSeparator',
  'Display.money.groupSeparator',
  'Display.money.symbolMode',
  'Display.money.symbolPosition',
  'Display.money.decimalsMode',
] as const

export type DisplaySettingKey = (typeof DISPLAY_SETTING_KEYS)[number]
export type DisplaySettingValue = string | number | boolean
export type DisplaySettingValues = Record<DisplaySettingKey, DisplaySettingValue>
export type LocalDisplayPreferences = Partial<DisplaySettingValues>

export const DISPLAY_SETTING_DEFAULTS: DisplaySettingValues = {
  'Display.timezone': 'Asia/Ho_Chi_Minh',
  'Display.dateFormat': 'DD/MM/YYYY',
  'Display.dateTimeFormat': 'DD/MM/YYYY HH:mm',
  'Display.timeFormat': 'HH:mm',
  'Display.firstDayOfWeek': 'MONDAY',
  'Display.number.decimalSeparator': '.',
  'Display.number.groupSeparator': ',',
  'Display.quantityDecimals': 3,
  'Display.percentageDecimals': 3,
  'Display.trailingZeros': false,
  'Display.money.decimalSeparator': '.',
  'Display.money.groupSeparator': ',',
  'Display.money.symbolMode': 'SYMBOL',
  'Display.money.symbolPosition': 'AUTO',
  'Display.money.decimalsMode': 'CURRENCY_DEFAULT',
}

export const DISPLAY_SETTINGS_STORAGE_PREFIX = 'ui.presentation.'

client.setConfig({
  axios: apiClient,
  headers: {
    Accept: 'application/json',
  },
})

export function storageKey(userId: number | string): string {
  const normalized = String(userId).trim()
  if (!/^[1-9][0-9]*$/.test(normalized)) throw new Error('Invalid authenticated user id.')
  return `${DISPLAY_SETTINGS_STORAGE_PREFIX}${normalized}`
}

function localStorageOrNull(): Storage | null {
  if (typeof window === 'undefined') return null
  try {
    return window.localStorage
  } catch {
    return null
  }
}

function isValidTimezone(value: string): boolean {
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: value }).format()
    return true
  } catch {
    return false
  }
}

export function isValidDisplaySettingValue(
  key: DisplaySettingKey,
  value: unknown,
): value is DisplaySettingValue {
  switch (key) {
    case 'Display.timezone':
      return typeof value === 'string' && isValidTimezone(value)
    case 'Display.dateFormat':
    case 'Display.dateTimeFormat':
    case 'Display.timeFormat':
      return typeof value === 'string' && value.length > 0
    case 'Display.firstDayOfWeek':
      return typeof value === 'string' && /^[A-Z][A-Z0-9_]*$/.test(value)
    case 'Display.number.decimalSeparator':
    case 'Display.number.groupSeparator':
    case 'Display.money.decimalSeparator':
    case 'Display.money.groupSeparator':
      return typeof value === 'string' && value.length === 1
    case 'Display.quantityDecimals':
    case 'Display.percentageDecimals':
      return typeof value === 'number' && Number.isInteger(value) && value >= 0
    case 'Display.trailingZeros':
      return typeof value === 'boolean'
    case 'Display.money.symbolMode':
    case 'Display.money.symbolPosition':
    case 'Display.money.decimalsMode':
      return typeof value === 'string' && /^[A-Z][A-Z0-9_]*$/.test(value)
  }
}

export function readLocalPreferences(userId: number | string): LocalDisplayPreferences {
  const storage = localStorageOrNull()
  if (!storage) return {}

  try {
    const raw = storage.getItem(storageKey(userId))
    if (!raw) return {}
    const parsed: unknown = JSON.parse(raw)
    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) return {}

    const preferences: LocalDisplayPreferences = {}
    for (const key of DISPLAY_SETTING_KEYS) {
      const value = (parsed as Record<string, unknown>)[key]
      if (value !== undefined && isValidDisplaySettingValue(key, value)) preferences[key] = value
    }
    return preferences
  } catch {
    return {}
  }
}

export function writeLocalPreferences(
  userId: number | string,
  preferences: LocalDisplayPreferences,
): void {
  const storage = localStorageOrNull()
  if (!storage) return

  const sanitized: LocalDisplayPreferences = {}
  for (const key of DISPLAY_SETTING_KEYS) {
    const value = preferences[key]
    if (value !== undefined && isValidDisplaySettingValue(key, value)) sanitized[key] = value
  }
  try {
    storage.setItem(storageKey(userId), JSON.stringify(sanitized))
  } catch {
    // Browser storage may be disabled or full; the in-memory overlay still works.
  }
}

export function runtimeToValues(runtime: DisplaySettingsRuntime): DisplaySettingValues {
  return {
    'Display.timezone': runtime.timezone,
    'Display.dateFormat': runtime.date.format,
    'Display.dateTimeFormat': runtime.date.date_time_format,
    'Display.timeFormat': runtime.date.time_format,
    'Display.firstDayOfWeek': runtime.date.first_day_of_week,
    'Display.number.decimalSeparator': runtime.number.decimal_separator,
    'Display.number.groupSeparator': runtime.number.group_separator,
    'Display.quantityDecimals': runtime.number.quantity_decimals,
    'Display.percentageDecimals': runtime.number.percentage_decimals,
    'Display.trailingZeros': runtime.number.trailing_zeros,
    'Display.money.decimalSeparator': runtime.money.decimal_separator,
    'Display.money.groupSeparator': runtime.money.group_separator,
    'Display.money.symbolMode': runtime.money.symbol_mode,
    'Display.money.symbolPosition': runtime.money.symbol_position,
    'Display.money.decimalsMode': runtime.money.decimals_mode,
  }
}

export function valuesToRuntime(values: DisplaySettingValues): DisplaySettingsRuntime {
  return {
    timezone: values['Display.timezone'] as string,
    date: {
      format: values['Display.dateFormat'] as string,
      date_time_format: values['Display.dateTimeFormat'] as string,
      time_format: values['Display.timeFormat'] as string,
      first_day_of_week: values['Display.firstDayOfWeek'] as string,
    },
    number: {
      decimal_separator: values['Display.number.decimalSeparator'] as string,
      group_separator: values['Display.number.groupSeparator'] as string,
      quantity_decimals: values['Display.quantityDecimals'] as number,
      percentage_decimals: values['Display.percentageDecimals'] as number,
      trailing_zeros: values['Display.trailingZeros'] as boolean,
    },
    money: {
      decimal_separator: values['Display.money.decimalSeparator'] as string,
      group_separator: values['Display.money.groupSeparator'] as string,
      symbol_mode: values['Display.money.symbolMode'] as string,
      symbol_position: values['Display.money.symbolPosition'] as string,
      decimals_mode: values['Display.money.decimalsMode'] as string,
    },
  }
}

export const DEFAULT_RUNTIME = valuesToRuntime(DISPLAY_SETTING_DEFAULTS)

export function mergeRuntime(runtime: DisplaySettingsRuntime | undefined): DisplaySettingsRuntime {
  if (!runtime) return valuesToRuntime(DISPLAY_SETTING_DEFAULTS)
  return valuesToRuntime({ ...DISPLAY_SETTING_DEFAULTS, ...runtimeToValues(runtime) })
}

export async function fetchDisplaySettingsRuntime(
  organizationId?: number | string,
): Promise<DisplaySettingsRuntime> {
  const response = await getDisplaySettingsRuntime({
    client,
    headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
    query: organizationId === undefined ? undefined : { organization_id: String(organizationId) },
    throwOnError: true,
  })
  return mergeRuntime(response.data.data)
}
