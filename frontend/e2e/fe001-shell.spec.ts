import { expect, test, type Page } from '@playwright/test'

async function mockAuthenticatedSession(page: Page): Promise<void> {
  await page.route('**/api/v1/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        user: {
          id: 7,
          username: 'quyetdv',
          email: 'quyetdv@example.test',
          active: true,
          status: 'ACTIVE',
          groups: ['orgadmin'],
        },
      }),
    })
  })
}

test('authenticated shell exposes five modes, deduplicated tabs, locale, and 404 boundary', async ({
  page,
}) => {
  await mockAuthenticatedSession(page)
  await page.goto('/products')

  await expect(page.locator('.mode-switcher button')).toHaveCount(5)
  await expect(page.getByText('Sản phẩm và vòng đời').first()).toBeVisible()
  await expect(page.locator('.workspace-tabs-wrap').getByText('Sản phẩm và vòng đời')).toBeVisible()

  await page.goto('/missing-fe001-route')
  await expect(page.getByRole('main').getByText('Không tìm thấy tài nguyên')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Về workspace' })).toBeVisible()
})
