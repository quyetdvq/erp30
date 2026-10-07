import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('loads the login shell and switches locale', async ({ page }) => {
  let lastLanguage = ''
  await page.route('**/api/v1/auth/me', async (route) => {
    lastLanguage = route.request().headers()['accept-language'] || ''
    await route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'Unauthenticated' }),
    })
  })
  await page.goto('/')
  await expect(page.locator('h1')).toHaveText('Đăng nhập workspace')
  const viewportFits = await page.evaluate(
    () => document.documentElement.scrollHeight <= window.innerHeight,
  )
  expect(viewportFits).toBe(true)
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
  await page.getByRole('button', { name: 'Ngôn ngữ' }).dispatchEvent('click')
  await expect(page.locator('h1')).toHaveText('Sign in to workspace')
  await page.reload()
  await expect(page.locator('h1')).toHaveText('Sign in to workspace')
  expect(lastLanguage).toBe('en')
})

test.describe('compact login viewport', () => {
  test.use({ viewport: { width: 1280, height: 600 } })

  test('fits without vertical scroll', async ({ page }) => {
    await page.route('**/api/v1/auth/me', async (route) => {
      await route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Unauthenticated' }),
      })
    })

    await page.goto('/login')
    await expect(page.locator('.auth-layout')).toBeVisible()
    const fitsViewport = await page.evaluate(
      () => document.documentElement.scrollHeight <= window.innerHeight,
    )
    expect(fitsViewport).toBe(true)
  })
})

test('redirects to the workspace after a successful login', async ({ page }) => {
  await page.route('**/api/v1/auth/me', async (route) => {
    await route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ error: { code: 'UNAUTHENTICATED', message: 'Unauthenticated' } }),
    })
  })
  await page.route('**/api/v1/auth/login', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: { 'X-Request-ID': 'login-test-request' },
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'workspace.user',
            email: 'workspace.user@example.com',
            active: true,
            status: 'ACTIVE',
            groups: ['workspace'],
          },
        },
        meta: { request_id: 'login-test-request' },
      }),
    })
  })

  await page.goto('/login')
  await page
    .getByRole('textbox', { name: 'Email hoặc tên tài khoản' })
    .fill('workspace.user@example.com')
  await page.getByRole('textbox', { name: 'Mật khẩu' }).fill('correct-password')
  await page.getByRole('button', { name: 'Đăng nhập' }).evaluate((element) => element.click())

  await expect(page).toHaveURL(/\/$/)
  await expect(page.getByRole('heading', { name: 'Tổng quan' })).toBeVisible()
  await expect(page.getByText('Không gian làm việc của workspace.user.')).toBeVisible()
})
