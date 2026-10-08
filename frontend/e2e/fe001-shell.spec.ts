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
            base_permissions: [
              'organization.view',
              'province.view',
              'ward.view',
              'department.view',
              'position.view',
              'employee.view',
              'display_setting.view',
              'user.view',
              'authorization_group.view',
              'resource.view',
              'policy.view',
              'authorization_log.view',
              'workflow_state.view',
              'lifecycle.view',
              'product.view',
            ],
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
  await page.goto('/organizations')

  await expect(page.locator('.mode-switcher button')).toHaveCount(5)
  await expect(page.getByText('Tổ chức và dữ liệu nền').first()).toBeVisible()
  await expect(
    page.locator('.workspace-tabs-wrap').getByText('Tổ chức và dữ liệu nền'),
  ).toBeVisible()

  await page.getByRole('button', { name: 'Ngôn ngữ' }).click()
  await page.getByText('English', { exact: true }).click()
  await expect(page.getByText('Organizations and foundation data').first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'quyetdv' })).toBeVisible()
  await expect(page).toHaveURL(/\/organizations$/)
  await expect.poll(() => page.evaluate(() => localStorage.getItem('ui.locale'))).toBe('en')

  await page.getByRole('button', { name: 'Language' }).click()
  await page.getByText('Tiếng Việt', { exact: true }).click()
  await expect(page.getByText('Tổ chức và dữ liệu nền').first()).toBeVisible()
  await expect.poll(() => page.evaluate(() => localStorage.getItem('ui.locale'))).toBe('vi')

  await page.goto('/missing-fe001-route')
  await expect(page.getByRole('main').getByText('Không tìm thấy tài nguyên')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Về workspace' })).toBeVisible()
})

test('shell keeps organization structure routes in the Organization mode', async ({ page }) => {
  await mockAuthenticatedSession(page)
  await page.setViewportSize({ width: 1280, height: 420 })
  await page.goto('/organizations')

  const menu = page.locator('.shell-sider .n-menu')
  await expect(menu.getByRole('menuitem', { name: 'Tổ chức và dữ liệu nền' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Cấu hình hiển thị' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Người dùng và quyền trực tiếp' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Nhóm phân quyền' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Resource và Action' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Policy PBAC' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Nhật ký phân quyền' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Workflow State' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Lifecycles' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Tỉnh, phường/xã' })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: 'Phòng ban' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Cơ cấu tổ chức' }).click()
  await expect(menu.getByRole('menuitem', { name: 'Tỉnh, phường/xã' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Phường/Xã', exact: true })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Phòng ban' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Chức danh' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Nhân viên' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Tổ chức và dữ liệu nền' })).toBeVisible()

  const menuScroll = page.locator('.sider-menu-scroll')
  await expect(menuScroll).toBeVisible()
  const scrollMetrics = await menuScroll.evaluate((element) => {
    const style = getComputedStyle(element)
    return {
      overflowX: style.overflowX,
      overflowY: style.overflowY,
      scrollable: element.scrollHeight > element.clientHeight,
    }
  })
  expect(scrollMetrics.overflowX).toBe('hidden')
  expect(scrollMetrics.overflowY).toBe('auto')
  expect(scrollMetrics.scrollable).toBe(true)

  await page.getByRole('button', { name: 'Hệ thống' }).click()
  await expect(menu.getByRole('menuitem', { name: 'Cấu hình hiển thị' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Người dùng và quyền trực tiếp' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Nhóm phân quyền' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Resource và Action' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Policy PBAC' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Nhật ký phân quyền' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Workflow State' })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: 'Lifecycles' })).toBeVisible()
})

test('removed menu routes resolve to the not-found boundary', async ({ page }) => {
  await mockAuthenticatedSession(page)

  for (const path of ['/approvals', '/sales']) {
    await page.goto(path)
    await expect(page.getByRole('main').getByText('Không tìm thấy tài nguyên')).toBeVisible()
  }
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
