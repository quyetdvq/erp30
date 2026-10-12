import type { Page, Route } from '@playwright/test'

// Lifecycle metadata is loaded in the background by each business list.
// Without an explicit fixture these requests reach the real API; a 401 can
// expire the mocked session and cause unrelated shell/list assertions to fail.
// Keep the route narrow: individual tests must still mock record options and
// POST actions when they exercise a transition.
const lifecycleResources = new Set([
  'contact',
  'province',
  'ward',
  'organization',
  'department',
  'position',
  'employee',
  'product_group',
  'product_uom',
  'product',
  'price_list',
  'organization_contact',
  'employee_contact',
])

/** Match production credentialed API transport, including CORS preflight. */
export function fulfillWorkflowMock(
  route: Route,
  envelope: Record<string, unknown>,
  status = 200,
): Promise<void> {
  const headers = {
    'Access-Control-Allow-Origin': route.request().headers()['origin'] ?? 'http://localhost:5173',
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Allow-Methods': 'GET,POST,OPTIONS',
    'Access-Control-Allow-Headers': 'Accept,Accept-Language,Content-Type,X-Request-ID,X-CSRF-TOKEN',
  }
  if (route.request().method() === 'OPTIONS') return route.fulfill({ status: 204, headers })
  return route.fulfill({
    status,
    headers,
    contentType: 'application/json',
    body: JSON.stringify(envelope),
  })
}

export async function mockBusinessWorkflowStates(page: Page): Promise<void> {
  await page.route('**/api/v1/business-workflow/*/states', (route) => {
    const pathname = new URL(route.request().url()).pathname
    const resource = pathname.split('/').at(-2)
    if (!resource || !lifecycleResources.has(resource)) return route.fallback()
    if (route.request().method() !== 'GET' && route.request().method() !== 'OPTIONS') {
      return route.fallback()
    }

    return fulfillWorkflowMock(route, {
      data: {
        mode: 'LIFECYCLE',
        initial: 'DRAFT',
        states: ['DRAFT', 'ACTIVE', 'INACTIVE', 'CLOSED'],
      },
      meta: { request_id: `e2e-workflow-${resource}` },
    })
  })
}
