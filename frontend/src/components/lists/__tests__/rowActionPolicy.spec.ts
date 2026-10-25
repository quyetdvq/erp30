import { describe, expect, it } from 'vitest'
import { canMutateRow, canViewRow, rowAllowedActions } from '../rowActionPolicy'

const base = (permission: string) => permission === 'product.view' || permission === 'product.edit' || permission === 'product.setstate' || permission === 'resource.configure_numbering'
const defaults = {
  show: true,
  permission: 'product.edit',
  intent: 'edit' as const,
  authorizationMode: 'effective' as const,
  snapshotReady: true,
  hasBasePermission: base,
  allowedActions: { edit: true },
}

describe('M10.2 TableRowActions PBAC', () => {
  it('requires ALL base, PBAC, business and snapshot gates', () => {
    expect(canMutateRow(defaults)).toBe(true)
    expect(canMutateRow({ ...defaults, allowedActions: { edit: false } })).toBe(false)
    expect(canMutateRow({ ...defaults, permission: 'product.delete' })).toBe(false)
    expect(canMutateRow({ ...defaults, show: false })).toBe(false)
    expect(canMutateRow({ ...defaults, snapshotReady: false })).toBe(false)
    expect(canMutateRow({ ...defaults, permission: undefined })).toBe(false)
    expect(canMutateRow({ ...defaults, allowedActions: null })).toBe(false)
    expect(canMutateRow({ ...defaults, allowedActions: { setstate: true } })).toBe(false)
  })

  it('treats malformed API fields as denied and never coerces strings into permissions', () => {
    expect(rowAllowedActions({ allowed_actions: { edit: 'true', setstate: 1 } })).toEqual({})
    expect(rowAllowedActions({ allowed_actions: { edit: true, setstate: false } })).toEqual({ edit: true, setstate: false })
    expect(rowAllowedActions({ allowed_actions: null })).toBeNull()
    expect(rowAllowedActions(null)).toBeNull()
  })

  it('view needs verified scoped result, explicit permission and ready snapshot', () => {
    const input = {
      show: true, viewScopeVerified: true,
      permission: 'product.view', snapshotReady: true,
      authorizationMode: 'effective' as const, hasBasePermission: base,
    }
    expect(canViewRow(input)).toBe(true)
    expect(canViewRow({ ...input, viewScopeVerified: false })).toBe(false)
    expect(canViewRow({ ...input, permission: undefined })).toBe(false)
    expect(canViewRow({ ...input, snapshotReady: false })).toBe(false)
    expect(canViewRow({ ...input, viewActionCode: 'configure_numbering' })).toBe(false)
    expect(canViewRow({ ...input, viewActionCode: 'configure_numbering', allowedActions: { configure_numbering: true } })).toBe(true)
  })

  it('special action mapping is exact: configure_numbering is not edit/setstate', () => {
    const spec = {
      ...defaults,
      permission: 'resource.configure_numbering',
      allowedActions: { configure_numbering: true },
    }
    expect(canMutateRow(spec)).toBe(false)
    expect(canMutateRow({ ...spec, actionCodes: { edit: 'configure_numbering' as const } })).toBe(true)
    expect(canMutateRow({ ...spec, intent: 'toggle' as const, actionCodes: { toggle: 'configure_numbering' as const } })).toBe(true)
  })
})
