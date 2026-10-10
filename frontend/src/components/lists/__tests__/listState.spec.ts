import { describe, expect, it } from 'vitest'

import { useListState } from '@/composables/useListState'
import {
  createListQuery,
  DEFAULT_LIST_PAGE_SIZE,
  resetListQuery,
  serializeListQuery,
  type ListResponse,
} from '@/components/lists/listTypes'

type Row = { id: string; amount: string }

function result(
  data: Row[],
  total = data.length,
  page = 1,
  pageSize = DEFAULT_LIST_PAGE_SIZE,
): ListResponse<Row> {
  return { data, meta: { page, page_size: pageSize, total } }
}

function deferred<T>(): {
  promise: Promise<T>
  resolve: (value: T) => void
} {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}

describe('shared list query infrastructure', () => {
  it('serializes the canonical API query without coercing values', () => {
    const query = createListQuery({
      q: '  9007199254740993  ',
      sort: '-created_at',
      filter: { status: ['ACTIVE', 'DRAFT'], owner_id: '9007199254740993' },
    })
    expect(serializeListQuery(query)).toBe(
      `q=9007199254740993&page=1&page_size=${DEFAULT_LIST_PAGE_SIZE}&sort=-created_at&filter%5Bowner_id%5D=9007199254740993&filter%5Bstatus%5D%5B%5D=ACTIVE&filter%5Bstatus%5D%5B%5D=DRAFT`,
    )
    expect(resetListQuery(query)).toEqual({
      q: '',
      page: 1,
      page_size: DEFAULT_LIST_PAGE_SIZE,
      sort: '',
      filter: {},
    })
    expect(resetListQuery(createListQuery({ page_size: 100 })).page_size).toBe(100)
  })

  it('resets page for query controls and trusts backend metadata for counts', async () => {
    const calls: string[] = []
    const list = useListState<Row>(async (query) => {
      calls.push(serializeListQuery(query))
      return result([{ id: '9007199254740993', amount: '0.10' }], 101)
    })

    expect(list.query.page_size).toBe(DEFAULT_LIST_PAGE_SIZE)
    await list.apply()
    expect(list.total.value).toBe(101)
    expect(list.pageCount.value).toBe(Math.ceil(101 / DEFAULT_LIST_PAGE_SIZE))
    expect(list.rows.value[0]).toEqual({ id: '9007199254740993', amount: '0.10' })

    list.setSearch('alpha')
    list.setFilters({ status: ['ACTIVE', 'DRAFT'] })
    list.setSort('-created_at')
    list.setPage(2)
    expect(list.query.page).toBe(2)
    expect(list.queryString.value).toContain('page=2')
    expect(list.queryString.value).toContain('filter%5Bstatus%5D%5B%5D=ACTIVE')
    expect(calls).toHaveLength(1)
  })

  it('keeps only the newest list response if two page requests finish out of order', async () => {
    const first = deferred<ListResponse<Row>>()
    const second = deferred<ListResponse<Row>>()
    const pages: number[] = []
    const list = useListState<Row>((query) => {
      pages.push(query.page)
      return pages.length === 1 ? first.promise : second.promise
    })

    const firstRequest = list.apply()
    list.setPage(2)
    const secondRequest = list.apply()

    second.resolve(result([{ id: 'new', amount: '2.00' }], 22, 2))
    await secondRequest
    expect(list.rows.value.map((row) => row.id)).toEqual(['new'])
    expect(list.response.value).toBe(list.queryState.data.value)
    expect(list.queryState.status.value).toBe('loaded')

    first.resolve(result([{ id: 'old', amount: '1.00' }], 1))
    await firstRequest
    expect(pages).toEqual([1, 2])
    expect(list.rows.value.map((row) => row.id)).toEqual(['new'])
    expect(list.total.value).toBe(22)
    expect(list.response.value?.meta.page).toBe(2)
  })

  it('never restores an invalidated list response after reset', async () => {
    const pending = deferred<ListResponse<Row>>()
    const list = useListState<Row>(() => pending.promise)

    const loading = list.apply()
    expect(list.isInitialLoading.value).toBe(true)
    list.reset()
    pending.resolve(result([{ id: 'old', amount: '1.00' }]))
    await loading

    expect(list.queryState.status.value).toBe('idle')
    expect(list.queryState.hasData.value).toBe(false)
    expect(list.response.value).toBeNull()
    expect(list.rows.value).toEqual([])
    expect(list.total.value).toBe(0)
  })

  it('does not let an older refresh overwrite a newer successful query', async () => {
    const oldRefresh = deferred<ListResponse<Row>>()
    const latest = deferred<ListResponse<Row>>()
    let calls = 0
    const list = useListState<Row>(() => {
      calls += 1
      if (calls === 1) return Promise.resolve(result([{ id: 'initial', amount: '1.00' }]))
      return calls === 2 ? oldRefresh.promise : latest.promise
    })

    await list.apply()
    const refreshing = list.refresh()
    expect(list.isBackgroundRefreshing.value).toBe(true)
    expect(list.rows.value[0]?.id).toBe('initial')

    list.setSearch('new')
    const searching = list.apply()
    latest.resolve(result([{ id: 'latest', amount: '3.00' }]))
    await searching
    oldRefresh.resolve(result([{ id: 'stale', amount: '2.00' }]))
    await refreshing

    expect(list.rows.value[0]?.id).toBe('latest')
    expect(list.response.value).toBe(list.queryState.data.value)
    expect(list.queryState.error.value).toBeNull()
  })

  it('distinguishes initial empty, filtered empty, and refresh failure with retained data', async () => {
    let call = 0
    const list = useListState<Row>(async () => {
      call += 1
      if (call === 1) return result([])
      if (call === 2) return result([])
      if (call === 3) return result([{ id: '1', amount: '1.00' }], 1)
      throw new Error('temporary outage')
    })

    await list.apply()
    expect(list.isEmptyDataset.value).toBe(true)
    list.setSearch('missing')
    await list.apply()
    expect(list.isEmptyFilteredResult.value).toBe(true)
    list.setSearch('found')
    await list.apply()
    expect(list.rows.value).toHaveLength(1)
    await expect(list.refresh()).rejects.toThrow('temporary outage')
    expect(list.queryState.status.value).toBe('error')
    expect(list.queryState.hasData.value).toBe(true)
    expect(list.rows.value[0]?.amount).toBe('1.00')
  })
})
