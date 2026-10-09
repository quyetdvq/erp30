import { expect, test } from '@playwright/test'

test.describe('production session CSRF lifecycle', () => {
  /* eslint-disable playwright/no-conditional-in-test */
  test('refreshes the CSRF token after logout before the next login', async ({ page }) => {
    if (!process.env.CI) return

    let csrfRequests = 0
    const loginTokens: string[] = []
    let logoutToken = ''

    await page.route('**/api/v1/auth/me', async (route) =>
      route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ error: { code: 'UNAUTHENTICATED' }, meta: {} }),
      }),
    )
    await page.route('**/api/v1/auth/csrf', async (route) => {
      csrfRequests += 1
      const token = `csrf-${csrfRequests}`
      await route.fulfill({
        status: 200,
        headers: { 'X-CSRF-TOKEN': token },
        contentType: 'application/json',
        body: JSON.stringify({
          data: { csrf_token: token, header_name: 'X-CSRF-TOKEN' },
          meta: {},
        }),
      })
    })
    await page.route('**/api/v1/auth/login', async (route) => {
      loginTokens.push(route.request().headers()['x-csrf-token'] ?? '')
      await route.fulfill({
        status: 200,
        headers: { 'X-CSRF-TOKEN': `csrf-login-${loginTokens.length}` },
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            user: {
              id: 7,
              username: 'quyetdv',
              email: 'quyetdv@example.test',
              active: true,
              status: 'ACTIVE',
              groups: ['orgadmin'],
              base_permissions: [],
            },
          },
          meta: {},
        }),
      })
    })
    await page.route('**/api/v1/auth/logout', async (route) => {
      logoutToken = route.request().headers()['x-csrf-token'] ?? ''
      await route.fulfill({
        status: 200,
        headers: { 'X-CSRF-TOKEN': 'csrf-logout' },
        contentType: 'application/json',
        body: JSON.stringify({ data: { ok: true }, meta: {} }),
      })
    })

    await page.goto('/login')
    await page.getByLabel(/Email hoặc tên tài khoản|Email or username|Định danh/).fill('quyetdv')
    await page.getByLabel('Mật khẩu').fill('secret')
    await page.getByRole('button', { name: /Đăng nhập|Sign in/ }).click()
    await expect(page).not.toHaveURL(/\/login$/)

    await page.getByRole('button', { name: /quyetdv/ }).click()
    await page.getByText(/Đăng xuất|Sign out|Logout/, { exact: true }).click()
    await expect(page).toHaveURL(/\/login$/)
    expect(logoutToken).toBe('csrf-2')

    await page.getByLabel(/Email hoặc tên tài khoản|Email or username|Định danh/).fill('quyetdv')
    await page.getByLabel('Mật khẩu').fill('secret')
    await page.getByRole('button', { name: /Đăng nhập|Sign in/ }).click()
    await expect(page).not.toHaveURL(/\/login$/)

    expect(csrfRequests).toBe(4)
    expect(loginTokens).toEqual(['csrf-1', 'csrf-3'])
  })
  /* eslint-enable playwright/no-conditional-in-test */
})
