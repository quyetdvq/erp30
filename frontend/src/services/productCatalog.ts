import {
  createProduct,
  createProductGroup,
  createProductUom,
  getProduct,
  listProductGroups,
  listProductUomConversions,
  listProductUoms,
  listProducts,
  replaceProductUomConversions,
  setProductGroupState,
  setProductState,
  setProductUomState,
  updateProduct,
  updateProductGroup,
  updateProductUom,
} from '@/api/generated/sdk.gen'
import type {
  Product,
  ProductCreate,
  ProductGroup,
  ProductGroupCreate,
  ProductGroupPatch,
  ProductPatch,
  ProductUom,
  ProductUomConversion,
  ProductUomConversionsRequest,
  ProductUomCreate,
  ProductUomPatch,
  State,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { i18n } from '@/i18n'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'

function headers() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
}

/** State changes use the dedicated set-state endpoints, never PATCH. */
function patchPayload<T extends object>(payload: T): Omit<T, 'status'> {
  const next = { ...payload } as T & { status?: unknown }
  delete next.status
  return next as Omit<T, 'status'>
}

function apiQuery(query: ListQuery) {
  return {
    q: query.q || undefined,
    page: query.page,
    page_size: query.page_size,
    sort: query.sort || undefined,
    filter: Object.fromEntries(
      Object.entries(query.filter).map(([key, value]) => [
        key,
        typeof value === 'string' ? value : [...value],
      ]),
    ),
  }
}

function collection<T>(response: {
  data: { data?: T[]; meta: ListResponse<T>['meta'] }
}): ListResponse<T> {
  return { data: response.data.data ?? [], meta: response.data.meta }
}

function resource<T>(response: { data: { data?: T } }): T {
  if (!response.data.data) throw new Error('API response did not contain a resource.')
  return response.data.data
}

export function listProductGroupPage(query: ListQuery): Promise<ListResponse<ProductGroup>> {
  return withTypedClient((client) =>
    listProductGroups({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export function saveProductGroup(
  payload: ProductGroupCreate | ProductGroupPatch,
  id?: string,
): Promise<ProductGroup> {
  return withTypedClient((client) =>
    id
      ? updateProductGroup({
          client,
          headers: headers(),
          path: { id },
          body: patchPayload(payload),
          throwOnError: true,
        }).then(resource)
      : createProductGroup({
          client,
          headers: headers(),
          body: payload as ProductGroupCreate,
          throwOnError: true,
        }).then(resource),
  )
}

export function changeProductGroupState(
  id: string,
  status: State['status'],
): Promise<ProductGroup> {
  return withTypedClient((client) =>
    setProductGroupState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }).then(resource),
  )
}

export function listProductUomPage(query: ListQuery): Promise<ListResponse<ProductUom>> {
  return withTypedClient((client) =>
    listProductUoms({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export function saveProductUom(
  payload: ProductUomCreate | ProductUomPatch,
  id?: string,
): Promise<ProductUom> {
  return withTypedClient((client) =>
    id
      ? updateProductUom({
          client,
          headers: headers(),
          path: { id },
          body: patchPayload(payload),
          throwOnError: true,
        }).then(resource)
      : createProductUom({
          client,
          headers: headers(),
          body: payload as ProductUomCreate,
          throwOnError: true,
        }).then(resource),
  )
}

export function changeProductUomState(id: string, status: State['status']): Promise<ProductUom> {
  return withTypedClient((client) =>
    setProductUomState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }).then(resource),
  )
}

export function listProductPage(query: ListQuery): Promise<ListResponse<Product>> {
  return withTypedClient((client) =>
    listProducts({ client, headers: headers(), query: apiQuery(query), throwOnError: true }).then(
      collection,
    ),
  )
}

export function getProductById(id: string): Promise<Product> {
  return withTypedClient((client) =>
    getProduct({ client, headers: headers(), path: { id }, throwOnError: true }).then(resource),
  )
}

export function saveProduct(payload: ProductCreate | ProductPatch, id?: string): Promise<Product> {
  return withTypedClient((client) =>
    id
      ? updateProduct({
          client,
          headers: headers(),
          path: { id },
          body: patchPayload(payload),
          throwOnError: true,
        }).then(resource)
      : createProduct({
          client,
          headers: headers(),
          body: payload as ProductCreate,
          throwOnError: true,
        }).then(resource),
  )
}

export function changeProductState(id: string, status: State['status']): Promise<Product> {
  return withTypedClient((client) =>
    setProductState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }).then(resource),
  )
}

export function listProductConversions(id: string): Promise<ProductUomConversion[]> {
  return withTypedClient((client) =>
    listProductUomConversions({
      client,
      headers: headers(),
      path: { id },
      throwOnError: true,
    }).then((response) => response.data.data ?? []),
  )
}

export function replaceProductConversions(
  id: string,
  body: ProductUomConversionsRequest,
): Promise<ProductUomConversion[]> {
  return withTypedClient((client) =>
    replaceProductUomConversions({
      client,
      headers: headers(),
      path: { id },
      body,
      throwOnError: true,
    }).then((response) => response.data.data ?? []),
  )
}
