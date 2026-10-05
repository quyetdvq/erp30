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
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
  await page.getByRole('button', { name: 'Ngôn ngữ' }).dispatchEvent('click')
  await expect(page.locator('h1')).toHaveText('Sign in to workspace')
  await page.reload()
  await expect(page.locator('h1')).toHaveText('Sign in to workspace')
  expect(lastLanguage).toBe('en')
})
