import { apiClient } from '@/services/api'
import type { ListQuery } from '@/components/lists/listTypes'

export type Customer = {
  id: string
  customer_code: string
  name: string
  customer_type: 'INDIVIDUAL' | 'ORGANIZATION'
  customer_group_code: 'STANDARD' | 'TMDT' | 'VIP1' | 'VIP2' | null
  phone: string | null
  email: string | null
  tax_code: string | null
  address: string | null
  province_id: string | null
  ward_id: string | null
  primary_contact_id: string | null
  all_companies: 0 | 1 | boolean
  company_ids?: string[]
  /** Canonical State of the Customer Resource's currently configured Lifecycle. */
  status: string
  allowed_actions?: { edit?: boolean; setstate?: boolean }
  created_at: string
}
export type CustomerForm = {
  customer_code?: string
  numbering_series_code?: string
  name: string
  customer_type: Customer['customer_type']
  customer_group_code: Customer['customer_group_code']
  phone: string | null
  email: string | null
  tax_code: string | null
  address: string | null
  province_id: string | null
  ward_id: string | null
  primary_contact_id: string | null
  all_companies: boolean
  company_ids: string[]
}
export type Interaction = {
  id: string
  customer_id: string
  company_organization_id: string
  activity_type: 'NOTE' | 'CALL' | 'EMAIL' | 'MEETING' | 'CORRECTION'
  content: string
  corrects_interaction_id: string | null
  happened_at: string
  created_by: string
}
export type CustomerContact = {
  id: string
  contact_type: string
  contact_ref_id: string
  description?: string | null
  name: string | null
  phone: string | null
  mobile: string | null
  email: string | null
  address: string | null
  province_id: string | null
  ward_id: string | null
  /** Optional persisted P1 Contact address labels; canonical fallback uses location detail APIs. */
  province_name?: string | null
  ward_name?: string | null
  status: string
  allowed_actions?: { edit?: boolean; setstate?: boolean }
}
export type Company = { id: string; name: string; code: string; type: string; status: string }
export type Location = { id: string; name: string; code: string; province_id?: string; name_full?: string | null }
export type Page<T> = { data: T[]; meta: { page: number; page_size: number; total: number } }
export const CUSTOMER_GROUPS = [
  { value: 'STANDARD', label: 'Tiêu chuẩn' },
  { value: 'TMDT', label: 'Thương mại điện tử' },
  { value: 'VIP1', label: 'Vip 1' },
  { value: 'VIP2', label: 'Vip 2' },
] as const

export async function customerPage(params: Record<string, string | number>): Promise<Page<Customer>> {
  const response = await apiClient.get<Page<Customer>>('/api/v1/customers', { params })
  return response.data
}
/** The CUS API consumes flat whitelisted filters, unlike the generic P1 list transport. */
const CUSTOMER_SORT_WHITELIST = new Set([
  'name', '-name', 'customer_code', '-customer_code', 'id', '-id',
])

export function listCustomerPage(query: ListQuery): Promise<Page<Customer>> {
  // The CUS backend rejects compound and unknown sort expressions with 400.
  // Only issue a sort authorized by its actual CustomerSql whitelist.
  const sort = CUSTOMER_SORT_WHITELIST.has(query.sort) ? query.sort : 'name'
  const params: Record<string, string | number> = {
    page: query.page, page_size: query.page_size, q: query.q, sort,
  }
  for (const key of ['status', 'customer_type', 'customer_group_code', 'company_id'] as const) {
    const value = query.filter[key]
    if (typeof value === 'string' && value) params[key] = value
  }
  return customerPage(params)
}
export async function customerById(id: string): Promise<Customer> {
  const response = await apiClient.get<{ data: Customer }>(`/api/v1/customers/${id}`)
  return response.data.data
}
export async function saveCustomer(body: Record<string, unknown>, id?: string): Promise<Customer> {
  const response = id
    ? await apiClient.patch<{ data: Customer }>(`/api/v1/customers/${id}`, body)
    : await apiClient.post<{ data: Customer }>('/api/v1/customers', body)
  return response.data.data
}
export async function customerSetState(id: string, status: Customer['status']): Promise<Customer> {
  const response = await apiClient.post<{ data: Customer }>(`/api/v1/customers/${id}/actions/set-state`, { status })
  return response.data.data
}
export async function customerInteractions(id: string, companyId: string, page = 1, pageSize = 25): Promise<Page<Interaction>> {
  const response = await apiClient.get<Page<Interaction>>(`/api/v1/customers/${id}/interactions`, {
    params: { company_id: companyId, page, page_size: pageSize },
  })
  return response.data
}
export async function addCustomerInteraction(id: string, body: Record<string, unknown>): Promise<Interaction> {
  const response = await apiClient.post<{ data: Interaction }>(`/api/v1/customers/${id}/interactions`, body)
  return response.data.data
}
export async function customerContacts(id: string): Promise<Page<CustomerContact>> {
  const response = await apiClient.get<Page<CustomerContact>>('/api/v1/contacts', {
    params: { page: 1, page_size: 100, filter: { contact_type: 'CUSTOMER', contact_ref_id: id } },
  })
  return response.data
}
export async function saveCustomerContact(id: string, body: Record<string, unknown>, contactId?: string): Promise<CustomerContact> {
  const response = contactId
    ? await apiClient.patch<{ data: CustomerContact }>(`/api/v1/contacts/${contactId}`, body)
    : await apiClient.post<{ data: CustomerContact }>('/api/v1/contacts', {
      contact_type: 'CUSTOMER', contact_ref_id: id, ...body,
    })
  return response.data.data
}
export async function listCompanies(): Promise<Company[]> {
  const response = await apiClient.get<Page<Company>>('/api/v1/organizations', {
    params: { page: 1, page_size: 200 },
  })
  return response.data.data.filter((company) => company.type === 'COMPANY' && company.status === 'ACTIVE')
}
export async function listProvinces(): Promise<Location[]> {
  const response = await apiClient.get<Page<Location>>('/api/v1/provinces', { params: { page: 1, page_size: 200 } })
  return response.data.data
}
/**
 * Search all authorized wards of one province, without truncating options at
 * the first 200 records. RemoteSearchableSelect debounces and pages results.
 * The canonical Ward API remains responsible for PBAC and query validation.
 */
export async function searchCustomerWards(provinceId: string, q: string, page = 1): Promise<Page<Location>> {
  if (!provinceId) return { data: [], meta: { page, page_size: 50, total: 0 } }
  const response = await apiClient.get<Page<Location>>('/api/v1/wards', {
    params: { q: q.trim(), page, page_size: 50, filter: { province_id: provinceId } },
  })
  return response.data
}
