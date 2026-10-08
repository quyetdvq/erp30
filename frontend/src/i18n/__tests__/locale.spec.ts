import { describe, expect, it } from 'vitest'

import { messages, supportedLocales } from '../index'

const routedLabelKeys = [
  'nav.dashboard',
  'shell.profile',
  'shell.displayPreferences',
  'nav.displaySettings',
  'nav.securityUsers',
  'nav.authorizationGroups',
  'nav.resources',
  'nav.policies',
  'nav.authorizationLogs',
  'nav.workflowStates',
  'nav.lifecycles',
  'nav.autoNumbering',
  'nav.productGroups',
  'nav.productUoms',
  'nav.productPricePolicies',
  'nav.productImport',
  'nav.productImportJobDetail',
  'nav.productImportJobs',
  'nav.productCreate',
  'nav.productEdit',
  'nav.products',
  'nav.organizations',
  'nav.locations',
  'nav.departments',
  'nav.positions',
  'nav.employees',
  'errors.status.404',
] as const

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

  it('resolves every routed menu label through both locale resources', () => {
    for (const labelKey of routedLabelKeys) {
      const viValue = labelKey.split('.').reduce<unknown>((value, key) => {
        return value && typeof value === 'object'
          ? (value as Record<string, unknown>)[key]
          : undefined
      }, messages.vi)
      const enValue = labelKey.split('.').reduce<unknown>((value, key) => {
        return value && typeof value === 'object'
          ? (value as Record<string, unknown>)[key]
          : undefined
      }, messages.en)

      expect(typeof viValue).toBe('string')
      expect(typeof enValue).toBe('string')
      expect(viValue).not.toBe(labelKey)
      expect(enValue).not.toBe(labelKey)
    }
  })

  it('keeps buttons, field labels, and validation messages translated in vi and en', () => {
    for (const locale of supportedLocales) {
      for (const key of leafKeys(messages[locale])) {
        const value = key.split('.').reduce<unknown>((current, segment) => {
          return current && typeof current === 'object'
            ? (current as Record<string, unknown>)[segment]
            : undefined
        }, messages[locale])

        expect(typeof value).toBe('string')
        expect(String(value).trim().length).toBeGreaterThan(0)
        expect(value).not.toBe(key)
      }
    }
  })
})
