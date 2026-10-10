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

describe('shared lookup request budget', () => {
  it('caps simultaneous first-page requests from seven independent selectors', async () => {
    let release!: () => void
    const gate = new Promise<void>((resolve) => {
      release = resolve
    })
    let active = 0
    let peak = 0
    const request = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => {
      active += 1
      peak = Math.max(peak, active)
      try {
        await gate
        return {
          data: [query.page],
          meta: { page: query.page, page_size: query.page_size, total: 1 },
        }
      } finally {
        active -= 1
      }
    })

    const pending = Array.from({ length: 7 }, () => loadLookupPages(request))
    const startedBeforeRelease = request.mock.calls.length
    release()
    const rows = await Promise.all(pending)
    expect(startedBeforeRelease).toBe(4)
    expect(request).toHaveBeenCalledTimes(7)
    expect(rows).toEqual(Array.from({ length: 7 }, () => [1]))
    expect(peak).toBe(4)
    expect(active).toBe(0)
  })

  it('never exceeds four in-flight page requests across simultaneous selectors', async () => {
    let inFlight = 0
    let peak = 0
    const request = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => {
      inFlight += 1
      peak = Math.max(peak, inFlight)
      try {
        await new Promise<void>((resolve) => setTimeout(resolve, 3))
        const total = 1_001
        const start = (query.page - 1) * query.page_size
        return {
          data: Array.from(
            { length: Math.max(0, Math.min(query.page_size, total - start)) },
            (_, i) => start + i,
          ),
          meta: { page: query.page, page_size: query.page_size, total },
        }
      } finally {
        inFlight -= 1
      }
    })

    const lookups = await Promise.all(Array.from({ length: 4 }, () => loadLookupPages(request)))
    for (const rows of lookups) {
      expect(rows).toHaveLength(1_001)
      expect(rows[1_000]).toBe(1_000)
    }
    expect(request).toHaveBeenCalledTimes(24)
    expect(peak).toBeGreaterThan(1)
    expect(peak).toBeLessThanOrEqual(4)
    expect(inFlight).toBe(0)
  })

  it('releases all request slots when a lookup fails and lets the next lookup finish', async () => {
    let inFlight = 0
    const broken = vi.fn<(query: ListQuery) => Promise<ListResponse<number>>>(async (query) => {
      inFlight += 1
      try {
        await new Promise<void>((resolve) => setTimeout(resolve, 2))
        if (query.page === 2) throw new Error('Simulated page failure')
        const total = 601
        const start = (query.page - 1) * query.page_size
        return {
          data: Array.from(
            { length: Math.min(query.page_size, total - start) },
            (_, i) => start + i,
          ),
          meta: { page: query.page, page_size: query.page_size, total },
        }
      } finally {
        inFlight -= 1
      }
    })
    await expect(loadLookupPages(broken)).rejects.toThrow('Simulated page failure')
    expect(inFlight).toBe(0)
    const rows = await loadLookupPages(paged(451))
    expect(rows).toHaveLength(451)
  })
})
