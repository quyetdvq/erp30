/**
 * CSS-A02-HF10: Compare the real Customer and Supplier routes in the HF09
 * single-scroll-owner AppShell. They share ListTable/QueryControls/Pagination,
 * while Customer deliberately opts into sticky Actions for its seven columns.
 * Fixtures are read-only API results; no workflow POST is exercised here.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'

const kinds = ['customers', 'suppliers'] as const
type Kind = (typeof kinds)[number]

function records(kind: Kind, count: number): Record<string, unknown>[] {
  return Array.from({ length: count }, (_, index) => {
    const common = {
      id: String(index + 101),
      name: `Đối tác ${index + 1}`,
      phone: `090000${String(index).padStart(4, '0')}`,
      status: 'ACTIVE',
      allowed_actions: { edit: true, setstate: true },
    }
    return kind === 'customers'
      ? { ...common, customer_code: `CUS-${index + 1}`, customer_type: 'ORGANIZATION', customer_group_code: 'STANDARD', created_at: '2026-11-06T00:00:00' }
      : { ...common, supplier_code: `SUP-${index + 1}`, supplier_type: 'COMPANY', supplier_group_code: 'STANDARD', row_version: '1' }
  })
}

async function mockPage(page: Page, kind: Kind, count: number): Promise<void> {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { user: {
      id: 7, username: 'hf10-list-parity-qa', groups: ['orgadmin'],
      base_permissions: [
        'customer.view', 'customer.create', 'customer.edit', 'customer.setstate',
        'supplier.view', 'supplier.create', 'supplier.edit', 'supplier.setstate',
      ],
    } } }),
  }))
  const rows = records(kind, count)
  await page.route(`**/api/v1/${kind}**`, (route: Route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: rows, meta: mockPagination(route, count, `hf10-${kind}`) }),
  }))
  const resource = kind === 'customers' ? 'customer' : 'supplier'
  await page.route(`**/api/v1/business-workflow/${resource}/states**`, (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { mode: 'LIFECYCLE', initial: 'DRAFT', states: ['DRAFT', 'ACTIVE', 'INACTIVE'] } }),
  }))
}

type Rect = { x: number; y: number; w: number; h: number }
type Geometry = { boxes: Rect[]; widths: number[]; heights: number[]; left: number; outerY: number; bodyWidth: number }
async function snapshot(page: Page): Promise<Geometry> {
  return page.locator('.list-table-wrap').evaluate((wrapper) => {
    const table = wrapper.querySelector('table.list-table')!
    const rows = [...table.querySelectorAll('tbody tr')]
    const root = wrapper.getBoundingClientRect()
    const rectangle = (node: Element): Rect => {
      const r = node.getBoundingClientRect()
      return { x: Math.round((r.x - root.x) * 100) / 100, y: Math.round((r.y - root.y) * 100) / 100,
        w: Math.round(r.width * 100) / 100, h: Math.round(r.height * 100) / 100 }
    }
    const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
    return {
      boxes: [rectangle(table), ...rows.slice(0, 2).flatMap((r) => [rectangle(r), ...[...r.cells].map(rectangle), ...[...r.querySelectorAll('button')].map(rectangle)])],
      widths: [...table.querySelectorAll('thead th')].map((node) => node.getBoundingClientRect().width),
      heights: rows.slice(0, 2).map((node) => node.getBoundingClientRect().height),
      left: wrapper.scrollLeft, outerY: owner.scrollTop, bodyWidth: document.documentElement.scrollWidth,
    }
  })
}

function equalLayout(a: Geometry, b: Geometry): void {
  expect(a.boxes.length).toBe(b.boxes.length)
  expect(a.widths.length).toBe(b.widths.length)
  expect(b.bodyWidth).toBe(a.bodyWidth)
  expect(b.left).toBe(a.left)
  expect(b.outerY).toBeCloseTo(a.outerY, 1)
  for (let i = 0; i < a.boxes.length; i++) {
    for (const value of ['x', 'y', 'w', 'h'] as const) {
      expect(b.boxes[i]![value], `box ${i} ${value}`).toBeCloseTo(a.boxes[i]![value], 1)
    }
  }
  for (let i = 0; i < a.widths.length; i++) expect(b.widths[i]).toBeCloseTo(a.widths[i]!, 1)
  for (let i = 0; i < a.heights.length; i++) expect(b.heights[i]).toBeCloseTo(a.heights[i]!, 1)
}

async function assertSingleOwner(page: Page): Promise<void> {
  const values = await page.evaluate(() => {
    const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
    const main = document.querySelector<HTMLElement>('.workspace-content')!
    const table = document.querySelector<HTMLElement>('.list-table-wrap')!
    const mainStyle = getComputedStyle(main)
    return {
      ownerY: getComputedStyle(owner).overflowY, ownerGutter: getComputedStyle(owner).scrollbarGutter,
      mainX: mainStyle.overflowX, mainY: mainStyle.overflowY, mainBottom: mainStyle.paddingBottom,
      mainScroll: Math.max(0, main.scrollHeight - main.clientHeight),
      tableX: getComputedStyle(table).overflowX,
    }
  })
  expect(values).toEqual({ ownerY: 'auto', ownerGutter: 'stable', mainX: 'clip', mainY: 'visible', mainBottom: '0px', mainScroll: 0, tableX: 'auto' })
}

for (const kind of kinds) {
  for (const theme of ['light', 'dark'] as const) {
    for (const width of [375, 1440]) {
      test(`${kind} ${theme} ${width}px: shared list layout and hover geometry are stable`, async ({ page }) => {
        await page.setViewportSize({ width, height: 771 })
        await mockPage(page, kind, 4)
        await page.goto(`/${kind}`)
        const rows = page.locator('.list-table tbody tr')
        await expect(rows).toHaveCount(4)
        await page.evaluate((value) => { document.documentElement.dataset.dsTheme = value }, theme)
        await assertSingleOwner(page)
        // Search/filters must complete before the shared Apply/Reset/Refresh
        // row; no panel-level overflow is allowed even when fields wrap.
        const filterLayout = await page.locator('.list-query-controls').evaluate((form) => {
          const grid = form.querySelector<HTMLElement>('.list-query-controls__grid')!
          const actions = form.querySelector<HTMLElement>('.list-query-controls__actions')!
          return { filterBottom: grid.getBoundingClientRect().bottom,
            actionsTop: actions.getBoundingClientRect().top,
            formOverflow: form.scrollWidth - form.clientWidth }
        })
        expect(filterLayout.actionsTop).toBeGreaterThanOrEqual(filterLayout.filterBottom - 2)
        expect(filterLayout.formOverflow).toBeLessThanOrEqual(1)
        const wrapper = page.locator('.list-table-wrap')
        const table = page.locator('.list-table')
        await expect(table).toHaveCSS('min-width', kind === 'customers' ? '1040px' : '640px')
        const statusCells = page.locator('.ds-table-status-with-label')
        await expect(statusCells).toHaveCount(4)
        await expect(statusCells.first()).toHaveCSS('display', 'inline-flex')
        await expect(statusCells.first()).toHaveCSS('gap', '6px')
        if (kind === 'customers') {
          await expect(rows.first().locator('td').last()).toHaveCSS('position', 'sticky')
        } else {
          await expect(rows.first().locator('td').last()).toHaveCSS('position', 'static')
        }
        for (const row of [rows.first(), rows.nth(1)]) {
          const icons = row.locator('button.ds-icon-control')
          await expect(icons).toHaveCount(3)
          for (const button of await icons.all()) {
            await expect(button).toHaveCSS('width', '32px')
            await expect(button).toHaveCSS('height', '32px')
          }
        }
        if (width === 375) {
          await wrapper.evaluate((el) => { el.scrollLeft = el.scrollWidth - el.clientWidth })
          expect(await wrapper.evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)
        }
        // Establish a visible, settled viewport before measuring: on a narrow
        // screen Playwright legitimately scrolls to make the table visible.
        const row1 = rows.first().locator('td').last()
        const row2 = rows.nth(1).locator('td').last()
        await row1.hover()
        await row2.hover()
        const baseline = await snapshot(page)
        const hover = await page.evaluate(() => {
          const sample = document.createElement('span')
          sample.style.backgroundColor = 'var(--ds-table-row-hover)'
          document.body.append(sample)
          const resolved = getComputedStyle(sample).backgroundColor
          sample.remove()
          return resolved
        })
        for (let i = 0; i < 4; i++) {
          await row1.hover()
          await expect(row1).toHaveCSS('background-color', hover)
          equalLayout(baseline, await snapshot(page))
          await row2.hover()
          await expect(row2).toHaveCSS('background-color', hover)
          equalLayout(baseline, await snapshot(page))
        }
        expect((await snapshot(page)).bodyWidth).toBeLessThanOrEqual(width + 1)
      })
    }
  }
  test(`${kind}: long list keeps vertical scrolling at NLayout, not main`, async ({ page }) => {
    await page.setViewportSize({ width: 1528, height: 732 })
    await mockPage(page, kind, 25)
    await page.goto(`/${kind}`)
    await expect(page.locator('.list-table tbody tr')).toHaveCount(25)
    await assertSingleOwner(page)
    const range = await page.evaluate(() => {
      const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
      const amount = owner.scrollHeight - owner.clientHeight
      owner.scrollTop = owner.scrollHeight
      return { amount, top: owner.scrollTop }
    })
    expect(range.amount).toBeGreaterThan(150)
    expect(range.top).toBeGreaterThan(150)
    await expect(page.locator('.list-pagination')).toBeInViewport()
  })
}

test('Customer at DPR 1.25: sticky Actions remains aligned after horizontal scrolling', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL, viewport: { width: 560, height: 771 }, deviceScaleFactor: 1.25 })
  try {
    const page = await context.newPage()
    await mockPage(page, 'customers', 4)
    await page.goto('/customers')
    await expect(page.locator('.list-table tbody tr')).toHaveCount(4)
    const wrapper = page.locator('.list-table-wrap')
    await wrapper.evaluate((el) => { el.scrollLeft = el.scrollWidth - el.clientWidth })
    const values = await page.evaluate(() => {
      const wrap = document.querySelector('.list-table-wrap')!
      const action = wrap.querySelector('tbody tr td:last-child')!
      const a = action.getBoundingClientRect()
      const w = wrap.getBoundingClientRect()
      return { dpr: window.devicePixelRatio, rightGap: w.right - a.right }
    })
    expect(values.dpr).toBe(1.25)
    expect(Math.abs(values.rightGap)).toBeLessThanOrEqual(3)
    await assertSingleOwner(page)
  } finally {
    await context.close()
  }
})
