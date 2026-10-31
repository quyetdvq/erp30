import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { NumberingSeriesRow } from '@/services/autoNumbering'
import type { ListResponse } from '@/components/lists/listTypes'

// Mock the generated SDK only for direct integration checks. Concurrency,
// ordering and fail-closed unit checks inject the Resource page reader into
// the pure aggregator, eliminating mock coupling to generated SDK internals.
const { sdkList } = vi.hoisted(() => ({ sdkList: vi.fn() }))
vi.mock('@/api/generated/sdk.gen', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/generated/sdk.gen')>()),
  listNumberingSeries: sdkList,
}))
import { aggregateNumberingSeries, listAllNumberingSeries, listNumberingSeriesPage } from '@/services/autoNumbering'

type ListRequest = {
  path: { resource_id: string }
  throwOnError: boolean
  headers: { 'Accept-Language': 'vi' | 'en' }
}

function series(resourceId: string): NumberingSeriesRow {
  return {
    id: resourceId,
    resource_id: resourceId,
    series_code: `SERIES_${resourceId}`,
    name: `Series ${resourceId}`,
    scope_type: 'GROUP',
    pattern: `R${resourceId}-{SEQ:6}`,
    reset_policy: 'NEVER',
    generation_mode: 'AUTO_REQUIRED',
    start_number: '1',
    increment_by: 1,
    is_default: true,
    status: 'ACTIVE',
  }
}
function response(rows: NumberingSeriesRow[], total = rows.length) {
  return { data: { data: rows, meta: { page: 1, page_size: Math.max(1, total), total } } }
}

function resourceIdFrom(options: ListRequest): string {
  expect(options.throwOnError).toBe(true)
  expect(options.headers['Accept-Language']).toMatch(/^(vi|en)$/)
  expect(options.path.resource_id).toMatch(/^[1-9][0-9]*$/)
  return options.path.resource_id
}

describe('All resource numbering aggregation', () => {
  const readPage = vi.fn<(id: string) => Promise<ListResponse<NumberingSeriesRow>>>()
  beforeEach(() => {
    sdkList.mockReset()
    readPage.mockReset()
  })

  it('loads authorized Resource series with four-request budget, stable order and typed paths', async () => {
    let active = 0
    let peak = 0
    readPage.mockImplementation(async (id: string) => {
      active += 1
      peak = Math.max(peak, active)
      try {
        await new Promise((resolve) => setTimeout(resolve, 16 - Number(id)))
        return { data: [series(id)], meta: { page: 1, page_size: 1, total: 1 } }
      } finally {
        active -= 1
      }
    })
    const rows = await aggregateNumberingSeries(['1', '2', '3', '4', '5', '6', '7', '3', '', 'bad'], readPage)
    expect(rows.map((row) => row.resource_id)).toEqual(['1', '2', '3', '4', '5', '6', '7'])
    expect(readPage).toHaveBeenCalledTimes(7)
    expect(readPage.mock.calls.map(([id]) => id).sort()).toEqual(['1', '2', '3', '4', '5', '6', '7'])
    expect(sdkList).not.toHaveBeenCalled()
    expect(peak).toBeGreaterThan(1)
    expect(peak).toBeLessThanOrEqual(4)
  })

  it('fails closed when backend PBAC changes during the Resource list', async () => {
    readPage.mockImplementation(async (id: string) => {
      if (id === '2') throw { response: { status: 403 } }
      return { data: [series(id)], meta: { page: 1, page_size: 1, total: 1 } }
    })
    await expect(aggregateNumberingSeries(['1', '2', '3'], readPage))
      .rejects.toMatchObject({ response: { status: 403 } })
  })

  it('rejects an unexpected backend error rather than showing an incomplete list', async () => {
    readPage.mockImplementation(async (id: string) => {
      if (id === '2') throw { response: { status: 500 } }
      return { data: [series(id)], meta: { page: 1, page_size: 1, total: 1 } }
    })
    await expect(aggregateNumberingSeries(['1', '2', '3'], readPage))
      .rejects.toMatchObject({ response: { status: 500 } })
  })

  it('rejects cross-Resource responses and mismatched metadata', async () => {
    readPage.mockResolvedValueOnce({ data: [series('2')], meta: { page: 1, page_size: 1, total: 1 } })
    await expect(aggregateNumberingSeries(['1'], readPage)).rejects.toThrow(/cross-resource/)
    readPage.mockResolvedValueOnce({ data: [series('1')], meta: { page: 1, page_size: 2, total: 2 } })
    await expect(aggregateNumberingSeries(['1'], readPage)).rejects.toThrow(/incomplete/)
  })

  it('does not call the SDK for an empty or unauthorized catalog', async () => {
    expect(await aggregateNumberingSeries([], readPage)).toEqual([])
    expect(await aggregateNumberingSeries(['', 'unknown', '0'], readPage)).toEqual([])
    expect(readPage).not.toHaveBeenCalled()
    expect(sdkList).not.toHaveBeenCalled()
  })

  it('production All resource delegates through the typed SDK reader', async () => {
    sdkList.mockResolvedValueOnce(response([series('2')]))
    expect((await listAllNumberingSeries(['2'])).map((row) => row.series_code))
      .toEqual(['SERIES_2'])
    expect(sdkList).toHaveBeenCalledTimes(1)
    expect(resourceIdFrom(sdkList.mock.calls[0]![0] as ListRequest)).toBe('2')
  })

  it('never treats a truncated Resource response as a complete cacheable list', async () => {
    sdkList.mockResolvedValueOnce(response([series('2')], 2))
    await expect(listNumberingSeriesPage('2')).rejects.toThrow(/incomplete Resource list/)
  })

  it('uses the Resource-scoped typed SDK operation for a single Resource', async () => {
    sdkList.mockResolvedValueOnce(response([series('2')]))
    const result = await listNumberingSeriesPage('2')
    expect(result.data).toEqual([series('2')])
    expect(sdkList).toHaveBeenCalledTimes(1)
    expect(resourceIdFrom(sdkList.mock.calls[0]![0] as ListRequest)).toBe('2')
  })
})
