import type { DisplaySettingsRuntime } from '@/api/generated/types.gen'

export type FormatterSettings = Pick<
  DisplaySettingsRuntime,
  'timezone' | 'date' | 'number' | 'money'
>

type DateParts = {
  year: string
  month: string
  day: string
  hour: string
  minute: string
  second: string
}

const DECIMAL_PATTERN = /^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?$/

function datePartsFromInstant(value: string, timezone: string): DateParts | null {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    }).formatToParts(date)
    const result = Object.fromEntries(parts.map((part) => [part.type, part.value]))
    return {
      year: result.year ?? '',
      month: result.month ?? '',
      day: result.day ?? '',
      hour: result.hour ?? '',
      minute: result.minute ?? '',
      second: result.second ?? '',
    }
  } catch {
    return null
  }
}

function datePartsFromDate(value: string): DateParts | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (!match) return null
  return {
    year: match[1]!,
    month: match[2]!,
    day: match[3]!,
    hour: '00',
    minute: '00',
    second: '00',
  }
}

function datePartsFromTime(value: string): DateParts | null {
  const match = /^(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value)
  if (!match) return null
  return {
    year: '',
    month: '',
    day: '',
    hour: match[1]!,
    minute: match[2]!,
    second: match[3] ?? '00',
  }
}

function applyPattern(pattern: string, parts: DateParts): string {
  return pattern.replace(/YYYY|MM|DD|HH|mm|ss|M|D/g, (token) => {
    switch (token) {
      case 'YYYY':
        return parts.year
      case 'MM':
        return parts.month
      case 'DD':
        return parts.day
      case 'HH':
        return parts.hour
      case 'mm':
        return parts.minute
      case 'ss':
        return parts.second
      case 'M':
        return parts.month.replace(/^0/, '') || '0'
      case 'D':
        return parts.day.replace(/^0/, '') || '0'
      default:
        return token
    }
  })
}

function decimalParts(value: string): { sign: string; integer: string; fraction: string } | null {
  const match = DECIMAL_PATTERN.exec(value)
  if (!match) return null
  return { sign: match[1]!, integer: match[2]!, fraction: match[3] ?? '' }
}

function incrementInteger(value: string): string {
  const digits = value.split('')
  let index = digits.length - 1
  while (index >= 0 && digits[index] === '9') {
    digits[index] = '0'
    index -= 1
  }
  if (index < 0) return `1${digits.join('')}`
  const nextDigit = '0123456789'['0123456789'.indexOf(digits[index]!) + 1]
  digits[index] = nextDigit ?? '0'
  return digits.join('')
}

function roundDecimal(
  value: string,
  decimals: number,
): { sign: string; integer: string; fraction: string } | null {
  const parsed = decimalParts(value)
  if (!parsed) return null
  const fraction = parsed.fraction
  let integer = parsed.integer
  let roundedFraction = fraction.slice(0, decimals).padEnd(decimals, '0')
  if (fraction.length > decimals && fraction[decimals]! >= '5') {
    const roundedDigits = `${integer}${roundedFraction}`.split('')
    let index = roundedDigits.length - 1
    while (index >= 0 && roundedDigits[index] === '9') {
      roundedDigits[index] = '0'
      index -= 1
    }
    if (index < 0) {
      integer = incrementInteger(integer)
      roundedFraction = '0'.repeat(decimals)
    } else {
      const nextDigit = '0123456789'['0123456789'.indexOf(roundedDigits[index]!) + 1]
      roundedDigits[index] = nextDigit ?? '0'
      const combined = roundedDigits.join('')
      integer = combined.slice(0, -decimals || undefined) || '0'
      roundedFraction = decimals > 0 ? combined.slice(-decimals) : ''
    }
  }
  return { sign: parsed.sign, integer, fraction: roundedFraction }
}

function formatDecimal(
  value: string,
  decimals: number,
  decimalSeparator: string,
  groupSeparator: string,
  trailingZeros: boolean,
): string {
  const rounded = roundDecimal(value, decimals)
  if (!rounded) return value
  const grouped = rounded.integer.replace(/\B(?=(\d{3})+(?!\d))/g, groupSeparator)
  const fraction = trailingZeros ? rounded.fraction : rounded.fraction.replace(/0+$/, '')
  return `${rounded.sign}${grouped}${fraction ? decimalSeparator + fraction : ''}`
}

function moneyDecimals(mode: string): number {
  if (mode === 'INTEGER' || mode === 'NO_DECIMALS') return 0
  const explicit = /(?:DECIMAL|FIXED|SCALE)_([0-9]+)$/.exec(mode)
  if (explicit) return Number(explicit[1])
  return 2
}

export function formatDate(value: string, runtime: FormatterSettings): string {
  const parts = datePartsFromDate(value)
  return parts ? applyPattern(runtime.date.format, parts) : value
}

export function formatDateTime(value: string, runtime: FormatterSettings): string {
  const parts = datePartsFromInstant(value, runtime.timezone)
  return parts ? applyPattern(runtime.date.date_time_format, parts) : value
}

export function formatTime(value: string, runtime: FormatterSettings): string {
  const parts =
    value.includes('T') || value.endsWith('Z')
      ? datePartsFromInstant(value, runtime.timezone)
      : datePartsFromTime(value)
  return parts ? applyPattern(runtime.date.time_format, parts) : value
}

export function formatQuantity(value: string, runtime: FormatterSettings): string {
  return formatDecimal(
    value,
    runtime.number.quantity_decimals,
    runtime.number.decimal_separator,
    runtime.number.group_separator,
    runtime.number.trailing_zeros,
  )
}

export function formatPercentage(value: string, runtime: FormatterSettings): string {
  return `${formatDecimal(
    value,
    runtime.number.percentage_decimals,
    runtime.number.decimal_separator,
    runtime.number.group_separator,
    runtime.number.trailing_zeros,
  )}%`
}

export function formatMoney(
  value: string,
  runtime: FormatterSettings,
  currencySymbol = '',
): string {
  const formatted = formatDecimal(
    value,
    moneyDecimals(runtime.money.decimals_mode),
    runtime.money.decimal_separator,
    runtime.money.group_separator,
    runtime.number.trailing_zeros,
  )
  if (!currencySymbol || runtime.money.symbol_mode === 'NONE') return formatted
  return runtime.money.symbol_position === 'SUFFIX'
    ? `${formatted} ${currencySymbol}`
    : `${currencySymbol} ${formatted}`
}
