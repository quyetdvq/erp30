import {
  createListQuery,
  MAX_LIST_PAGE_SIZE,
  type ListQuery,
  type ListResponse,
} from '@/components/lists/listTypes'

// Keep lookup traffic bounded when several large selectors load at the same time.
// The API still returns 200 rows per request; this only overlaps network latency.
const MAX_CONCURRENT_LOOKUP_PAGES = 4

/**
 * Read a complete, authorization-filtered master-data lookup through the existing
 * paginated list contract. NEVER silently substitute the first page for the full
 * selector. Larger catalogs require server-side search.
 *
 * Read page 1 first to establish the authoritative total, then request the remaining
 * pages with bounded concurrency. Validate every page before returning anything;
 * store by page number so network response order cannot reorder the options.
 * This is not a transactional database snapshot (concurrent updates can still
 * replace a row without changing the total).
 */
export async function loadLookupPages<T>(
  listPage: (query: ListQuery) => Promise<ListResponse<T>>,
  input: Partial<ListQuery> = {},
  maxRows = 10_000,
): Promise<T[]> {
  const query = createListQuery({ ...input, page: 1, page_size: MAX_LIST_PAGE_SIZE })

  function readPage(result: ListResponse<T>, page: number, total?: number): T[] {
    const meta = result.meta
    if (
      !meta ||
      !Number.isSafeInteger(meta.total) ||
      meta.total < 0 ||
      !Array.isArray(result.data)
    ) {
      throw new Error('Lookup API returned invalid pagination metadata.')
    }
    if (meta.page !== page || meta.page_size !== query.page_size) {
      throw new Error('Lookup API returned a different page than requested.')
    }
    if (total !== undefined && meta.total !== total) {
      throw new Error('Lookup changed while paging. Reload to avoid an incomplete selection.')
    }
    const size = Math.min(query.page_size, meta.total - (page - 1) * query.page_size)
    if (result.data.length !== size) {
      throw new Error('Lookup API returned an incomplete page. Reload the selector.')
    }
    return result.data
  }

  const firstResponse = await listPage(query)
  const firstPage = readPage(firstResponse, 1)
  const total = firstResponse.meta.total
  if (total > maxRows) {
    throw new Error(
      `Lookup has ${total} rows (limit ${maxRows}). Use a server-search selector for this catalog.`,
    )
  }
  const pageCount = Math.ceil(total / query.page_size)
  if (pageCount <= 1) return firstPage

  const pages: T[][] = [firstPage]
  let nextPage = 2
  const workers = Array.from(
    { length: Math.min(MAX_CONCURRENT_LOOKUP_PAGES, pageCount - 1) },
    async () => {
      while (nextPage <= pageCount) {
        // Claim synchronously before the await, so workers never request one page twice.
        const page = nextPage++
        const response = await listPage({ ...query, page })
        pages[page - 1] = readPage(response, page, total)
      }
    },
  )
  await Promise.all(workers)
  return pages.flat()
}
