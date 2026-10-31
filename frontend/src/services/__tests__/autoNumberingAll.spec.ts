import { beforeEach, describe, expect, it, vi } from 'vitest'

const { sdkList } = vi.hoisted(() => ({ sdkList: vi.fn() }))
vi.mock('@/api/generated/sdk.gen', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/generated/sdk.gen')>()),
  listNumberingSeries: sdkList,
}))
import { listNumberingSeriesPage } from '@/services/autoNumbering'

const series = (resourceId: string) => ({
  id: resourceId, resource_id: resourceId, series_code: `SERIES_${resourceId}`,
  name: `Number ${resourceId}`, scope_type: 'GROUP', pattern: `R${resourceId}-{SEQ:6}`,
  reset_policy: 'NEVER', generation_mode: 'AUTO_REQUIRED', start_number: '1',
  increment_by: 1, is_default: true, status: 'ACTIVE',
})
const response = (rows: unknown[], total = rows.length) => ({
  data: { data: rows, meta: { page: 1, page_size: Math.max(1, total), total } },
})

describe('Resource-specific Numbering Series refresh (API-040)', () => {
  beforeEach(() => sdkList.mockReset())

  it('uses the typed SDK and sends only the selected Resource ID', async () => {
    sdkList.mockResolvedValueOnce(response([series('2')]))
    const result = await listNumberingSeriesPage('2')
    expect(result.data).toEqual([series('2')])
    expect(sdkList).toHaveBeenCalledTimes(1)
    expect(sdkList.mock.calls[0]![0]).toMatchObject({
      path: { resource_id: '2' }, throwOnError: true,
    })
  })

  it('fails closed if the scoped API returns a truncated or inconsistent list', async () => {
    sdkList.mockResolvedValueOnce(response([series('2')], 2))
    await expect(listNumberingSeriesPage('2')).rejects.toThrow(/incomplete Resource list/)
    sdkList.mockResolvedValueOnce({ data: { data: [], meta: { page: 2, page_size: 1, total: 0 } } })
    await expect(listNumberingSeriesPage('2')).rejects.toThrow(/incomplete Resource list/)
  })

  it('propagates permission denials without retrying the request', async () => {
    const denied = { response: { status: 403 } }
    sdkList.mockRejectedValueOnce(denied)
    await expect(listNumberingSeriesPage('2')).rejects.toBe(denied)
    expect(sdkList).toHaveBeenCalledTimes(1)
  })
})
