import type { Page, Route } from '@playwright/test'

/**
 * Mock only the session and shell traffic needed by Auto Numbering browser
 * tests. On a development machine, Vite may enable M11 inbox polling; an
 * unmocked /notifications/actions/sync may return 401 from the live API and
 * expire the fake session before the numbering table finishes loading.
 *
 * CORS preflights and credentialed responses must be handled as well: the
 * app's canonical API client sends X-Request-ID and Accept-Language headers.
 */
function fixtureHeaders(route: Route): Record<string, string> {
  const origin = route.request().headers()['origin'] || 'http://localhost:5173'
  return {
    'Access-Control-Allow-Origin': origin,
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Allow-Methods': 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Accept, Accept-Language, Authorization, Content-Type, X-CSRF-TOKEN, X-Request-ID, X-Requested-With',
    'Access-Control-Expose-Headers': 'X-CSRF-TOKEN, X-Request-ID',
    'X-CSRF-TOKEN': 'auto-numbering-e2e-csrf',
    Vary: 'Origin',
  }
}

export function fulfillAutoNumberingFixture(
  route: Route,
  options: Parameters<Route['fulfill']>[0] = {},
): Promise<void> {
  if (route.request().method() === 'OPTIONS') {
    return route.fulfill({ status: 204, headers: fixtureHeaders(route) })
  }
  return route.fulfill({
    contentType: 'application/json',
    ...options,
    headers: { ...fixtureHeaders(route), ...(options?.headers ?? {}) },
  })
}

export async function mockAutoNumberingSession(
  page: Page,
  options: { permissions?: string[]; authDelayMs?: number } = {},
): Promise<void> {
  const permissions = options.permissions ?? ['resource.view', 'resource.configure_numbering']
  // The mock session must never accidentally contact the live API. Unexpected
  // calls become explicit test fixture errors instead of a misleading 401
  // that expires the fake session and empties every Auto Numbering table.
  await page.route('**/api/v1/**', (route) =>
    fulfillAutoNumberingFixture(route, {
      status: 501,
      body: JSON.stringify({ error: {
        code: 'E2E_UNMOCKED_API',
        message: `Unmocked endpoint: ${route.request().method()} ${new URL(route.request().url()).pathname}`,
      } }),
    }),
  )
  await page.route('**/api/v1/display-settings/runtime**', (route) =>
    fulfillAutoNumberingFixture(route, {
      status: 200,
      body: JSON.stringify({ data: {
        timezone: 'Asia/Ho_Chi_Minh',
        date: { format: 'DD/MM/YYYY', date_time_format: 'DD/MM/YYYY HH:mm', time_format: 'HH:mm', first_day_of_week: 'MONDAY' },
        number: { decimal_separator: '.', group_separator: ',', quantity_decimals: 3, percentage_decimals: 3, trailing_zeros: false },
        money: { decimal_separator: '.', group_separator: ',', symbol_mode: 'SYMBOL', symbol_position: 'AUTO', decimals_mode: 'CURRENCY_DEFAULT' },
      } }),
    }),
  )
  await page.route('**/api/v1/auth/csrf**', (route) =>
    fulfillAutoNumberingFixture(route, {
      status: 200,
      body: JSON.stringify({ data: { csrf_token: 'auto-numbering-e2e-csrf', header_name: 'X-CSRF-TOKEN' } }),
    }),
  )
  await page.route('**/api/v1/auth/me**', async (route) => {
    if (options.authDelayMs) await new Promise((resolve) => setTimeout(resolve, options.authDelayMs))
    return fulfillAutoNumberingFixture(route, {
      status: 200,
      body: JSON.stringify({ data: {
        user: {
          id: 7,
          username: 'numbering-admin',
          email: 'numbering@example.test',
          active: true,
          status: 'ACTIVE',
          groups: [],
          base_permissions: permissions,
        },
      } }),
    })
  })
  await page.route('**/api/v1/notifications**', (route) => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/actions/sync')) {
      return fulfillAutoNumberingFixture(route, {
        status: 200,
        body: JSON.stringify({ data: { status: 'ok', processed: 0 } }),
      })
    }
    if (path.endsWith('/summary')) {
      return fulfillAutoNumberingFixture(route, {
        status: 200,
        body: JSON.stringify({ data: { unread_count: 0 } }),
      })
    }
    if (path === '/api/v1/notifications' && route.request().method() === 'GET') {
      return fulfillAutoNumberingFixture(route, {
        status: 200,
        body: JSON.stringify({ data: { items: [], next_cursor: null } }),
      })
    }
    return route.fallback()
  })
}

/** Register LAST so OPTIONS is resolved before any Resource-specific handler. */
export async function mockAutoNumberingPreflight(page: Page): Promise<void> {
  await page.route('**/api/v1/**', (route) =>
    route.request().method() === 'OPTIONS'
      ? fulfillAutoNumberingFixture(route)
      : route.fallback(),
  )
}
