import { apiClient } from '@/services/api'
import type { Product } from '@/api/generated/types.gen'

export function productHasImage(row: Product | null | undefined): boolean {
  return Boolean(
    (row as (Product & { image_available?: boolean }) | null | undefined)?.image_available,
  )
}

const url = (id: string): string => `/api/v1/products/${encodeURIComponent(id)}/image`

export async function uploadProductImage(id: string, image: File): Promise<Product> {
  const body = new FormData()
  body.append('image', image, image.name)
  const response = await apiClient.post<{ data: Product }>(url(id), body, { timeout: 30_000 })
  return response.data.data
}

export async function deleteProductImage(id: string): Promise<Product> {
  const response = await apiClient.delete<{ data: Product }>(url(id))
  return response.data.data
}
