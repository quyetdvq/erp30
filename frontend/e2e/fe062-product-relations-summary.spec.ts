import { expect, test, type Page, type Route } from '@playwright/test'
import { mockLookupBatch, child, combo, parent, simple } from './support/lookupBatchMock'
import { productRow } from './support/productRows'

type Preview = { product_id: string; total: number; products: (typeof simple)[] }

function cors(route: Route) {
  return {
    'Access-Control-Allow-Origin': route.request().headers()['origin'] ?? 'http://localhost:5173',
    'Access-Control-Allow-Credentials': 'true',
  }
}
async function fulfill(route: Route, data: unknown) {
  await route.fulfill({
    status: 200,
    headers: cors(route),
    contentType: 'application/json',
    body: JSON.stringify({ data }),
  })
}
async function installPreviews(page: Page, parentTotal: number, comboTotal: number) {
  await page.route('**/api/v1/products/relations-preview', async (route) => {
    if (route.request().method() === 'OPTIONS') return route.fallback()
    const previews: Preview[] = [
      {
        product_id: String(parent.id),
        total: parentTotal,
        products: [simple, { ...child, status: 'DRAFT' }].slice(0, Math.min(parentTotal, 2)),
      },
      {
        product_id: String(combo.id),
        total: comboTotal,
        products: [simple, child].slice(0, Math.min(comboTotal, 2)),
      },
    ]
    await fulfill(route, previews)
  })
}
for (const count of [0, 1, 2, 4]) {
  test(`Parent/COMBO preview with ${count} related products remains compact`, async ({ page }) => {
    const api = await mockLookupBatch(page)
    await installPreviews(page, count, count)
    await page.goto('/products')
    for (const code of [parent.product_code, combo.product_code]) {
      const row = productRow(page, code)
      const summary = row.locator('.relation-summary')
      await expect(summary).toContainText(`${count} mục`)
      await expect(summary.locator('.relation-product')).toHaveCount(Math.min(count, 2))
      await expect(summary.getByRole('button', { name: 'Xem thêm' })).toHaveCount(count > 2 ? 1 : 0)
      await expect(summary.locator('.relation-overflow')).toHaveCount(count > 2 ? 1 : 0)
      await expect(summary.locator('.relation-overflow')).toHaveText(
        count > 2 ? [`+${count - 2} mục khác`] : [],
      )
    }
    await expect(page.locator('.expanded-row')).toHaveCount(0)
    expect(api.calls.filter((call) => call.path.includes('/combo-items'))).toHaveLength(0)
    expect(api.calls.filter((call) => call.path.endsWith('/details/resolve'))).toHaveLength(0)
    expect(api.unexpected).toEqual([])
  })
}

test('Parent with 4 children opens a readable, on-demand list including DRAFT children', async ({
  page,
}) => {
  const api = await mockLookupBatch(page)
  await installPreviews(page, 4, 0)
  const children = [
    { ...child, id: '33', product_code: 'CHILD-A', full_name: 'Child Alpha', status: 'ACTIVE' },
    { ...child, id: '34', product_code: 'CHILD-B', full_name: 'Child Beta', status: 'DRAFT' },
    { ...child, id: '35', product_code: 'CHILD-C', full_name: 'Child Gamma', status: 'CLOSED' },
    { ...child, id: '36', product_code: 'CHILD-D', full_name: 'Child Delta', status: 'ACTIVE' },
  ]
  let childListCalls = 0
  await page.route(/\/api\/v1\/products\?.*/, async (route) => {
    const url = new URL(route.request().url())
    if (!url.search.includes('parent_product_id')) return route.fallback()
    if (route.request().method() === 'OPTIONS') return route.fallback()
    childListCalls += 1
    await route.fulfill({
      status: 200,
      headers: cors(route),
      contentType: 'application/json',
      body: JSON.stringify({
        data: children,
        meta: {
          page: Number(url.searchParams.get('page') ?? '1'),
          page_size: Number(url.searchParams.get('page_size') ?? '200'),
          total: children.length,
        },
      }),
    })
  })
  await page.goto('/products')
  const row = productRow(page, parent.product_code)
  await expect(row.locator('.relation-product')).toHaveCount(2)
  await expect(row).toContainText('+2 mục khác')
  expect(childListCalls).toBe(0)
  await row.getByRole('button', { name: 'Xem thêm' }).click()
  const dialog = page.getByRole('dialog', { name: /Sản phẩm con của E2E Parent/ })
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('tbody tr')).toHaveCount(4)
  await expect(dialog).toContainText('Child Delta')
  await expect(dialog).toContainText('Bản nháp')
  await expect(dialog).toContainText('Đã đóng')
  expect(childListCalls).toBe(1)
  await dialog.getByRole('button', { name: 'Đóng' }).first().click()
  await expect(dialog).toHaveCount(0)
  expect(api.unexpected).toEqual([])
})

test('COMBO components expand into a full table with quantities and only one batched detail request', async ({
  page,
}) => {
  const api = await mockLookupBatch(page)
  await installPreviews(page, 0, 4)
  const components = [
    simple,
    child,
    { ...child, id: '34', product_code: 'COMP-C', full_name: 'Component Gamma', status: 'DRAFT' },
    { ...child, id: '35', product_code: 'COMP-D', full_name: 'Component Delta', status: 'ACTIVE' },
  ]
  let comboCalls = 0
  let resolveCalls = 0
  await page.route('**/api/v1/products/32/combo-items', async (route) => {
    if (route.request().method() === 'OPTIONS') return route.fallback()
    comboCalls += 1
    await fulfill(
      route,
      components.map((component, index) => ({
        id: String(91 + index),
        combo_product_id: combo.id,
        component_product_id: component.id,
        quantity: `${index + 1}.500000`,
      })),
    )
  })
  await page.route('**/api/v1/products/details/resolve', async (route) => {
    if (route.request().method() === 'OPTIONS') return route.fallback()
    resolveCalls += 1
    const { product_ids: ids } = route.request().postDataJSON() as { product_ids: string[] }
    await fulfill(
      route,
      components.filter((component) => ids.includes(component.id)),
    )
  })
  await page.goto('/products')
  const row = productRow(page, combo.product_code)
  await expect(row.locator('.relation-product')).toHaveCount(2)
  expect(comboCalls).toBe(0)
  expect(resolveCalls).toBe(0)
  await row.getByRole('button', { name: 'Xem thêm' }).click()
  const dialog = page.getByRole('dialog', { name: /Thành phần của E2E Combo/ })
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('tbody tr')).toHaveCount(4)
  await expect(dialog.getByRole('columnheader', { name: 'Số lượng' })).toBeVisible()
  await expect(dialog).toContainText('Component Delta')
  await expect(dialog).toContainText('Component Gamma')
  await expect(dialog.locator('tbody tr').first()).toContainText(/1[.,]5/)
  expect(comboCalls).toBe(1)
  expect(resolveCalls).toBe(1)
  await dialog.getByRole('button', { name: 'Đóng' }).first().click()
  await row.getByRole('button', { name: 'Xem thêm' }).click()
  await expect(dialog.locator('tbody tr')).toHaveCount(4)
  expect(comboCalls).toBe(1)
  expect(resolveCalls).toBe(1)
  expect(api.unexpected).toEqual([])
})
