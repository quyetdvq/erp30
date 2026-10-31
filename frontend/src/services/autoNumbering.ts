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
  Resource,
} from '@/api/generated/types.gen'
import { withTypedClient } from '@/api/client'
import { apiClient } from '@/services/api'
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { i18n } from '@/i18n'
import type { ListResponse } from '@/components/lists/listTypes'

/**
 * Screen-specific read projection; the existing typed Resource/Numbering
 * endpoints remain authoritative for detail, edits, validation and state.
 * Uses the shared Axios transport (session, locale, request IDs, errors).
 * This route is also documented in the canonical OpenAPI specification.
 */
export type AutoNumberingOverview = {
  resources: Resource[]
  series: NumberingSeriesRow[]
}

export async function getAutoNumberingOverview(): Promise<AutoNumberingOverview> {
  const response = await apiClient.get<{ data?: AutoNumberingOverview }>(
    '/api/v1/numbering-series/overview',
  )
  const payload = response.data?.data
  if (!payload || !Array.isArray(payload.resources) || !Array.isArray(payload.series)) {
    throw new Error('Numbering overview returned an invalid response.')
  }
  // A response must never elevate advisory row action hints or mix scopes.
  // The view performs the complete, per-Resource series integrity check
  // before atomically installing this snapshot.
  const ids = new Set<string>()
  for (const resource of payload.resources) {
    const id = String(resource?.id)
    if (!/^[1-9][0-9]*$/.test(id) || ids.has(id) ||
      rowAllowedActions(resource)?.configure_numbering !== true) {
      throw new Error('Numbering overview returned an invalid Resource catalog.')
    }
    ids.add(id)
  }
  numberSeriesSnapshotForAll([...ids], payload.series)
  return payload
}

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
    }).then((response) => {
      const result = collection(response)
      // API-040 exposes a complete, non-paginated Resource collection. Do not
      // cache a truncated future/backend response as if it were complete.
      if (!Number.isSafeInteger(result.meta?.total) || result.meta.total < 0 ||
        result.meta.page !== 1 || result.meta.total !== result.data.length) {
        throw new Error('Numbering API returned an incomplete Resource list.')
      }
      return result
    }),
  )
}

/**
 * One authorization-scoped, view-local snapshot. Only complete Resource
 * responses may enter the cache; a failed All-Resource read commits nothing.
 * Never share it globally across users, route visits or permission snapshots.
 */
export type NumberingSeriesSnapshot = ReadonlyMap<string, readonly NumberingSeriesRow[]>

function verifiedResourceRows(resourceId: string, rows: readonly NumberingSeriesRow[]): NumberingSeriesRow[] {
  if (!/^[1-9][0-9]*$/.test(resourceId)) throw new Error('Invalid numbering Resource ID.')
  const seen = new Set<string>()
  for (const row of rows) {
    const id = String(row.id)
    if (String(row.resource_id) !== resourceId || !/^[1-9][0-9]*$/.test(id) || seen.has(id)) {
      throw new Error('Numbering API returned an invalid or cross-resource series.')
    }
    seen.add(id)
  }
  return [...rows]
}

/** Build an atomic snapshot from a complete, authorized All-Resource read. */
export function numberSeriesSnapshotForAll(
  resourceIds: readonly string[],
  rows: readonly NumberingSeriesRow[],
): NumberingSeriesSnapshot {
  const ids = new Set(resourceIds)
  const grouped = new Map<string, NumberingSeriesRow[]>()
  for (const id of ids) {
    if (!/^[1-9][0-9]*$/.test(id)) throw new Error('Invalid numbering Resource ID.')
    grouped.set(id, [])
  }
  for (const row of rows) {
    const id = String(row.resource_id)
    const bucket = grouped.get(id)
    if (!bucket) throw new Error('Numbering API returned an unauthorized Resource.')
    bucket.push(row)
  }
  for (const [id, bucket] of grouped) grouped.set(id, verifiedResourceRows(id, bucket))
  return grouped
}

/** A scoped reload replaces only the affected Resource, not other snapshots. */
export function numberSeriesSnapshotForResource(
  snapshot: NumberingSeriesSnapshot,
  resourceId: string,
  rows: readonly NumberingSeriesRow[],
): NumberingSeriesSnapshot {
  return new Map(snapshot).set(resourceId, verifiedResourceRows(resourceId, rows))
}

/**
 * Successful POST/PATCH/set-state responses contain the canonical server row.
 * Apply it only to a Resource whose COMPLETE list is already present; never
 * mistake an isolated mutation response for an entire Resource listing.
 */
export function numberSeriesSnapshotWithMutation(
  snapshot: NumberingSeriesSnapshot,
  resourceId: string,
  row: NumberingSeriesRow,
): NumberingSeriesSnapshot {
  verifiedResourceRows(resourceId, [row])
  const cached = snapshot.get(resourceId)
  if (!cached) return snapshot
  const position = cached.findIndex((item) => String(item.id) === String(row.id))
  const updated = [...cached]
  if (position === -1) updated.push(row)
  else updated[position] = row
  return numberSeriesSnapshotForResource(snapshot, resourceId, updated)
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
  seriesId?: string,
): Promise<NumberingSeriesValidation> {
  // Previewing an EDIT must exclude only its own persisted series. The API
  // confirms the series belongs to this Resource before applying the exclusion.
  const body = seriesId ? { ...payload, series_id: seriesId } : payload
  return resource(
    await withTypedClient((client) =>
      validateNumberingSeriesConfig({
        client,
        path: { resource_id: resourceId },
        body,
        headers: headers(),
        throwOnError: true,
      }),
    ),
  )
}
