import type { CustomerForm } from '@/services/customer'

/** Business-approved defaults for POST only; existing Customer details are never overwritten. */
export function newCustomerForm(): CustomerForm {
  return {
    customer_code: '',
    name: '',
    customer_type: 'INDIVIDUAL',
    customer_group_code: 'STANDARD',
    phone: null,
    email: null,
    tax_code: null,
    address: null,
    province_id: null,
    ward_id: null,
    primary_contact_id: null,
    all_companies: true,
    company_ids: [],
  }
}
