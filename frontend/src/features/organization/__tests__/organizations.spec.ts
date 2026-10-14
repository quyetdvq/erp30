import { describe, expect, it } from 'vitest'

import { allowedOrganizationChildren } from '@/modules/organizationMatrix'

describe('Organization parent matrix', () => {
  it('allows only canonical child types', () => {
    expect(allowedOrganizationChildren(null)).toEqual(['GROUP'])
    expect(allowedOrganizationChildren('GROUP')).toEqual(['COMPANY'])
    expect(allowedOrganizationChildren('COMPANY')).toEqual(['BRANCH', 'PLANT', 'WAREHOUSE'])
    expect(allowedOrganizationChildren('BRANCH')).toEqual(['PLANT', 'WAREHOUSE'])
    expect(allowedOrganizationChildren('PLANT')).toEqual(['WAREHOUSE'])
    expect(allowedOrganizationChildren('WAREHOUSE')).toEqual([])
  })

  it('rejects invalid Company-under-Company and warehouse children in the UX matrix', () => {
    expect(allowedOrganizationChildren('COMPANY')).not.toContain('COMPANY')
    expect(allowedOrganizationChildren('WAREHOUSE')).toHaveLength(0)
  })
})
