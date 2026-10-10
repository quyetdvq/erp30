import type { Route } from '@playwright/test'

/** Keep mocked list metadata consistent with the actual paginated API query. */
export function mockPagination(route: Route, total: number, requestId?: string) {
  const query = new URL(route.request().url()).searchParams
  const page = Number(query.get('page') ?? 1)
  const pageSize = Number(query.get('page_size') ?? 50)
  return {
    page,
    page_size: pageSize,
    total,
    ...(requestId ? { request_id: requestId } : {}),
  }
}
