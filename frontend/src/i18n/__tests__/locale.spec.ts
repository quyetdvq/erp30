import { describe, expect, it } from 'vitest'

import { messages, supportedLocales } from '../index'

function leafKeys(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object') return [prefix]

  return Object.entries(value).flatMap(([key, child]) =>
    leafKeys(child, prefix ? `${prefix}.${key}` : key),
  )
}

describe('locale resources', () => {
  it('keeps vi and en translation key trees in parity', () => {
    const [vi, en] = supportedLocales
    expect(new Set(leafKeys(messages[vi]))).toEqual(new Set(leafKeys(messages[en])))
  })
})
