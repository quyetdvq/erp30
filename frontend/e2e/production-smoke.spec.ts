import { expect, test, type Route } from '@playwright/test'

async function mockResponse(route: Route, status: number, payload: unknown): Promise<void> {
  // The frontend may use an external API origin even in local preview.
  const requestOrigin = route.request().headers()['origin'] || 'http://127.0.0.1:4173'
  const headers = {
    'Access-Control-Allow-Origin': requestOrigin,
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'accept-language, x-request-id, content-type',
  }
  if (route.request().method() === 'OPTIONS') {
    await route.fulfill({ status: 204, headers })
    return
  }
  await route.fulfill({ status, headers, contentType: 'application/json', body: JSON.stringify(payload) })
}

// Any uncaught startup error (including cyclic chunk initialization) makes
// the production build invalid even when `vite build` reports success.
for (const path of ['/', '/login', '/notifications']) {
  test(`production bundle mounts Vue on ${path}`, async ({ page }) => {
    const runtimeErrors: string[] = []
    page.on('pageerror', (error) => runtimeErrors.push(error.message))

    // Run against a production build without depending on the live ERP API.
    await page.route('**/api/v1/auth/me', (route) =>
      mockResponse(route, 401, { message: 'Unauthenticated' }),
    )

    const response = await page.goto(path)
    expect(response?.status()).toBe(200)
    await expect(page.locator('#app .auth-layout')).toBeVisible()
    await expect(page.locator('#app h1')).toHaveText('Đăng nhập workspace')
    expect(runtimeErrors, 'Uncaught browser errors during SPA startup').toEqual([])
  })
}

// Exercise the M11 lazy route under a mocked authenticated session as well.
test('production Notifications Inbox mounts without a live backend', async ({ page }) => {
  const runtimeErrors: string[] = []
  page.on('pageerror', (error) => runtimeErrors.push(error.message))
  await page.route('**/api/v1/auth/me', (route) =>
    mockResponse(route, 200, {
      data: { user: {
        id: 7, username: 'smoke.user', name: 'Smoke User', email: 'smoke@example.test',
        active: true, status: 'ACTIVE', groups: [], base_permissions: [],
        has_avatar: false,
      } },
    }),
  )
  await page.route('**/api/v1/notifications/summary', (route) =>
    mockResponse(route, 200, { data: { unread_count: 0 } }),
  )
  await page.route(/\/api\/v1\/notifications(?:\?|$)/, (route) =>
    mockResponse(route, 200, { data: { items: [], next_cursor: null } }),
  )

  const response = await page.goto('/notifications')
  expect(response?.status()).toBe(200)
  await expect(page.locator('.m11-inbox h1')).toHaveText('Hộp thư thông báo')
  expect(runtimeErrors, 'Uncaught browser errors in the Inbox route').toEqual([])
})
