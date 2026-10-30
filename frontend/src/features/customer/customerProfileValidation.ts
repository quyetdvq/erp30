import type { CustomerForm } from '@/services/customer'

export type CustomerProfileField = 'name' | 'phone' | 'address' | 'province_id' | 'ward_id'
export type CustomerProfileErrors = Partial<Record<CustomerProfileField, 'required' | 'invalid'>>

/** Must remain equivalent to CustomerPhone::key() in the backend. */
export function customerPhoneKey(phone: string | null): string | null {
  if (!phone) return null
  const compact = phone.trim().replace(/[\s().-]+/gu, '')
  if (!/^\+?[0-9]{7,15}$/.test(compact)) return null
  for (const prefix of ['+84', '0084', '84']) {
    if (compact.startsWith(prefix) && compact.length === prefix.length + 9) {
      return `0${compact.slice(prefix.length)}`
    }
  }
  return compact
}

/** Prevent a request with an incomplete profile; the server remains authoritative. */
export function validateCustomerProfile(form: CustomerForm): CustomerProfileErrors {
  const errors: CustomerProfileErrors = {}
  if (!form.name?.trim()) errors.name = 'required'
  if (!form.phone?.trim()) errors.phone = 'required'
  else if (!customerPhoneKey(form.phone)) errors.phone = 'invalid'
  if (!form.address?.trim()) errors.address = 'required'
  if (!form.province_id) errors.province_id = 'required'
  if (!form.ward_id) errors.ward_id = 'required'
  return errors
}
