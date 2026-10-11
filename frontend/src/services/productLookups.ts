import type { Product } from '@/api/generated/types.gen'
import type { ListResponse } from '@/components/lists/listTypes'
import { apiClient } from '@/services/api'

/** Mirrors the new OpenAPI read projections; never bypass the shared Axios session. */
export type ProductOption = Pick<
  Product,
  'id' | 'product_code' | 'full_name' | 'role' | 'status' | 'base_uom_id' | 'selectable'
>
export type ProductOptionPurpose = 'price_list_item' | 'combo_component' | 'parent_product'
export type RelationPreview = { product_id: string; total: number; products: Product[] }
export type SalesUomOption = { id: string; code: string }
export type UomLabel = { id: string; code: string; status: 'ACTIVE' | 'INACTIVE' }
export type SalesUomOptions = { product_id: string; options: SalesUomOption[] }
export type OrganizationLabel = { id: string; code: string; name: string; type: string }

type DataResponse<T> = { data: T }

export async function searchProductOptions(
  purpose: ProductOptionPurpose,
  q: string,
  page = 1,
  pageSize = 20,
): Promise<ListResponse<ProductOption>> {
  const response = await apiClient.get<{
    data: ProductOption[]
    meta: ListResponse<ProductOption>['meta']
  }>('/api/v1/products/options', {
    params: { purpose, q, page, page_size: pageSize },
  })
  return { data: response.data.data, meta: response.data.meta }
}

export async function resolveProductOptions(ids: readonly string[]): Promise<ProductOption[]> {
  if (!ids.length) return []
  const { data } = await apiClient.post<DataResponse<ProductOption[]>>(
    '/api/v1/products/options/resolve',
    { product_ids: [...new Set(ids)] },
  )
  return data.data
}

export async function resolveProductDetails(ids: readonly string[]): Promise<Product[]> {
  if (!ids.length) return []
  const { data } = await apiClient.post<DataResponse<Product[]>>(
    '/api/v1/products/details/resolve',
    { product_ids: [...new Set(ids)] },
  )
  return data.data
}

export async function previewProductRelations(ids: readonly string[]): Promise<RelationPreview[]> {
  if (!ids.length) return []
  const { data } = await apiClient.post<DataResponse<RelationPreview[]>>(
    '/api/v1/products/relations-preview',
    { product_ids: [...new Set(ids)], limit_per_product: 2 },
  )
  return data.data
}

export async function resolveSalesUomOptions(ids: readonly string[]): Promise<SalesUomOptions[]> {
  if (!ids.length) return []
  const { data } = await apiClient.post<DataResponse<SalesUomOptions[]>>(
    '/api/v1/products/sales-uom-options/resolve',
    { product_ids: [...new Set(ids)] },
  )
  return data.data
}

export async function resolveProductUomLabels(ids: readonly string[]): Promise<UomLabel[]> {
  if (!ids.length) return []
  const { data } = await apiClient.post<DataResponse<UomLabel[]>>(
    '/api/v1/product-uoms/labels/resolve',
    { uom_ids: [...new Set(ids)] },
  )
  return data.data
}

export async function resolveOrganizationLabels(
  ids: readonly string[],
): Promise<OrganizationLabel[]> {
  if (!ids.length) return []
  const { data } = await apiClient.post<DataResponse<OrganizationLabel[]>>(
    '/api/v1/organizations/labels/resolve',
    { organization_ids: [...new Set(ids)] },
  )
  return data.data
}

/** Distinct bounded requests; each response is permission-scoped to its own session. */
export function chunks<T>(items: readonly T[], size: number): T[][] {
  const result: T[][] = []
  for (let i = 0; i < items.length; i += size) result.push(items.slice(i, i + size))
  return result
}
