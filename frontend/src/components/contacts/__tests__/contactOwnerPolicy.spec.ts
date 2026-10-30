import { describe, expect, it } from 'vitest'
import { contactOwnerResource } from '@/components/contacts/contactOwnerPolicy'

describe('SUP-T06B1: canonical Shared Contact owner resource mapping', () => {
  it.each([
    ['ORGANIZATION', 'organization_contact'],
    ['EMPLOYEE', 'employee_contact'],
    ['CUSTOMER', 'customer_contact'],
    ['SUPPLIER', 'supplier_contact'],
  ])('maps %s to exact PBAC resource %s', (owner, resource) => {
    expect(contactOwnerResource(owner)).toBe(resource)
  })

  it.each(['', 'contact', 'SUPPLIER_CONTACT', 'supplier', 'Supplier', ' EMPLOYEE', 'UNKNOWN', 'SUPPLIER '])(
    'rejects unknown/noncanonical owner %s without an employee fallback',
    (owner) => {
      expect(contactOwnerResource(owner)).toBe('')
    },
  )

  it('rejects null or missing owner without any permission fallback', () => {
    expect(contactOwnerResource(null)).toBe('')
    expect(contactOwnerResource(undefined)).toBe('')
  })
})
