import type { CustomerForm } from '@/services/customer'

/** POST: blank code delegates to the canonical backend Numbering issuer. */
export function buildCustomerPayload(form: CustomerForm, creating: boolean): Record<string, unknown> {
  const payload: Record<string, unknown> = { ...form }
  if (!creating) {
    delete payload.customer_code
  } else if (typeof form.customer_code !== 'string' || form.customer_code.trim() === '') {
    delete payload.customer_code
  } else {
    payload.customer_code = form.customer_code.trim()
  }
  return payload
}
