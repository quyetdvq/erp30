// DS5 HF10 + HF05: unchanged AutoNumberingView with an atomic PBAC-scoped overview GET.
import { expect, test } from '@playwright/test'
import { fulfillAutoNumberingFixture, mockAutoNumberingPreflight, mockAutoNumberingSession } from './support/autoNumberingApiFixture'

const resource2 = { id: '2', type: 'PRODUCT', name: 'Products', domain: 'MASTER', status: 'ACTIVE', allowed_actions: { configure_numbering: true } }
const resource3 = { id: '3', type: 'ORDER', name: 'Orders', domain: 'MASTER', status: 'ACTIVE', allowed_actions: { configure_numbering: true } }
function item(id: string, resourceId: string, name: string) {
  return {
    id, resource_id: resourceId, series_code: `CODE_${resourceId}`,
    name, scope_type: 'GROUP', pattern: `CODE${resourceId}-{SEQ:6}`,
    reset_policy: 'NEVER', generation_mode: 'AUTO_REQUIRED',
    start_number: '1', increment_by: 1, is_default: true, status: 'ACTIVE',
  }
}

async function mockNumbering(page: import('@playwright/test').Page, denyThird = false, authDelayMs = 0) {
  await mockAutoNumberingSession(page, { authDelayMs })
  await page.route('**/api/v1/resources*', (route) => {
    const url = new URL(route.request().url())
    if (url.pathname === '/api/v1/resources' && route.request().method() === 'GET') {
      return fulfillAutoNumberingFixture(route, { status: 200, contentType: 'application/json',
        body: JSON.stringify({ data: [resource2, denyThird
          ? { ...resource3, allowed_actions: { configure_numbering: false } } : resource3],
          meta: { page: 1, page_size: 200, total: 2 } }),
      })
    }
    return route.fallback()
  })
  const requests: string[] = []
  const stateChanges: string[] = []
  const currentByResource = new Map<string, ReturnType<typeof item>>()
  const deniedReads = new Set<string>()
  const slowReads = new Map<string, number>()
  for (const [id, record] of [['2', item('21', '2', 'Product number')], ['3', item('31', '3', 'Order number')]] as const) {
    let current = record
    currentByResource.set(id, current)
    await page.route(`**/api/v1/resources/${id}/numbering-series**`, async (route) => {
      const url = new URL(route.request().url())
      const method = route.request().method()
      requests.push(`${method} ${url.pathname}`)
      if (url.pathname.endsWith('/actions/validate-config')) {
        const body = JSON.parse(route.request().postData() ?? '{}') as Record<string, unknown>
        if (body.series_id !== record.id) {
          return fulfillAutoNumberingFixture(route, { status: 409, contentType: 'application/json',
            body: JSON.stringify({ error: { code: 'NUM_SERIES_DUPLICATE' } }) })
        }
        return fulfillAutoNumberingFixture(route, { status: 200, contentType: 'application/json',
          body: JSON.stringify({ data: { valid: true, normalized_pattern: record.pattern, validation_messages: [] } }) })
      }
      if (url.pathname.endsWith('/actions/set-state')) {
        stateChanges.push(id)
        current = { ...current, status: 'INACTIVE' }
        currentByResource.set(id, current)
        return fulfillAutoNumberingFixture(route, { status: 200, contentType: 'application/json', body: JSON.stringify({ data: current }) })
      }
      if (/\/numbering-series\/\d+$/.test(url.pathname)) {
        return fulfillAutoNumberingFixture(route, { status: 200, contentType: 'application/json', body: JSON.stringify({ data: current }) })
      }
      if (url.pathname.endsWith('/numbering-series') && method === 'GET') {
        const delay = slowReads.get(id) ?? 0
        if (delay) await new Promise((resolve) => setTimeout(resolve, delay))
        if (deniedReads.has(id)) {
          return fulfillAutoNumberingFixture(route, { status: 403, contentType: 'application/json',
            body: JSON.stringify({ error: { code: 'NUM_AUTHORIZATION_DENIED' } }) })
        }
        return fulfillAutoNumberingFixture(route, { status: 200, contentType: 'application/json',
          body: JSON.stringify({ data: [current], meta: { page: 1, page_size: 1, total: 1 } }),
        })
      }
      return route.fallback()
    })
  }
  await page.route('**/api/v1/numbering-series/overview', async (route) => {
    requests.push(`GET ${new URL(route.request().url()).pathname}`)
    const delay = slowReads.get('3') ?? 0
    if (delay) await new Promise((resolve) => setTimeout(resolve, delay))
    if (deniedReads.size > 0) {
      return fulfillAutoNumberingFixture(route, { status: 403,
        body: JSON.stringify({ error: { code: 'NUM_AUTHORIZATION_DENIED' } }) })
    }
    const visible = denyThird ? [resource2] : [resource2, resource3]
    return fulfillAutoNumberingFixture(route, { status: 200,
      body: JSON.stringify({ data: {
        resources: visible,
        series: visible.map((resource) => currentByResource.get(String(resource.id))),
      } }),
    })
  })
  await mockAutoNumberingPreflight(page)
  return { requests, stateChanges, denyRead: (id: string) => deniedReads.add(id), allowRead: (id: string) => deniedReads.delete(id), delayRead: (id: string, ms: number) => slowReads.set(id, ms) }
}

test('All resource is the default; table shows all authorized series and preserves scope on edit', async ({ page }) => {
  const api = await mockNumbering(page)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/configuration/auto-numbering')
  const listFilter = page.locator('.numbering-resource-filter .searchable-select')
  await expect(listFilter.locator('.n-base-selection')).toBeVisible()
  await expect(listFilter).toContainText(/All resource|Tất cả Resource/)
  await expect(page.locator('.numbering-resource-filter > span')).toHaveText('Resource')
  const table = page.locator('table.list-table')
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  await expect(table.getByRole('row')).toHaveCount(3)
  await expect(table.getByRole('columnheader').first()).toContainText('Resource')
  expect(api.requests.filter((request) => request === 'GET /api/v1/numbering-series/overview')).toHaveLength(1)
  expect(api.requests.filter((request) => /^GET \/api\/v1\/resources\/\d+\/numbering-series$/.test(request))).toHaveLength(0)

  await listFilter.locator('.n-base-selection').click()
  await listFilter.locator('input').first().fill('Orders')
  await expect(page.locator('.n-base-select-option').filter({ hasText: 'ORDER - Orders' })).toBeVisible()
  await page.locator('.n-base-select-option').filter({ hasText: 'ORDER - Orders' }).click()
  await expect(listFilter).toContainText('ORDER - Orders')
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toHaveCount(0)
  await listFilter.hover()
  await listFilter.locator('.n-base-clear').click()
  // Filterable NSelect can render its placeholder as an input attribute,
  // not textContent, immediately after clearing. Test the actual All resource
  // behavior (both Resource series return) rather than incidental DOM markup.
  await page.locator('.page-heading h1').click()
  await expect(listFilter.locator('.n-base-selection')).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  await expect(table.getByRole('row')).toHaveCount(3)

  const orderRow = table.getByRole('row').filter({ hasText: 'Order number' })
  await orderRow.getByRole('button', { name: /Sửa|Edit/ }).click()
  const dialog = page.getByRole('dialog', { name: /Biểu mẫu Auto Numbering|Auto Numbering form/ })
  await expect(dialog.getByRole('combobox', { name: 'Resource' })).toHaveValue('3')
  await expect(dialog.getByRole('combobox', { name: 'Resource' })).toBeDisabled()
  await dialog.getByRole('button', { name: /Kiểm tra cấu hình|Validate configuration/ }).click()
  await expect(dialog.getByText(/Cấu hình hợp lệ|Configuration is valid/)).toBeVisible()
  await expect(listFilter).toContainText(/All resource|Tất cả Resource/)
  await dialog.getByRole('button', { name: /Hủy|Cancel/ }).click()
  await expect(dialog).toHaveCount(0)
  await expect(table.getByRole('row')).toHaveCount(3)

  await page.getByRole('button', { name: /Tạo numbering series|Create numbering series/ }).click()
  const newDialog = page.getByRole('dialog', { name: /Biểu mẫu Auto Numbering|Auto Numbering form/ })
  await expect(newDialog.getByRole('combobox', { name: 'Resource' })).toHaveValue('')
  await newDialog.getByRole('combobox', { name: 'Resource' }).selectOption('2')
  await expect(listFilter).toContainText(/All resource|Tất cả Resource/)
  await newDialog.getByRole('button', { name: /Hủy|Cancel/ }).click()
  await expect(newDialog).toHaveCount(0)
  await expect(listFilter).toContainText(/All resource|Tất cả Resource/)
})

test('row state mutation always routes to the row Resource while All resource stays selected', async ({ page }) => {
  const api = await mockNumbering(page)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/configuration/auto-numbering')
  const table = page.locator('table.list-table')
  const row = table.getByRole('row').filter({ hasText: 'Order number' })
  await expect(row).toBeVisible()
  await row.getByRole('button', { name: /Ngừng hoạt động|Deactivate/ }).click()
  await expect.poll(() => api.stateChanges).toContain('3')
  await expect(page.locator('.numbering-resource-filter .searchable-select')).toContainText(/All resource|Tất cả Resource/)
})


test('All resource never requests a Resource without effective configure_numbering permission', async ({ page }) => {
  const api = await mockNumbering(page, true)
  await page.goto('/configuration/auto-numbering')
  const table = page.locator('table.list-table')
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toHaveCount(0)
  await expect(table.getByRole('row')).toHaveCount(2)
  expect(api.requests).toContain('GET /api/v1/numbering-series/overview')
  expect(api.requests).not.toContain('GET /api/v1/resources/3/numbering-series')
  const filter = page.locator('.numbering-resource-filter .searchable-select')
  await filter.locator('.n-base-selection').click()
  await expect(page.locator('.n-base-select-option')).toHaveCount(1)
  await expect(page.locator('.n-base-select-option').first()).toContainText('PRODUCT - Products')
})


test('direct route entry loads after a delayed permission snapshot', async ({ page }) => {
  const api = await mockNumbering(page, false, 250)
  await page.goto('/configuration/auto-numbering')
  const filter = page.locator('.numbering-resource-filter .searchable-select')
  await expect(filter).toContainText(/All resource|Tất cả Resource/)
  const table = page.locator('table.list-table')
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  expect(api.requests.filter((request) => request === 'GET /api/v1/numbering-series/overview')).toHaveLength(1)
  expect(api.requests.filter((request) => /^GET \/api\/v1\/resources\/\d+\/numbering-series$/.test(request))).toHaveLength(0)
})


test('reuses the All resource snapshot across filter changes without duplicate GETs', async ({ page }) => {
  const api = await mockNumbering(page)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/configuration/auto-numbering')
  const table = page.locator('table.list-table')
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  const listGetCount = () => api.requests.filter((request) => request === 'GET /api/v1/numbering-series/overview' || /^GET \/api\/v1\/resources\/\d+\/numbering-series$/.test(request)).length
  expect(listGetCount()).toBe(1)

  const filter = page.locator('.numbering-resource-filter .searchable-select')
  await filter.locator('.n-base-selection').click()
  await filter.locator('input').first().fill('Orders')
  await page.locator('.n-base-select-option').filter({ hasText: 'ORDER - Orders' }).click()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toHaveCount(0)
  expect(listGetCount()).toBe(1) // Switching filters is entirely local.

  // A canonical mutation response updates the row without refetching either Resource.
  await table.getByRole('row').filter({ hasText: 'Order number' })
    .getByRole('button', { name: /Ngừng hoạt động|Deactivate/ }).click()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })
    .getByRole('button', { name: /Kích hoạt|Activate/ })).toBeVisible()
  expect(listGetCount()).toBe(1)

  // Refresh is an explicit server read scoped to the selected Resource.
  await page.locator('.numbering-query-controls button[type="submit"]').click()
  await expect.poll(listGetCount).toBe(2)
  await filter.hover()
  await filter.locator('.n-base-clear').click()
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  // Clearing an NSelect value is not a request to close its filter menu.
  // Dismiss the menu with a normal outside click (as a user would), then
  // verify the Refresh click and its network cost without force-clicking.
  await page.locator('.page-heading h1').click()
  expect(listGetCount()).toBe(2)

  await page.locator('.numbering-query-controls button[type="submit"]').click()
  await expect.poll(listGetCount).toBe(3) // All Refresh uses exactly one overview GET.
})

test('a denied Resource refresh fails closed and clears the entire list', async ({ page }) => {
  const api = await mockNumbering(page)
  await page.goto('/configuration/auto-numbering')
  const table = page.locator('table.list-table')
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  api.denyRead('3')
  await page.locator('.numbering-query-controls button[type="submit"]').click()
  await expect(page.locator('.numbering-panel .inline-error')).toBeVisible()
  await expect(page.getByRole('cell', { name: 'Product number' })).toHaveCount(0)
  await expect(page.getByRole('cell', { name: 'Order number' })).toHaveCount(0)
  // Axios triggers asynchronous PBAC refresh before the component catch runs.
  // The denial must stay visible and never issue an unbounded GET/403 loop.
  await page.waitForTimeout(350)
  expect(api.requests.filter((request) => request === 'GET /api/v1/numbering-series/overview')).toHaveLength(2)

  // Retry is explicit: a real permission revalidation may restore the grant,
  // but must never silently override the prior 403 and re-read the table.
  api.allowRead('3')
  await expect(page.locator('.numbering-query-controls button[type="submit"]')).toBeEnabled()
  await page.locator('.numbering-query-controls button[type="submit"]').click()
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  expect(api.requests.filter((request) => request === 'GET /api/v1/numbering-series/overview')).toHaveLength(3)
})

test('a stale All resource response cannot replace newer filtered data', async ({ page }) => {
  const api = await mockNumbering(page)
  await page.goto('/configuration/auto-numbering')
  const table = page.locator('table.list-table')
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toBeVisible()
  api.delayRead('3', 400)
  await page.locator('.numbering-query-controls button[type="submit"]').click()
  // Filter selection uses the cached complete snapshot while Refresh is pending.
  const filter = page.locator('.numbering-resource-filter .searchable-select')
  await filter.locator('.n-base-selection').click()
  await filter.locator('input').first().fill('Orders')
  await page.locator('.n-base-select-option').filter({ hasText: 'ORDER - Orders' }).click()
  await expect(table.getByRole('row').filter({ hasText: 'Order number' })).toBeVisible()
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toHaveCount(0)
  await expect.poll(() => api.requests.filter((request) => request === 'GET /api/v1/numbering-series/overview').length).toBe(2)
  // Give the delayed response enough time to arrive: the latest filter still wins.
  await page.waitForTimeout(450)
  await expect(table.getByRole('row').filter({ hasText: 'Product number' })).toHaveCount(0)
})
