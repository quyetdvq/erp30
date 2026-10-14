import { expect, test, type Page } from '@playwright/test'
import { messages } from '../src/i18n'
import { mockPagination } from './support/mockPagination'
import { mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

test.beforeEach(async ({ page }) => {
  await mockBusinessWorkflowStates(page)
})

const { vi, en } = messages

async function mockAuthenticatedSession(
  page: Page,
  excludedPermissions: string[] = [],
): Promise<void> {
  // Shell tests still mount OrganizationsView; never let its background GET
  // escape to a real API and invalidate the mocked user session.
  await page.route('**/api/v1/organizations/tree**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: mockPagination(route, 0, 'fe001-tree') }),
    }),
  )
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
            ].filter((permission) => !excludedPermissions.includes(permission)),
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
  await expect(page.getByText(vi.nav.organizations).first()).toBeVisible()
  await expect(page.locator('.workspace-tabs-wrap').getByText(vi.nav.organizations)).toBeVisible()

  await page.getByRole('button', { name: vi.shell.language }).click()
  await page.getByText('English', { exact: true }).click()
  await expect(page.getByText(en.nav.organizations).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'quyetdv' })).toBeVisible()
  await expect(page).toHaveURL(/\/organizations$/)
  await expect.poll(() => page.evaluate(() => localStorage.getItem('ui.locale'))).toBe('en')

  await page.getByRole('button', { name: en.shell.language }).click()
  await page.getByText('Tiếng Việt', { exact: true }).click()
  await expect(page.getByText(vi.nav.organizations).first()).toBeVisible()
  await expect.poll(() => page.evaluate(() => localStorage.getItem('ui.locale'))).toBe('vi')

  await page.goto('/missing-fe001-route')
  await expect(page.getByRole('main').getByText(vi.errors.status['404'])).toBeVisible()
  await expect(page.getByRole('button', { name: vi.errors.backToWorkspace })).toBeVisible()
})

test('shell keeps organization structure routes in the Organization mode', async ({ page }) => {
  await mockAuthenticatedSession(page)
  await page.setViewportSize({ width: 1280, height: 420 })
  await page.goto('/organizations')

  const menu = page.locator('.shell-sider .n-menu')
  await expect(menu.getByRole('menuitem', { name: vi.nav.organizations })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.displaySettings })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.securityUsers })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.authorizationGroups })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.resources })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.policies })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.authorizationLogs })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.workflowStates })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.lifecycles })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.locations })).toHaveCount(0)
  await expect(menu.getByRole('menuitem', { name: vi.nav.departments })).toHaveCount(0)

  await page.getByRole('button', { name: vi.shell.modes.organization }).click()
  await expect(menu.getByRole('menuitem', { name: vi.nav.locations })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.wards, exact: true })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.departments })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.positions })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.employees })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.organizations })).toBeVisible()

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

  await page.getByRole('button', { name: vi.shell.modes.system }).click()
  await expect(menu.getByRole('menuitem', { name: vi.nav.displaySettings })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.securityUsers })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.authorizationGroups })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.resources })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.policies })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.authorizationLogs })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.workflowStates })).toBeVisible()
  await expect(menu.getByRole('menuitem', { name: vi.nav.lifecycles })).toBeVisible()
})

test('Favorites only shows explicitly starred menus, persists across reload and never navigates when starring', async ({
  page,
}) => {
  await mockAuthenticatedSession(page)
  await page.goto('/organizations')
  const sidebar = page.locator('.shell-sider')
  await page.getByRole('button', { name: vi.shell.modes.favorites, exact: true }).click()
  await expect(sidebar.getByText(vi.shell.emptyFavorites)).toBeVisible()
  await expect(sidebar.locator('.n-menu')).toHaveCount(0)

  await page.getByRole('button', { name: vi.shell.modes.organization }).click()
  await page
    .getByRole('button', { name: vi.shell.addFavorite.replace('{name}', vi.nav.wards) })
    .click()
  await expect(page).toHaveURL(/\/organizations$/)

  await page.getByRole('button', { name: vi.shell.modes.menu }).click()
  await page
    .getByRole('button', { name: vi.shell.addFavorite.replace('{name}', vi.nav.dashboard) })
    .click()
  await expect(page).toHaveURL(/\/organizations$/)

  await page.getByRole('button', { name: vi.shell.modes.favorites, exact: true }).click()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.wards, exact: true })).toBeVisible()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.dashboard, exact: true })).toBeVisible()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.organizations })).toHaveCount(0)

  // Clicking the label targets navigation, not the nested favorite-toggle button.
  await sidebar
    .getByRole('menuitem', { name: vi.nav.dashboard, exact: true })
    .getByText(vi.nav.dashboard, { exact: true })
    .click()
  await expect(page).toHaveURL(/\/$/)
  await page.reload()
  await expect(
    page.getByRole('button', { name: vi.shell.modes.favorites, exact: true }),
  ).toHaveAttribute('aria-pressed', 'true')
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.wards, exact: true })).toBeVisible()

  await page
    .getByRole('button', { name: vi.shell.removeFavorite.replace('{name}', vi.nav.wards) })
    .click()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.wards })).toHaveCount(0)
  await page
    .getByRole('button', { name: vi.shell.removeFavorite.replace('{name}', vi.nav.dashboard) })
    .click()
  await expect(sidebar.getByText(vi.shell.emptyFavorites)).toBeVisible()
})

test('Recent is visited history, not a duplicate Favorites/menu list', async ({ page }) => {
  await mockAuthenticatedSession(page)
  await page.goto('/organizations')
  const sidebar = page.locator('.shell-sider')
  await page.getByRole('button', { name: vi.shell.modes.recent }).click()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.organizations })).toBeVisible()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.wards })).toHaveCount(0)

  await page.goto('/profile')
  await expect(sidebar.getByRole('menuitem', { name: vi.shell.profile, exact: true })).toBeVisible()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.organizations })).toBeVisible()
  await expect(sidebar.locator('.n-menu-item')).toHaveCount(2)

  await page.reload()
  await expect(sidebar.locator('.n-menu-item')).toHaveCount(2)
  await page.getByRole('button', { name: vi.shell.modes.favorites, exact: true }).click()
  await expect(sidebar.getByText(vi.shell.emptyFavorites)).toBeVisible()
})

test('Favorites cannot reveal routes after Base Permission is revoked', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem(
      'ui.sidebar.favorites.7',
      JSON.stringify(['/security/policies', '/organizations']),
    )
  })
  await mockAuthenticatedSession(page, ['policy.view'])
  await page.goto('/organizations')
  await page.getByRole('button', { name: vi.shell.modes.favorites, exact: true }).click()
  const sidebar = page.locator('.shell-sider')
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.organizations })).toBeVisible()
  await expect(sidebar.getByRole('menuitem', { name: vi.nav.policies })).toHaveCount(0)
})

test('removed menu routes resolve to the not-found boundary', async ({ page }) => {
  await mockAuthenticatedSession(page)

  for (const path of ['/approvals', '/sales']) {
    await page.goto(path)
    await expect(page.getByRole('main').getByText(vi.errors.status['404'])).toBeVisible()
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

test('compact header keeps Search, Language and User as icons without wrapping', async ({
  page,
}) => {
  await mockAuthenticatedSession(page)
  await page.goto('/organizations')

  for (const width of [768, 390, 320]) {
    await page.setViewportSize({ width, height: 720 })
    await expect(page.locator('.workspace-search-label')).toBeHidden()
    await expect(page.locator('.workspace-search kbd')).toBeHidden()
    await expect(page.locator('.header-language-label')).toBeHidden()
    await expect(page.locator('.header-language-chevron')).toBeHidden()
    await expect(page.locator('.header-language-icon')).toBeVisible()
    await expect(page.locator('.header-user-label')).toBeHidden()
    await expect(page.locator('.header-user-chevron')).toBeHidden()

    const search = page.getByRole('button', { name: vi.shell.searchWorkspace, exact: true })
    const language = page.getByRole('button', { name: vi.shell.language, exact: true })
    const user = page.getByRole('button', { name: 'quyetdv', exact: true })
    await expect(search).toBeVisible()
    await expect(language).toBeVisible()
    await expect(user).toBeVisible()

    const bounds = await page.locator('.shell-header').evaluate((element) => {
      const header = element.getBoundingClientRect()
      const buttons = Array.from(element.querySelectorAll('button')).map((button) => {
        const box = button.getBoundingClientRect()
        return { left: box.left, right: box.right, top: box.top, bottom: box.bottom }
      })
      return {
        viewportWidth: window.innerWidth,
        header: {
          left: header.left,
          right: header.right,
          top: header.top,
          bottom: header.bottom,
        },
        buttons,
      }
    })
    expect(bounds.header.right).toBeLessThanOrEqual(bounds.viewportWidth + 1)
    expect(bounds.buttons).toHaveLength(6)

    // Measure the seven CSS Grid items, not nested controls. The notification
    // button lives inside NBadge, whose counter can offset the button bounds.
    const gridCenters = await page.locator('.shell-header').evaluate((header) => {
      const gridItems = Array.from(header.children).flatMap((group) =>
        getComputedStyle(group).display === 'contents' ? Array.from(group.children) : [group],
      )
      return gridItems.map((item) => {
        const box = item.getBoundingClientRect()
        return box.left + box.width / 2
      })
    })
    expect(gridCenters).toHaveLength(7)
    const slotWidth = (bounds.header.right - bounds.header.left - 16) / 7
    const slotNames = ['Logo', 'Menu', 'Search', 'Notifications', 'Theme', 'Language', 'User']
    gridCenters.forEach((center, index) => {
      const expectedCenter = bounds.header.left + 8 + slotWidth * (index + 0.5)
      expect(
        Math.abs(center - expectedCenter),
        `${slotNames[index]} grid item must be centered in its header column`,
      ).toBeLessThanOrEqual(3)
    })
    expect(
      bounds.buttons.every(
        (box) =>
          box.left >= bounds.header.left - 1 &&
          box.right <= bounds.header.right + 1 &&
          box.top >= bounds.header.top - 1 &&
          box.bottom <= bounds.header.bottom + 1,
      ),
    ).toBe(true)
  }

  await page.setViewportSize({ width: 1280, height: 720 })
  await expect(page.locator('.workspace-search-label')).toBeVisible()
  await expect(page.locator('.header-language-label')).toBeVisible()
  await expect(page.locator('.header-user-label')).toBeVisible()
  await expect(page.locator('.header-language-icon')).toBeHidden()
})

test('compact header icon buttons preserve search, locale and account actions', async ({
  page,
}) => {
  await mockAuthenticatedSession(page)
  await page.setViewportSize({ width: 390, height: 720 })
  await page.goto('/organizations')

  const search = page.getByRole('button', { name: vi.shell.searchWorkspace, exact: true })
  await search.click()
  await expect(page.getByText(vi.shell.commandPalette)).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByText(vi.shell.commandPalette)).toBeHidden()

  await page.getByRole('button', { name: vi.shell.language, exact: true }).click()
  await page.getByText('English', { exact: true }).click()
  await expect.poll(() => page.evaluate(() => document.documentElement.lang)).toBe('en')
  await page.getByRole('button', { name: 'quyetdv', exact: true }).click()
  await expect(page.getByText(en.shell.profile, { exact: true })).toBeVisible()
})

test('collapsed shell keeps only five mode icons and shell actions work', async ({ page }) => {
  await mockAuthenticatedSession(page)
  await page.goto('/organizations')
  await expect(page.locator('.sider-collapse')).toBeVisible()
  await expect
    .poll(async () => (await page.locator('.shell-sider').boundingBox())?.width ?? 0)
    .toBeGreaterThan(0)

  await page
    .getByRole('button', { name: vi.shell.collapseSidebar })
    .evaluate((element) => element.click())
  await expect(page.locator('.mode-switcher button')).toHaveCount(5)
  await expect(page.locator('.shell-sider .n-menu')).toHaveCount(0)
  await expect(page.getByPlaceholder(vi.shell.searchMenu)).toHaveCount(0)

  await page.getByRole('button', { name: vi.shell.theme }).dispatchEvent('click')
  await expect(page.locator('.erp-shell')).toHaveClass(/theme-dark/)
  await page.getByRole('button', { name: vi.shell.theme }).dispatchEvent('click')
  await expect(page.locator('.erp-shell')).not.toHaveClass(/theme-dark/)

  await page.getByRole('button', { name: vi.shell.notifications }).dispatchEvent('click')
  await expect(page.getByText(vi.shell.notificationWorkspace)).toBeVisible()
  await page
    .getByRole('button', { name: vi.shell.markAllRead })
    .evaluate((element) => element.click())
  await expect(page.getByRole('button', { name: vi.shell.allRead })).toBeVisible()

  await page.getByRole('button', { name: vi.shell.searchWorkspace }).click()
  await expect(page.getByText(vi.shell.commandPalette)).toBeVisible()
  await expect(page.getByPlaceholder(vi.shell.searchCommand)).toBeVisible()
})

test('collapsed sidebar stacks five modes vertically without overlap and exposes expand action', async ({
  page,
}) => {
  await mockAuthenticatedSession(page)
  await page.setViewportSize({ width: 1280, height: 720 })
  await page.goto('/organizations')

  await page.getByRole('button', { name: vi.shell.collapseSidebar }).click()
  const sider = page.locator('.shell-sider')
  await expect(sider).toHaveClass(/sider-collapsed/)
  const buttons = page.locator('.mode-switcher button')
  await expect(buttons).toHaveCount(5)
  // Naive UI animates the sider width after the collapsed class is applied.
  // Wait for the 72px target and measure the sider and buttons in the same frame.
  await expect
    .poll(async () => (await sider.boundingBox())?.width ?? Number.POSITIVE_INFINITY)
    .toBeLessThanOrEqual(73)
  const geometry = await sider.evaluate((element) => {
    const rect = element.getBoundingClientRect()
    const boxes = Array.from(element.querySelectorAll('.mode-switcher button')).map((button) => {
      const box = button.getBoundingClientRect()
      return { left: box.left, right: box.right, top: box.top, bottom: box.bottom }
    })
    return { left: rect.left, right: rect.right, boxes }
  })
  const { boxes } = geometry
  expect(boxes).toHaveLength(5)
  expect(
    boxes.every((box) => box.left >= geometry.left - 1 && box.right <= geometry.right + 1),
  ).toBe(true)
  expect(boxes.slice(1).every((box, index) => box.top >= boxes[index]!.bottom)).toBe(true)
  expect(boxes.every((box) => Math.abs(box.left - boxes[0]!.left) <= 1)).toBe(true)

  const activeColor = await buttons
    .first()
    .evaluate((node) => getComputedStyle(node).backgroundColor)
  const inactiveColor = await buttons
    .nth(1)
    .evaluate((node) => getComputedStyle(node).backgroundColor)
  expect(activeColor).not.toBe(inactiveColor)

  const expand = page.getByRole('button', { name: vi.shell.expandSidebar })
  await expect(expand).toBeVisible()
  await expand.click()
  await expect(sider).not.toHaveClass(/sider-collapsed/)
  await expect(page.getByPlaceholder(vi.shell.searchMenu)).toBeVisible()
})

test('sidebar drag resizes smoothly with one persisted width and keyboard resize still works', async ({
  page,
}) => {
  await mockAuthenticatedSession(page)
  await page.setViewportSize({ width: 1280, height: 720 })
  await page.goto('/organizations')

  const sider = page.locator('.shell-sider')
  const handle = page.locator('.sider-resize')
  await expect(handle).toBeVisible()
  const initialWidth = (await sider.boundingBox())!.width
  const handleBox = (await handle.boundingBox())!
  const x = handleBox.x + handleBox.width / 2
  const y = handleBox.y + 36
  await page.mouse.move(x, y)
  await page.mouse.down()
  await expect(page.locator('.erp-shell')).toHaveClass(/shell-resizing/)
  await page.mouse.move(x + 74, y, { steps: 8 })
  await expect
    .poll(async () => (await sider.boundingBox())?.width ?? 0)
    .toBeGreaterThan(initialWidth + 50)
  await page.mouse.up()
  await expect(page.locator('.erp-shell')).not.toHaveClass(/shell-resizing/)
  const resizedWidth = (await sider.boundingBox())!.width
  expect(resizedWidth).toBeGreaterThan(initialWidth + 50)

  await page.reload()
  await expect
    .poll(async () => (await sider.boundingBox())?.width ?? 0)
    .toBeGreaterThan(initialWidth + 50)
  await handle.focus()
  await handle.press('Home')
  await expect
    .poll(async () => (await sider.boundingBox())?.width ?? 0)
    .toBeLessThan(resizedWidth - 20)
})
