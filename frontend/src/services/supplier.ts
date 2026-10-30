import type {
  Supplier,
  SupplierCreate,
  SupplierPatch,
  SupplierCompanyUsage,
  SupplierCompanyUsagePut,
  SupplierSetState,
} from '@/api/generated/types.gen'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'
import { apiClient } from '@/services/api'
import type { OrganizationLabel } from '@/services/productLookups'

// T07B/T08: transport uses the existing session + rotating CSRF interceptor.
// DTOs are tool-generated from the canonical seven Supplier operations.
export type { Supplier, SupplierCreate, SupplierPatch, SupplierCompanyUsage, SupplierCompanyUsagePut, SupplierSetState }

/** UINT32 CAS is strict JSON integer (no parseInt truncation, stale fallback or string body). */
export function expectedSupplierVersion(raw: string | number): number {
  const text = String(raw)
  if (!/^[1-9][0-9]*$/.test(text) || text.length > 10) throw new Error('SUPPLIER_EXPECTED_VERSION_INVALID')
  const value = Number(text)
  if (!Number.isSafeInteger(value) || value < 1 || value > 4294967294) {
    throw new Error('SUPPLIER_EXPECTED_VERSION_INVALID')
  }
  return value
}

const SUPPLIER_FILTERS = ['status', 'supplier_type', 'supplier_group_code', 'company_id'] as const
const SUPPLIER_SORT = new Set(['name', '-name', 'supplier_code', '-supplier_code', 'id', '-id'])

/** Flat Supplier query, unlike the nested `filter` in generic P1 lists. */
export function supplierListParams(query: ListQuery): Record<string, string | number> {
  if (!Number.isInteger(query.page) || query.page < 1 || !Number.isInteger(query.page_size) ||
      query.page_size < 1 || query.page_size > 100) throw new Error('SUPPLIER_PAGE_INVALID')
  const params: Record<string, string | number> = {
    page: query.page,
    page_size: query.page_size,
    q: query.q.trim(),
    sort: SUPPLIER_SORT.has(query.sort) ? query.sort : 'name',
  }
  for (const key of SUPPLIER_FILTERS) {
    const value = query.filter[key]
    if (typeof value === 'string' && value) params[key] = value
  }
  return params
}

export async function listSupplierPage(query: ListQuery): Promise<ListResponse<Supplier>> {
  const response = await apiClient.get<ListResponse<Supplier>>('/api/v1/suppliers', {
    params: supplierListParams(query),
  })
  return response.data
}

export async function supplierById(id: string): Promise<Supplier> {
  const response = await apiClient.get<{ data: Supplier }>(`/api/v1/suppliers/${encodeURIComponent(id)}`)
  return response.data.data
}

export async function saveSupplier(payload: SupplierCreate | SupplierPatch, id?: string): Promise<Supplier> {
  const response = id
    ? await apiClient.patch<{ data: Supplier }>(`/api/v1/suppliers/${encodeURIComponent(id)}`, payload)
    : await apiClient.post<{ data: Supplier }>('/api/v1/suppliers', payload)
  return response.data.data
}

export async function supplierCompanyUsage(id: string): Promise<SupplierCompanyUsage> {
  const response = await apiClient.get<{ data: SupplierCompanyUsage }>(
    `/api/v1/suppliers/${encodeURIComponent(id)}/company-usages`,
  )
  return response.data.data
}

export async function replaceSupplierCompanyUsage(id: string, payload: SupplierCompanyUsagePut): Promise<Supplier> {
  const response = await apiClient.put<{ data: Supplier }>(
    `/api/v1/suppliers/${encodeURIComponent(id)}/company-usages`, payload,
  )
  return response.data.data
}

/** Supplier owns the CAS Workflow transaction; generic Workflow/execute is never invoked. */
export async function supplierSetState(id: string, payload: SupplierSetState): Promise<Supplier> {
  const response = await apiClient.post<{ data: Supplier }>(
    `/api/v1/suppliers/${encodeURIComponent(id)}/actions/set-state`, payload,
  )
  return response.data.data
}

/** Authorized search is SQL-paged, never first-200 Company client filtering. */
export async function searchSupplierCompanies(q: string, page = 1): Promise<{ data: OrganizationLabel[]; meta: ListResponse<OrganizationLabel>['meta'] }> {
  const response = await apiClient.get<ListResponse<OrganizationLabel>>('/api/v1/organizations', {
    params: {
      q: q.trim(), page, page_size: 50, sort: 'name',
      filter: { type: 'COMPANY', status: 'ACTIVE' },
    },
  })
  return {
    data: response.data.data,
    meta: response.data.meta,
  }
}
