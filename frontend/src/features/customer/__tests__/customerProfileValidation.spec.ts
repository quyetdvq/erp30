import { describe, expect, it } from 'vitest'
import { customerPhoneKey, validateCustomerProfile } from '@/features/customer/customerProfileValidation'
import { newCustomerForm } from '@/features/customer/customerDefaults'

describe('Customer required profile and duplicate-phone canonicalization', () => {
  it('rejects empty mandatory fields on a new Customer of either type', () => {
    for (const customer_type of ['INDIVIDUAL', 'ORGANIZATION'] as const) {
      expect(validateCustomerProfile({ ...newCustomerForm(), name: 'New', customer_type })).toEqual({
        phone: 'required', address: 'required', province_id: 'required', ward_id: 'required',
      })
    }
  })
  it('accepts valid full profile', () => {
    expect(validateCustomerProfile({ ...newCustomerForm(), name: 'Khách A', phone: '0912 345 678',
      address: '12 Phố Huế', province_id: '1', ward_id: '2' })).toEqual({})
  })
  it('rejects invalid phone and whitespace-only address', () => {
    expect(validateCustomerProfile({ ...newCustomerForm(), name: 'A', phone: 'abc', address: '  ',
      province_id: '1', ward_id: '2' })).toMatchObject({ phone: 'invalid', address: 'required' })
  })
  it('normalizes common formatting and Vietnamese prefixes consistently', () => {
    for (const phone of ['0912345678', '0912 345 678', '(0912)-345.678',
      '+84 912 345 678', '0084-912-345-678', '84 912345678']) {
      expect(customerPhoneKey(phone)).toBe('0912345678')
    }
    expect(customerPhoneKey('123456')).toBeNull()
    expect(customerPhoneKey('abc')).toBeNull()
    expect(customerPhoneKey('')).toBeNull()
  })
})
