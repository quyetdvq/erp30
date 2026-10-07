import { expect, test, type Page } from '@playwright/test'

async function mockAuthenticatedSession(page: Page): Promise<void> {
  await page.route('**/api/v1/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
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
          },
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

test('shell keeps the header full width and uses the official logo', async ({ page }) => {
  await mockAuthenticatedSession(page)
  await page.goto('/organizations')
  await expect(page.locator('.shell-header')).toBeVisible()
  await expect(page.locator('.shell-sider')).toBeVisible()
  await expect
    .poll(async () => (await page.locator('.shell-header').boundingBox())?.width ?? 0)
    .toBeGreaterThan(0)

  const geometry = await page.evaluate(() => {
    const header = document.querySelector('.shell-header')?.getBoundingClientRect()
    const sider = document.querySelector('.shell-sider')?.getBoundingClientRect()
    const workspace = document.querySelector('.workspace-layout')?.getBoundingClientRect()
    return {
      viewportWidth: window.innerWidth,
      headerWidth: header?.width || 0,
      headerHeight: header?.height || 0,
      siderLeft: sider?.left || 0,
      workspaceLeft: workspace?.left || 0,
    }
  })

  expect(geometry.headerWidth).toBe(geometry.viewportWidth)
  expect(geometry.headerHeight).toBeGreaterThan(0)
  expect(geometry.siderLeft).toBe(0)
  expect(geometry.workspaceLeft).toBeGreaterThan(geometry.siderLeft)
  const logo = page.locator('.shell-brand-logo')
  await expect(logo).toHaveAttribute('alt', 'Dinktech')
  expect(await logo.evaluate((image) => (image as HTMLImageElement).naturalWidth)).toBeGreaterThan(
    0,
  )
})

test('collapsed shell keeps only five mode icons and shell actions work', async ({ page }) => {
  await mockAuthenticatedSession(page)
  await page.goto('/organizations')
  await expect(page.locator('.sider-collapse')).toBeVisible()
  await expect
    .poll(async () => (await page.locator('.shell-sider').boundingBox())?.width ?? 0)
    .toBeGreaterThan(0)

  await page.getByRole('button', { name: 'Thu gọn sidebar' }).evaluate((element) => element.click())
  await expect(page.locator('.mode-switcher button')).toHaveCount(5)
  await expect(page.locator('.shell-sider .n-menu')).toHaveCount(0)
  await expect(page.getByPlaceholder('Tìm menu')).toHaveCount(0)

  await page.getByRole('button', { name: 'Đổi giao diện' }).dispatchEvent('click')
  await expect(page.locator('.erp-shell')).toHaveClass(/theme-dark/)
  await page.getByRole('button', { name: 'Đổi giao diện' }).dispatchEvent('click')
  await expect(page.locator('.erp-shell')).not.toHaveClass(/theme-dark/)

  await page.getByRole('button', { name: 'Thông báo' }).dispatchEvent('click')
  await expect(page.getByText('Workspace đã sẵn sàng')).toBeVisible()
  await page.getByRole('button', { name: 'Đánh dấu đã đọc' }).evaluate((element) => element.click())
  await expect(page.getByRole('button', { name: 'Đã đọc hết' })).toBeVisible()

  await page.getByRole('button', { name: /Tìm trong workspace/ }).dispatchEvent('click')
  await expect(page.getByText('Command palette')).toBeVisible()
  await expect(page.getByPlaceholder('Tìm lệnh')).toBeVisible()
})
