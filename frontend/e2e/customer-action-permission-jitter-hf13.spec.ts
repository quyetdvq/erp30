/**
 * HF13 regression: Customer action controls are fail-closed during a PBAC
 * refresh. Their DOM may temporarily disappear; the sticky table MUST keep
 * exactly the same row, table, and workspace geometry. This is independent of
 * hover CSS and exercises the real Vue route and production stylesheets.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'

async function fixture(page: Page, count = 18): Promise<void> {
  await page.route('**/api/v1/auth/me', route => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { user: {
      id: 7, username: 'customer-hf13-qa', groups: ['orgadmin'],
      base_permissions: ['customer.view','customer.create','customer.edit','customer.setstate'],
    } } }),
  }))
  await page.route('**/api/v1/customers**', (route: Route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: Array.from({ length: count }, (_, i) => ({
      id: String(i + 1), name: `Customer ${i + 1}`, customer_code: `C-${i + 1}`,
      customer_type: 'ORGANIZATION', customer_group_code: 'STANDARD',
      phone: '0900000000', status: 'ACTIVE',
      allowed_actions: { edit: true, setstate: true },
    })), meta: mockPagination(route, count, 'customer-hf13') }),
  }))
  await page.route('**/api/v1/business-workflow/customer/states**', route => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { mode: 'LIFECYCLE', initial: 'DRAFT', states: ['DRAFT','ACTIVE','INACTIVE'] } }),
  }))
}

async function geometry(page: Page) {
  return page.locator('.customer-list-panel .list-table-wrap').evaluate(wrapper => {
    const table = wrapper.querySelector<HTMLTableElement>('table.list-table')!
    const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
    const rect = (element: Element) => {
      const r = element.getBoundingClientRect()
      return [r.x, r.y, r.width, r.height].map(n => Math.round(n * 100) / 100)
    }
    return {
      rows: Array.from(table.tBodies[0]?.rows || []).map(row => rect(row)),
      cells: Array.from(table.tBodies[0]?.rows || []).slice(0, 8).map(row => rect(row.lastElementChild!)),
      actions: Array.from(table.tBodies[0]?.rows || []).slice(0, 8).map(row => rect(row.querySelector('.table-row-actions')!)),
      table: rect(table), wrap: rect(wrapper), scrollTop: owner.scrollTop,
      scrollLeft: wrapper.scrollLeft, scrollHeight: owner.scrollHeight,
    }
  })
}

function stableGeometry(before: Awaited<ReturnType<typeof geometry>>, after: Awaited<ReturnType<typeof geometry>>) {
  const compare = (a: number[], b: number[]) => {
    expect(a.length).toBe(b.length)
    for (let i = 0; i < a.length; i++) expect(Math.abs(a[i]! - b[i]!)).toBeLessThan(0.5)
  }
  expect(after.rows.length).toBe(before.rows.length)
  for (let i = 0; i < before.rows.length; i++) compare(before.rows[i]!, after.rows[i]!)
  for (let i = 0; i < before.cells.length; i++) compare(before.cells[i]!, after.cells[i]!)
  // When PBAC intentionally hides buttons, the empty inline-flex may lose
  // intrinsic WIDTH and move within its right-aligned cell. No action remains
  // visible, so only its vertical slot must stay stable; the sticky cell,
  // table, row and workspace rectangles above must not move.
  for (let i = 0; i < before.actions.length; i++) {
    expect(Math.abs(before.actions[i]![1]! - after.actions[i]![1]!)).toBeLessThan(0.5)
    expect(Math.abs(before.actions[i]![3]! - after.actions[i]![3]!)).toBeLessThan(0.5)
  }
  compare(before.table, after.table)
  compare(before.wrap, after.wrap)
  expect(Math.abs(after.scrollTop - before.scrollTop)).toBeLessThan(0.5)
  expect(Math.abs(after.scrollLeft - before.scrollLeft)).toBeLessThan(0.5)
  expect(Math.abs(after.scrollHeight - before.scrollHeight)).toBeLessThan(0.5)
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [375, 720, 1440]) {
    test(`HF13 ${theme} ${width}px: intermittent action disappearance cannot resize Customer rows`, async ({ page }) => {
      await page.setViewportSize({ width, height: 771 })
      await fixture(page)
      await page.goto('/customers')
      const rows = page.locator('.customer-list-panel tbody tr')
      await expect(rows).toHaveCount(18)
      await expect(rows.first().locator('td').last().locator('button.ds-icon-control')).toHaveCount(3)
      await page.evaluate(value => { document.documentElement.dataset.dsTheme = value }, theme)
      await page.evaluate(() => {
        const owner = document.querySelector<HTMLElement>('.workspace-layout .n-layout-scroll-container')!
        const wrapper = document.querySelector<HTMLElement>('.customer-list-panel .list-table-wrap')!
        owner.scrollTop = Math.min(240, owner.scrollHeight - owner.clientHeight)
        wrapper.scrollLeft = wrapper.scrollWidth - wrapper.clientWidth
      })
      const original = await geometry(page)
      const hide = await page.addStyleTag({ content:
        '.customer-list-panel .table-row-actions > button { display: none !important; }' })
      // Simulates the rendered effect of the PBAC fail-closed phase (v-if false)
      // without invoking or bypassing any real permissions or business handlers.
      await expect(rows.first().locator('.table-row-actions button').first()).toHaveCSS('display', 'none')
      stableGeometry(original, await geometry(page))
      await hide.evaluate(el => el.remove())
      await expect(rows.first().locator('.table-row-actions button').first()).toHaveCSS('display', 'inline-flex')
      stableGeometry(original, await geometry(page))
    })
  }
}

test('HF13 diagnostic: Customer-only opt-in reports action changes without capturing record data', async ({ page }) => {
  await fixture(page, 4)
  await page.goto('/customers?customers_trace=1')
  const controls = page.locator('#dink-customer-live-trace-controls')
  await expect(controls).toBeVisible()
  await expect(page.locator('.customer-list-panel tbody tr')).toHaveCount(4)
  await page.locator('.customer-list-panel').evaluate(el => {
    el.setAttribute('data-customer-permission-snapshot', 'loading')
    el.querySelectorAll('.table-row-actions').forEach(container => container.replaceChildren())
  })
  await expect.poll(async () => page.evaluate(() => {
    const api = (window as unknown as { __dinkCustomerActionsTrace: { report(): {
      summary: { categories: Record<string,number> }
    } } }).__dinkCustomerActionsTrace
    const categories = api.report().summary.categories
    return Boolean(categories.PERMISSION_SNAPSHOT_CHANGED) &&
      Object.keys(categories).some(s => s.startsWith('ROW_ACTIONS_CHANGED_'))
  })).toBe(true)
  await page.getByRole('button', { name: 'Đánh dấu nhảy' }).click()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Xuất JSON' }).click()
  expect((await download).suggestedFilename()).toMatch(/^DinkERP_Customers_Action_Trace_.*\.json$/)
  const safe = await page.evaluate(() => {
    const api = (window as unknown as { __dinkCustomerActionsTrace: { report(): unknown } }).__dinkCustomerActionsTrace
    return JSON.stringify(api.report())
  })
  expect(safe).not.toContain('Customer 1')
  expect(safe).not.toContain('0900000000')
})

test('HF13 diagnostic is absent when visiting Customers without opt-in', async ({ page }) => {
  await fixture(page, 4)
  await page.goto('/customers')
  await expect(page.locator('.customer-list-panel tbody tr')).toHaveCount(4)
  await expect(page.locator('#dink-customer-live-trace-controls')).toHaveCount(0)
  expect(await page.evaluate(() => typeof (window as unknown as { __dinkCustomerActionsTrace?: unknown }).__dinkCustomerActionsTrace)).toBe('undefined')
})
