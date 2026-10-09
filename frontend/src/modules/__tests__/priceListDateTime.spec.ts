import { describe, expect, it } from 'vitest'
import { toApiDateTime, toDateTimeLocal } from '@/modules/priceListDateTime'

describe('price list date-time conversion', () => {
  it('maps SQL timestamps returned by the API to datetime-local values', () => {
    expect(toDateTimeLocal('2026-10-01 00:00:00')).toBe('2026-10-01T00:00')
  })

  it('normalizes RFC3339 timestamps with offsets to UTC input values', () => {
    expect(toDateTimeLocal('2026-10-01T03:00:00+03:00')).toBe('2026-10-01T00:00')
  })

  it('returns an empty input value for malformed timestamps', () => {
    expect(toDateTimeLocal('2026-10-01 00:')).toBe('')
  })

  it('creates the canonical RFC3339 payload from a datetime-local value', () => {
    expect(toApiDateTime('2026-10-01T00:00')).toBe('2026-10-01T00:00:00Z')
    expect(toApiDateTime('2026-10-01 00:00')).toBe('')
  })
})
