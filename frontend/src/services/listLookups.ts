import {
  createListQuery,
  MAX_LIST_PAGE_SIZE,
  type ListQuery,
  type ListResponse,
} from '@/components/lists/listTypes'

/**
 * Read a complete, authorization-filtered master-data lookup through the existing
 * paginated list contract. NEVER silently substitute the first 200 rows for a
 * complete selector. Larger catalogs require a server-search selector instead.
 *
 * Pages are fetched sequentially so total/count changes are detected rather
 * than returning a partially usable option list. This is not a DB snapshot.
 */
export async function loadLookupPages<T>(
  listPage: (query: ListQuery) => Promise<ListResponse<T>>,
  input: Partial<ListQuery> = {},
  maxRows = 10_000,
): Promise<T[]> {
  const query = createListQuery({ ...input, page: 1, page_size: MAX_LIST_PAGE_SIZE })
  const all: T[] = []
  let expectedTotal: number | null = null

  for (let page = 1; ; page += 1) {
    const result = await listPage({ ...query, page })
    const total = result.meta?.total
    if (
      typeof total !== 'number' ||
      !Number.isSafeInteger(total) ||
      total < 0 ||
      !Array.isArray(result.data)
    ) {
      throw new Error('Lookup API returned invalid pagination metadata.')
    }
    if (result.meta.page !== page || result.meta.page_size !== query.page_size) {
      throw new Error('Lookup API returned a different page than requested.')
    }
    if (total > maxRows) {
      throw new Error(
        `Lookup has ${total} rows (limit ${maxRows}). Use a server-search selector for this catalog.`,
      )
    }
    if (expectedTotal === null) expectedTotal = total
    if (total !== expectedTotal) {
      throw new Error('Lookup changed while paging. Reload to avoid an incomplete selection.')
    }
    if (all.length + result.data.length > total) {
      throw new Error('Lookup API returned more rows than its authorized total.')
    }
    all.push(...result.data)
    if (all.length === total) return all
    if (result.data.length === 0 || result.data.length < query.page_size) {
      throw new Error('Lookup API returned an incomplete page. Reload the selector.')
    }
  }
}
