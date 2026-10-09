import {
  createPriceList,
  getPriceList,
  listPriceListItems,
  listPriceLists,
  replacePriceListItems,
  setPriceListState,
  updatePriceList,
} from '@/api/generated/sdk.gen'
import type {
  PriceList,
  PriceListCreate,
  PriceListItem,
  PriceListItemsRequest,
  PriceListPatch,
  State,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { jsonBodySerializer } from '@/api/generated/client'
import { i18n } from '@/i18n'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'

function headers() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
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

function patchPayload<T extends object>(payload: T): Omit<T, 'status'> {
  const next = { ...payload } as T & { status?: unknown }
  delete next.status
  return next as Omit<T, 'status'>
}

export function listPriceListPage(query: ListQuery): Promise<ListResponse<PriceList>> {
  return withTypedClient((client) =>
    listPriceLists({ client, headers: headers(), query: apiQuery(query), throwOnError: true }).then(
      collection,
    ),
  )
}

export function getPriceListById(id: string): Promise<PriceList> {
  return withTypedClient((client) =>
    getPriceList({ client, headers: headers(), path: { id }, throwOnError: true }).then(resource),
  )
}

export function savePriceList(
  payload: PriceListCreate | PriceListPatch,
  id?: string,
): Promise<PriceList> {
  return withTypedClient((client) =>
    id
      ? updatePriceList({
          client,
          headers: headers(),
          path: { id },
          body: patchPayload(payload),
          throwOnError: true,
        }).then(resource)
      : createPriceList({
          client,
          headers: headers(),
          body: payload as PriceListCreate,
          throwOnError: true,
        }).then(resource),
  )
}

export function changePriceListState(id: string, status: State['status']): Promise<PriceList> {
  return withTypedClient((client) =>
    setPriceListState({
      client,
      headers: headers(),
      path: { id },
      body: { status },
      throwOnError: true,
    }).then(resource),
  )
}

export function listPriceListItemRows(id: string): Promise<PriceListItem[]> {
  return withTypedClient((client) =>
    listPriceListItems({
      client,
      headers: headers(),
      path: { id },
      throwOnError: true,
    }).then((response) => response.data.data ?? []),
  )
}

export function replacePriceListItemRows(
  id: string,
  body: PriceListItemsRequest,
): Promise<PriceListItem[]> {
  return withTypedClient(async (client) => {
    await replacePriceListItems({
      client,
      headers: headers(),
      path: { id },
      body: { items: Array.isArray(body) ? body : body.items },
      bodySerializer: jsonBodySerializer.bodySerializer,
      throwOnError: true,
    })
    // Re-read after replacement because deployed API revisions may return
    // 200 with an empty data array even when the set was persisted.
    const response = await listPriceListItems({
      client,
      headers: headers(),
      path: { id },
      throwOnError: true,
    })
    const rows = response.data.data ?? []
    const submitted = Array.isArray(body) ? body : body.items
    if (submitted.length > 0 && rows.length === 0) {
      throw new Error(
        'Price List replacement was acknowledged but no rows were returned by the API.',
      )
    }
    return rows
  })
}
