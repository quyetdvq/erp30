/**
 * CSS-A02-HF12: mounted /customers with a hostile changing AppShell ancestor.
 * The table+icon geometry must depend on the Design System table boundary,
 * not on incidental inherited font/line-height/letter-spacing from workspace.
 * Only presentation properties are varied; fixture APIs are read-only.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'

async function mockCustomers(page: Page): Promise<void> {
  await page.route('**/api/v1/auth/me', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { user: {
      id: 7, username: 'hf12-css-ancestor', groups: ['orgadmin'],
      base_permissions: [
        'customer.view', 'customer.create', 'customer.edit', 'customer.setstate',
      ],
    } } }),
  }))
  const rows = Array.from({ length: 6 }, (_, index) => ({
    id: String(index + 101),
    customer_code: `CUS-${index + 1}`, name: `Northwind Example ${index + 1}`,
    customer_type: 'ORGANIZATION', customer_group_code: 'STANDARD',
    phone: '0912000000', status: 'ACTIVE',
    allowed_actions: { edit: true, setstate: true },
  }))
  await page.route('**/api/v1/customers**', (route: Route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: rows, meta: mockPagination(route, rows.length, 'hf12-customers') }),
  }))
  await page.route('**/api/v1/business-workflow/customer/states**', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: {
      mode: 'LIFECYCLE', initial: 'DRAFT', states: ['DRAFT', 'ACTIVE', 'INACTIVE'],
    } }),
  }))
}

type Snapshot = {
  table: number[]; rows: number[][]; cells: number[][][]; actions: number[][];
  fonts: { family: string; size: string; weight: string; height: string; spacing: string };
  iconSizes: number[][]; tableScroll: number;
}

async function snapshot(page: Page): Promise<Snapshot> {
  return page.locator('.list-table-wrap--sticky-actions').evaluate((wrap) => {
    const table = wrap.querySelector<HTMLTableElement>('table.list-table')!
    const rows = Array.from(table.tBodies[0]!.rows).slice(0, 2)
    const wr = wrap.getBoundingClientRect()
    const round = (n: number) => Math.round(n * 100) / 100
    const box = (el: Element) => {
      const r = el.getBoundingClientRect()
      return [round(r.x - wr.x), round(r.y - wr.y), round(r.width), round(r.height)]
    }
    const css = getComputedStyle(wrap)
    const buttons = rows.flatMap((row) => Array.from(row.querySelectorAll<HTMLButtonElement>('.table-row-actions .ds-icon-control')))
    return {
      table: box(table), rows: rows.map(box),
      cells: rows.map((row) => Array.from(row.cells).map(box)),
      actions: buttons.map(box),
      fonts: {
        family: css.fontFamily, size: css.fontSize, weight: css.fontWeight,
        height: css.lineHeight, spacing: css.letterSpacing,
      },
      iconSizes: buttons.map((el) => [round(el.getBoundingClientRect().width), round(el.getBoundingClientRect().height)]),
      tableScroll: wrap.scrollLeft,
    }
  })
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [375, 720, 1440]) {
    test(`Customers HF12 ${theme}/${width}px: AppShell inherited typography cannot resize table or actions`, async ({ browser, baseURL }) => {
      const context = await browser.newContext({ baseURL, viewport: { width, height: 771 }, deviceScaleFactor: 1.25 })
      try {
        const page = await context.newPage()
        await mockCustomers(page)
        await page.goto('/customers')
        const rows = page.locator('.list-table tbody tr')
        await expect(rows).toHaveCount(6)
        await page.evaluate((value) => { document.documentElement.dataset.dsTheme = value }, theme)
        const wrap = page.locator('.list-table-wrap--sticky-actions')
        // Ensure both rows are reachable on the compact mobile toolbar before
        // taking the baseline. AppShell scrolling is not the table CSS metric.
        await rows.nth(1).locator('td').last().scrollIntoViewIfNeeded()
        await wrap.evaluate((el) => { el.scrollLeft = el.scrollWidth - el.clientWidth })
        const baseline = await snapshot(page)
        expect(baseline.actions).toHaveLength(6)
        for (const size of baseline.iconSizes) expect(size).toEqual([32, 32])

        // Simulate a changing parent style without touching the table or AppShell logic.
        await page.addStyleTag({ content: `
          .workspace-content.hf12-font-poison {
            font: italic 700 26px/3 Georgia !important;
            letter-spacing: 2px !important;
            text-transform: uppercase !important;
            text-indent: 10px !important;
            text-align: center !important;
            white-space: nowrap !important;
            color: #c00000 !important;
          }
        ` })
        await page.locator('.workspace-content').evaluate((el) => el.classList.add('hf12-font-poison'))
        expect(await snapshot(page)).toEqual(baseline)
        // Direct pointer motion does not invoke Playwright locator.hover()'s
        // scrollIntoView behavior (which can change horizontal scrollLeft).
        const actionButtons = [
          rows.nth(0).locator('td').last().locator('.table-row-actions .ds-icon-control').last(),
          rows.nth(1).locator('td').last().locator('.table-row-actions .ds-icon-control').last(),
        ]
        for (let step = 0; step < 12; step++) {
          const button = actionButtons[step % 2]!
          await button.scrollIntoViewIfNeeded()
          // Playwright's scroll-into-view can change scrollLeft independently
          // of CSS; restore the same horizontal view before measuring.
          await wrap.evaluate((el, left) => { el.scrollLeft = left }, baseline.tableScroll)
          const r = await button.boundingBox()
          expect(r).not.toBeNull()
          await page.mouse.move(r!.x + r!.width / 2, r!.y + r!.height / 2)
          await expect(button).toHaveJSProperty('disabled', false)
          expect(await button.evaluate((el) => el.matches(':hover'))).toBe(true)
          expect(await snapshot(page), `after pointer move ${step}`).toEqual(baseline)
        }
        await page.locator('.workspace-content').evaluate((el) => el.classList.remove('hf12-font-poison'))
        expect(await snapshot(page)).toEqual(baseline)
      } finally {
        await context.close()
      }
    })
  }
}
