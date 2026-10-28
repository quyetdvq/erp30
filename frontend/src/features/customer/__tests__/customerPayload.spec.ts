import { describe, expect, it } from 'vitest'
import { buildCustomerPayload } from '@/features/customer/customerPayload'
import { newCustomerForm } from '@/features/customer/customerDefaults'
import type { CustomerForm } from '@/services/customer'

const baseline: CustomerForm = {
  customer_code: '', name: 'ABC', customer_type: 'ORGANIZATION', customer_group_code: null,
  phone: null, email: null, tax_code: null, address: null,
  province_id: null, ward_id: null, primary_contact_id: null,
  all_companies: true, company_ids: [],
}

describe('CUS: Customer code with P1 Numbering', () => {
  it('omits an empty code on POST so the server can issue one', () => {
    expect(buildCustomerPayload(baseline, true)).not.toHaveProperty('customer_code')
  })
  it('also omits a whitespace-only code on POST', () => {
    expect(buildCustomerPayload({ ...baseline, customer_code: '   ' }, true)).not.toHaveProperty('customer_code')
  })
  it('retains a trimmed manual code for AUTO_IF_EMPTY mode', () => {
    expect(buildCustomerPayload({ ...baseline, customer_code: ' KH002 ' }, true)).toMatchObject({ customer_code: 'KH002' })
  })
  it('never attempts to update a Customer code', () => {
    expect(buildCustomerPayload({ ...baseline, customer_code: 'KH002' }, false)).not.toHaveProperty('customer_code')
  })
})


describe('CUS: new Customer defaults and individual Contact initialization', () => {
  it('starts a fresh form as INDIVIDUAL / STANDARD', () => {
    const form = newCustomerForm()
    expect(form.customer_type).toBe('INDIVIDUAL')
    expect(form.customer_group_code).toBe('STANDARD')
    expect(form.all_companies).toBe(true)
    expect(form.customer_code).toBe('')
  })
  it('submits explicit defaults while leaving Customer code to Numbering', () => {
    const payload = buildCustomerPayload({ ...newCustomerForm(), name: 'Nguyễn Văn A' }, true)
    expect(payload).toMatchObject({
      customer_type: 'INDIVIDUAL', customer_group_code: 'STANDARD', name: 'Nguyễn Văn A',
    })
    expect(payload).not.toHaveProperty('customer_code')
  })
  it('does not override selected Organization or other Customer group', () => {
    const payload = buildCustomerPayload({
      ...newCustomerForm(), name: 'Company A', customer_type: 'ORGANIZATION', customer_group_code: 'VIP2',
    }, true)
    expect(payload).toMatchObject({ customer_type: 'ORGANIZATION', customer_group_code: 'VIP2' })
  })
  it('allows intentional NULL group without restoring STANDARD', () => {
    const payload = buildCustomerPayload({ ...newCustomerForm(), name: 'Không nhóm', customer_group_code: null }, true)
    expect(payload.customer_group_code).toBeNull()
  })
  it('creates a fresh Company allowlist for every new form', () => {
    const first = newCustomerForm()
    first.company_ids.push('17')
    expect(newCustomerForm().company_ids).toEqual([])
  })
})
