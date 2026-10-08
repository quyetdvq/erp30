import {
  createNumberingSeries,
  getNumberingSeries,
  listNumberingSeries,
  setNumberingSeriesState,
  updateNumberingSeries,
  validateNumberingSeriesConfig,
} from '@/api/generated/sdk.gen'
import type {
  NumberingSeries,
  NumberingSeriesCreate,
  NumberingSeriesPatch,
  NumberingSeriesState,
  NumberingSeriesValidation,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { i18n } from '@/i18n'
import type { ListResponse } from '@/components/lists/listTypes'

export type NumberingSeriesRow = NumberingSeries & {
  /** Optional forward-compatible metadata; API-040 currently omits issuance state. */
  issued?: boolean
  is_issued?: boolean
}

function headers() {
  return { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' }
}

function collection(response: {
  data: { data?: NumberingSeriesRow[]; meta: ListResponse<NumberingSeriesRow>['meta'] }
}): ListResponse<NumberingSeriesRow> {
  return { data: response.data.data ?? [], meta: response.data.meta }
}

function resource<T>(response: { data: { data?: T } }): T {
  if (!response.data.data) throw new Error('API response did not contain a resource.')
  return response.data.data
}

export function listNumberingSeriesPage(
  resourceId: string,
): Promise<ListResponse<NumberingSeriesRow>> {
  return withTypedClient((client) =>
    listNumberingSeries({
      client,
      path: { resource_id: resourceId },
      headers: headers(),
      throwOnError: true,
    }).then(collection),
  )
}

export async function getNumberingSeriesById(
  resourceId: string,
  seriesId: string,
): Promise<NumberingSeriesRow> {
  return resource(
    await withTypedClient((client) =>
      getNumberingSeries({
        client,
        path: { resource_id: resourceId, series_id: seriesId },
        headers: headers(),
        throwOnError: true,
      }),
    ),
  )
}

export async function saveNumberingSeries(
  resourceId: string,
  payload: NumberingSeriesCreate | NumberingSeriesPatch,
  seriesId?: string,
): Promise<NumberingSeriesRow> {
  return resource(
    await withTypedClient((client) =>
      seriesId
        ? updateNumberingSeries({
            client,
            path: { resource_id: resourceId, series_id: seriesId },
            body: payload as NumberingSeriesPatch,
            headers: headers(),
            throwOnError: true,
          })
        : createNumberingSeries({
            client,
            path: { resource_id: resourceId },
            body: payload as NumberingSeriesCreate,
            headers: headers(),
            throwOnError: true,
          }),
    ),
  )
}

export async function changeNumberingSeriesState(
  resourceId: string,
  seriesId: string,
  status: NumberingSeriesState['status'],
): Promise<NumberingSeriesRow> {
  return resource(
    await withTypedClient((client) =>
      setNumberingSeriesState({
        client,
        path: { resource_id: resourceId, series_id: seriesId },
        body: { status },
        headers: headers(),
        throwOnError: true,
      }),
    ),
  )
}

export async function validateNumberingSeries(
  resourceId: string,
  payload: NumberingSeriesCreate,
): Promise<NumberingSeriesValidation> {
  return resource(
    await withTypedClient((client) =>
      validateNumberingSeriesConfig({
        client,
        path: { resource_id: resourceId },
        body: payload,
        headers: headers(),
        throwOnError: true,
      }),
    ),
  )
}
