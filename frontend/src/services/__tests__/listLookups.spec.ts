import { describe, expect, it, vi } from 'vitest'
import { loadLookupPages } from '@/services/listLookups'
import type { ListQuery, ListResponse } from '@/components/lists/listTypes'

function paged(total: number) {
  const request = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => {
    const start = (query.page - 1) * query.page_size
    return {
      data: Array.from(
        { length: Math.max(0, Math.min(query.page_size, total - start)) },
        (_, i) => start + i,
      ),
      meta: { page: query.page, page_size: query.page_size, total },
    }
  })
  return request
}

describe('complete master-data lookups', () => {
  it('loads beyond the API page limit without exceeding 200 per call', async () => {
    const request = paged(451)
    const rows = await loadLookupPages(request, { sort: 'name', filter: { status: 'ACTIVE' } })
    expect(rows).toHaveLength(451)
    expect(rows[450]).toBe(450)
    expect(request).toHaveBeenCalledTimes(3)
    expect(request.mock.calls.map(([q]) => q.page)).toEqual([1, 2, 3])
    for (const [q] of request.mock.calls) {
      expect(q.page_size).toBe(200)
      expect(q.sort).toBe('name')
      expect(q.filter).toEqual({ status: 'ACTIVE' })
    }
  })

  it('fetches slow later pages concurrently but returns rows in stable API page order', async () => {
    const total = 1_201
    let inFlight = 0
    let peakInFlight = 0
    const request = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => {
      inFlight += 1
      peakInFlight = Math.max(peakInFlight, inFlight)
      // Finish later pages out of order. Their rows must still follow page order.
      await new Promise<void>((resolve) => setTimeout(resolve, (8 - query.page) * 2))
      inFlight -= 1
      const start = (query.page - 1) * query.page_size
      return {
        data: Array.from(
          { length: Math.max(0, Math.min(query.page_size, total - start)) },
          (_, i) => start + i,
        ),
        meta: { page: query.page, page_size: query.page_size, total },
      }
    })
    const rows = await loadLookupPages(request)
    expect(rows).toEqual(Array.from({ length: total }, (_, i) => i))
    expect(request).toHaveBeenCalledTimes(7)
    expect(peakInFlight).toBeGreaterThan(1)
    expect(peakInFlight).toBeLessThanOrEqual(4)
  })

  it('fails closed if an asynchronously loaded page changes the authorized total', async () => {
    const request = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => {
      const total = query.page === 3 ? 402 : 401
      const start = (query.page - 1) * query.page_size
      return {
        data: Array.from(
          { length: Math.max(0, Math.min(query.page_size, 401 - start)) },
          (_, i) => start + i,
        ),
        meta: { page: query.page, page_size: query.page_size, total },
      }
    })
    await expect(loadLookupPages(request)).rejects.toThrow(/changed while paging/)
  })

  it('fails closed when the first page is shorter than the reported total', async () => {
    const request = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => ({
      data: [1],
      meta: { page: query.page, page_size: query.page_size, total: 350 },
    }))
    await expect(loadLookupPages(request)).rejects.toThrow(/incomplete page/)
    expect(request).toHaveBeenCalledTimes(1)
  })

  it('does not accidentally omit authorization-safe empty results', async () => {
    expect(await loadLookupPages(paged(0))).toEqual([])
  })

  it('fails closed rather than presenting an incomplete selector when the lookup is too large', async () => {
    const request = paged(501)
    await expect(loadLookupPages(request, {}, 500)).rejects.toThrow(/server-search selector/)
    expect(request).toHaveBeenCalledTimes(1)
  })

  it('rejects an API that returns the same page repeatedly', async () => {
    const request = vi.fn<(q: ListQuery) => Promise<ListResponse<number>>>(async (q) => ({
      data: Array.from({ length: 200 }, (_, i) => i),
      meta: { page: 1, page_size: q.page_size, total: 400 },
    }))
    await expect(loadLookupPages(request)).rejects.toThrow(/different page/)
  })

  it('rejects inconsistent pagination and never returns a partial option list', async () => {
    const request = vi.fn<(q: ListQuery) => Promise<ListResponse<number>>>(async (q) => ({
      data: q.page === 1 ? Array.from({ length: 200 }, (_, i) => i) : [],
      meta: { page: q.page, page_size: q.page_size, total: 250 },
    }))
    await expect(loadLookupPages(request)).rejects.toThrow(/incomplete page/)
  })
})
