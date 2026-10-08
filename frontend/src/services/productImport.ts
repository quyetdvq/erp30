import {
  getImportJob,
  listImportJobRows,
  listImportJobs,
  startProductImport,
} from '@/api/generated/sdk.gen'
import type { ImportJob, ImportJobRow, ProductImportStart } from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
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

export function startProductImportJob(
  file: File,
  mode: ProductImportStart['mode'],
): Promise<ImportJob> {
  return withTypedClient((client) =>
    startProductImport({
      client,
      headers: headers(),
      body: { file, mode },
      throwOnError: true,
    }).then(resource),
  )
}

export function listProductImportJobs(query: ListQuery): Promise<ListResponse<ImportJob>> {
  return withTypedClient((client) =>
    listImportJobs({
      client,
      headers: headers(),
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}

export function getProductImportJob(id: string): Promise<ImportJob> {
  return withTypedClient((client) =>
    getImportJob({ client, headers: headers(), path: { id }, throwOnError: true }).then(resource),
  )
}

export function listProductImportJobRows(
  id: string,
  query: ListQuery,
): Promise<ListResponse<ImportJobRow>> {
  return withTypedClient((client) =>
    listImportJobRows({
      client,
      headers: headers(),
      path: { id },
      query: apiQuery(query),
      throwOnError: true,
    }).then(collection),
  )
}
