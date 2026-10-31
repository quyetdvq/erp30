import { beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/api', () => ({ apiClient: { get } }))
vi.mock('@/api/client', () => ({ withTypedClient: vi.fn() }))

import { getAutoNumberingOverview } from '@/services/autoNumbering'

const resource = (id: string, allowed = true) => ({
  id, type: 'PRODUCT', name: `Resource ${id}`, domain: 'MASTER',
  status: 'ACTIVE', allowed_actions: { configure_numbering: allowed },
})
const series = (id: string, resourceId: string) => ({
  id, resource_id: resourceId, series_code: `SERIES_${id}`, name: `Series ${id}`,
  scope_type: 'GROUP', pattern: `R${id}-{SEQ:6}`, reset_policy: 'NEVER',
  generation_mode: 'AUTO_REQUIRED', start_number: '1', increment_by: 1,
  is_default: false, status: 'ACTIVE',
})
const result = (resources: unknown, rows: unknown) => ({
  data: { data: { resources, series: rows } },
})

beforeEach(() => get.mockReset())

describe('Auto Numbering combined initial read', () => {
  it('loads authorized Resource options and all series in exactly one GET', async () => {
    get.mockResolvedValueOnce(result([resource('2'), resource('3')], [series('21','2'), series('31','3')]))
    const overview = await getAutoNumberingOverview()
    expect(overview.resources).toHaveLength(2)
    expect(overview.series).toHaveLength(2)
    expect(get).toHaveBeenCalledExactlyOnceWith('/api/v1/numbering-series/overview')
  })

  it('accepts an empty authorized catalog without any per-Resource follow-up GET', async () => {
    get.mockResolvedValueOnce(result([], []))
    expect(await getAutoNumberingOverview()).toEqual({ resources: [], series: [] })
    expect(get).toHaveBeenCalledTimes(1)
  })

  it('rejects malformed, unauthorized and duplicate Resource catalogs', async () => {
    for (const bad of [
      result(undefined, []),
      result([resource('2', false)], []),
      result([resource('2'), resource('2')], []),
      result([resource('0')], []),
    ]) {
      get.mockResolvedValueOnce(bad)
      await expect(getAutoNumberingOverview()).rejects.toThrow()
    }
    expect(get).toHaveBeenCalledTimes(4)
  })

  it('rejects cross-Resource series and duplicate IDs before exposing any snapshot', async () => {
    get.mockResolvedValueOnce(result([resource('2')], [series('31', '3')]))
    await expect(getAutoNumberingOverview()).rejects.toThrow(/unauthorized/)
    get.mockResolvedValueOnce(result([resource('2')], [series('21','2'), series('21','2')]))
    await expect(getAutoNumberingOverview()).rejects.toThrow(/invalid/)
    expect(get).toHaveBeenCalledTimes(2)
  })

  it('propagates API 403 instead of falling back to N scoped reads', async () => {
    const forbidden = Object.assign(new Error('Forbidden'), { response: { status: 403 } })
    get.mockRejectedValueOnce(forbidden)
    await expect(getAutoNumberingOverview()).rejects.toBe(forbidden)
    expect(get).toHaveBeenCalledTimes(1)
  })
})
