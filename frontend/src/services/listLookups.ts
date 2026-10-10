import {
  createListQuery,
  MAX_LIST_PAGE_SIZE,
  type ListQuery,
  type ListResponse,
} from '@/components/lists/listTypes'

// Limit total lookup traffic across concurrently mounted selectors, not only
// requests within a single selector. Ordinary list/detail requests are not gated.
const MAX_CONCURRENT_LOOKUP_REQUESTS = 4
let activeLookupRequests = 0
const waitingLookupRequests: Array<() => void> = []

async function withLookupRequestSlot<T>(request: () => Promise<T>): Promise<T> {
  if (activeLookupRequests >= MAX_CONCURRENT_LOOKUP_REQUESTS) {
    await new Promise<void>((resolve) => waitingLookupRequests.push(resolve))
  } else {
    activeLookupRequests += 1
  }

  try {
    return await request()
  } finally {
    // Transfer the existing slot directly to the next waiter. Decrementing
    // before waking it would briefly allow a fifth request to start.
    const next = waitingLookupRequests.shift()
    if (next) next()
    else activeLookupRequests -= 1
  }
}

/**
 * Read an entire authorization-filtered paginated lookup. Never present a
 * truncated selector as if it were complete. For larger catalogs, use a
 * server-side searchable selector instead of raising maxRows.
 *
 * Page one establishes the total. Later pages run concurrently with a shared
 * four-request budget across all lookups, and are stored in page order.
 * Metadata validation cannot provide a database snapshot if rows are edited
 * concurrently without changing the total.
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

  // Include page one in the global budget: several selectors often mount at once.
  const firstResponse = await withLookupRequestSlot(() => listPage(query))
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
  let stopped = false
  const workers = Array.from(
    { length: Math.min(MAX_CONCURRENT_LOOKUP_REQUESTS, pageCount - 1) },
    async () => {
      while (!stopped && nextPage <= pageCount) {
        // Claim synchronously so two workers cannot fetch the same page.
        const page = nextPage++
        try {
          const response = await withLookupRequestSlot(() => listPage({ ...query, page }))
          pages[page - 1] = readPage(response, page, total)
        } catch (error: unknown) {
          stopped = true
          throw error
        }
      }
    },
  )
  // Wait for all already-started workers to settle even after a failure. This
  // prevents an abandoned lookup from continuing to schedule page requests.
  const results = await Promise.allSettled(workers)
  const failure = results.find((result) => result.status === 'rejected')
  if (failure?.status === 'rejected') throw failure.reason
  return pages.flat()
}
