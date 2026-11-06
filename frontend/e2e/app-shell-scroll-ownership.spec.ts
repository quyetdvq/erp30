/**
 * CSS-A02-HF09: Compare the REAL People/Positions and People/Departments
 * routes mounted inside the actual Naive AppShell, not an isolated table.
 * The live browser traces showed one 46.4px workspace scroll with four rows
 * and a 48px decorative bottom gutter. This suite checks that short lists do
 * not create that scroll range, while long and narrow lists remain usable.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'
import { mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

type PeopleListKind = 'positions' | 'departments'
const kinds: PeopleListKind[] = ['positions', 'departments']
const kindPath = (kind: PeopleListKind) => `/people/${kind}`

function records(kind: PeopleListKind, length: number): Record<string, unknown>[] {
  const codes = kind === 'positions' ? ['GD', 'NV', 'PP', 'TP'] : ['KD', 'KT', 'HC', 'NS']
  const labels = kind === 'positions'
    ? ['Giám Đốc', 'Nhân viên', 'Phó phòng', 'Trưởng Phòng']
    : ['Kinh doanh', 'Kế toán', 'Hành chính', 'Nhân sự']
  return Array.from({ length }, (_, index) => ({
    id: String(100 + index),
    code: codes[index % 4] + (index >= 4 ? `-${index}` : ''),
    name: labels[index % 4] + (index >= 4 ? ` ${index}` : ''),
    status: 'ACTIVE',
    ...(kind === 'positions' ? { level: 1 } : { parent_id: null }),
    allowed_actions: { edit: true, setstate: true },
  }))
}

async function mockPeopleList(page: Page, kind: PeopleListKind, rowCount: number): Promise<void> {
  await mockBusinessWorkflowStates(page)
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: { user: {
      id: 7,
      username: 'hf09-appshell-qa',
      groups: ['orgadmin'],
      base_permissions: [
        'position.view', 'position.create', 'position.edit', 'position.setstate',
        'department.view', 'department.create', 'department.edit', 'department.setstate',
      ],
    } } }),
  }))
  const data = records(kind, rowCount)
  await page.route(`**/api/v1/${kind}**`, (route: Route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data, meta: mockPagination(route, rowCount, `hf09-${kind}`) }),
  }))
}

async function shellMetrics(page: Page) {
  return page.evaluate(() => {
    const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
    const main = document.querySelector<HTMLElement>('.workspace-content')!
    const tableWrap = document.querySelector<HTMLElement>('.list-table-wrap')!
    const ownerCss = getComputedStyle(owner)
    const mainCss = getComputedStyle(main)
    return {
      ownerOverflowY: ownerCss.overflowY,
      ownerGutter: ownerCss.scrollbarGutter,
      ownerMaxScroll: Math.max(0, owner.scrollHeight - owner.clientHeight),
      ownerScrollTop: owner.scrollTop,
      mainOverflowX: mainCss.overflowX,
      mainOverflowY: mainCss.overflowY,
      mainDisplay: mainCss.display,
      mainPaddingBottom: mainCss.paddingBottom,
      mainMaxScroll: Math.max(0, main.scrollHeight - main.clientHeight),
      tableOverflowX: getComputedStyle(tableWrap).overflowX,
      tableHorizontalRange: Math.max(0, tableWrap.scrollWidth - tableWrap.clientWidth),
      tableVerticalRange: Math.max(0, tableWrap.scrollHeight - tableWrap.clientHeight),
    }
  })
}

function expectSingleOwner(metrics: Awaited<ReturnType<typeof shellMetrics>>): void {
  expect(metrics.ownerOverflowY).toBe('auto')
  expect(metrics.ownerGutter).toBe('stable')
  expect(metrics.mainOverflowX).toBe('clip')
  expect(metrics.mainOverflowY).toBe('visible')
  expect(metrics.mainDisplay).toBe('flow-root')
  expect(metrics.mainPaddingBottom).toBe('0px')
  expect(metrics.mainMaxScroll).toBe(0)
  expect(metrics.tableOverflowX).toBe('auto')
  expect(metrics.tableVerticalRange).toBe(0)
}

// Actual DEV geometry: 1528x732 CSS pixels, DPR 1.25, four rows.
// Both pages must follow the same rule; a compact list should have no room
// to jump by 46.4px even if some input requests scroll-to-bottom.
for (const kind of kinds) {
  for (const theme of ['light', 'dark'] as const) {
    test(`${kind} ${theme}: 4 rows at DEV viewport cannot scroll workspace by 46.4px`, async ({ page }) => {
      await page.setViewportSize({ width: 1528, height: 732 })
      await mockPeopleList(page, kind, 4)
      await page.goto(kindPath(kind))
      await page.evaluate((value) => { document.documentElement.dataset.dsTheme = value }, theme)
      const rows = page.locator('.list-table tbody tr')
      await expect(rows).toHaveCount(4)
      const before = await shellMetrics(page)
      expectSingleOwner(before)
      expect(before.ownerMaxScroll).toBeLessThanOrEqual(1)
      await page.evaluate(() => {
        document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!.scrollTop = 46.4
      })
      expect((await shellMetrics(page)).ownerScrollTop).toBeLessThanOrEqual(1)
      const first = rows.nth(0).locator('td').first()
      const second = rows.nth(1).locator('td').first()
      for (let i = 0; i < 5; i++) {
        await first.hover()
        await second.hover()
        expect((await shellMetrics(page)).ownerScrollTop).toBeLessThanOrEqual(1)
      }
    })
  }
}

// Avoid "fixing" the compact case by disabling legitimate scrolling for
// long lists. The same Naive owner must still reach the bottom of 15 rows.
for (const kind of kinds) {
  test(`${kind}: 15-row table scrolls vertically through NLayout (not main)`, async ({ page }) => {
    await page.setViewportSize({ width: 1528, height: 732 })
    await mockPeopleList(page, kind, 15)
    await page.goto(kindPath(kind))
    await expect(page.locator('.list-table tbody tr')).toHaveCount(15)
    const initial = await shellMetrics(page)
    expectSingleOwner(initial)
    expect(initial.ownerMaxScroll).toBeGreaterThan(150)
    const scrolled = await page.evaluate(() => {
      const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
      owner.scrollTop = owner.scrollHeight
      return owner.scrollTop
    })
    expect(scrolled).toBeGreaterThan(150)
    await expect(page.locator('.list-table tbody tr').last()).toBeVisible()
    await expect(page.locator('.list-pagination')).toBeInViewport()
  })
}

for (const kind of kinds) {
  test(`${kind}: mobile keeps horizontal table scroll without a second vertical main scroll`, async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 771 })
    await mockPeopleList(page, kind, 4)
    await page.goto(kindPath(kind))
    await expect(page.locator('.list-table tbody tr')).toHaveCount(4)
    const initial = await shellMetrics(page)
    expectSingleOwner(initial)
    expect(initial.tableHorizontalRange).toBeGreaterThan(0)
    await page.locator('.list-table-wrap').evaluate((element) => { element.scrollLeft = 90 })
    const delta = await page.locator('.list-table-wrap').evaluate((element) => element.scrollLeft)
    expect(delta).toBeGreaterThan(0)
  })
}

// Ensure DPR is tested independently of the physical viewport; the DEV trace
// recorded devicePixelRatio 1.25 and full-width AppShell scrollbar metrics.
test('Positions DPR 1.25: compact list does not develop a scroll range', async ({ browser, baseURL }) => {
  const context = await browser.newContext({
    baseURL,
    viewport: { width: 1528, height: 732 },
    deviceScaleFactor: 1.25,
  })
  try {
    const page = await context.newPage()
    await mockPeopleList(page, 'positions', 4)
    await page.goto('/people/positions')
    await expect(page.locator('.list-table tbody tr')).toHaveCount(4)
    expect(await page.evaluate(() => window.devicePixelRatio)).toBe(1.25)
    const metrics = await shellMetrics(page)
    expectSingleOwner(metrics)
    expect(metrics.ownerMaxScroll).toBeLessThanOrEqual(1)
  } finally {
    await context.close()
  }
})
