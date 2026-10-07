import { describe, expect, it } from 'vitest'

import { useListState } from '@/composables/useListState'
import { createListQuery, resetListQuery, serializeListQuery } from '@/components/lists/listTypes'

type Row = { id: string; amount: string }

function result(data: Row[], total = data.length) {
  return { data, meta: { page: 1, page_size: 50, total } }
}

describe('shared list query infrastructure', () => {
  it('serializes the canonical API query without coercing values', () => {
    const query = createListQuery({
      q: '  9007199254740993  ',
      sort: '-created_at',
      filter: { status: ['ACTIVE', 'DRAFT'], owner_id: '9007199254740993' },
    })
    expect(serializeListQuery(query)).toBe(
      'q=9007199254740993&page=1&page_size=50&sort=-created_at&filter%5Bowner_id%5D=9007199254740993&filter%5Bstatus%5D%5B%5D=ACTIVE&filter%5Bstatus%5D%5B%5D=DRAFT',
    )
    expect(resetListQuery(query)).toEqual({ q: '', page: 1, page_size: 50, sort: '', filter: {} })
  })

  it('resets page for query controls and trusts backend metadata for counts', async () => {
    const calls: string[] = []
    const list = useListState<Row>(async (query) => {
      calls.push(serializeListQuery(query))
      return result([{ id: '9007199254740993', amount: '0.10' }], 101)
    })

    expect(list.query.page_size).toBe(50)
    await list.apply()
    expect(list.total.value).toBe(101)
    expect(list.pageCount.value).toBe(3)
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
