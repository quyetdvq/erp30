import { expect, test, type Page } from '@playwright/test'
import { mockLookupBatch, type ApiCall } from './support/lookupBatchMock'

const callsTo = (calls: ApiCall[], path: string, method?: string) =>
  calls.filter((call) => call.path === path && (!method || call.method === method))
const distinctPaths = (calls: ApiCall[]) =>
  calls.filter((call) => call.path.startsWith('/api/v1/products/')).map((call) => call.path)

async function openPriceItems(page: Page) {
  const row = page.getByRole('row').filter({ hasText: 'E2E-RETAIL' })
  await row.getByRole('button', { name: 'Xem chi tiết' }).click()
  const dialog = page.getByRole('dialog', { name: 'Product Price List Items' })
  await expect(dialog).toBeVisible()
  return dialog
}

test.describe('E2E-LOOKUP-BATCH (browser contract, mocked API)', () => {
  test('Products initial load uses one paged list and one relation preview, never entire Product lookup', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page)
    await page.goto('/products')
    await expect(page.getByRole('heading', { name: 'Sản phẩm' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'E2E-PARENT', exact: true })).toBeVisible()
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/products/relations-preview', 'POST').length)
      .toBe(1)

    const listCalls = callsTo(api.calls, '/api/v1/products', 'GET')
    expect(listCalls).toHaveLength(1)
    expect(Number(listCalls[0]!.query.get('page_size'))).toBeLessThanOrEqual(50)
    const previews = callsTo(api.calls, '/api/v1/products/relations-preview', 'POST')
    expect(previews[0]?.body).toEqual({ product_ids: ['31', '32'], limit_per_product: 2 })
    expect(callsTo(api.calls, '/api/v1/products/options', 'GET')).toHaveLength(0)
    expect(callsTo(api.calls, '/api/v1/products/details/resolve', 'POST')).toHaveLength(0)
    expect(distinctPaths(api.calls).filter((path) => /\/\d+$/.test(path))).toHaveLength(0)
    expect(api.unexpected).toEqual([])
  })

  test('Expanding a COMBO fetches its relation once and resolves component details in one batch', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page)
    await page.goto('/products')
    const row = page.getByRole('row').filter({ hasText: 'E2E-COMBO' })
    await expect(row.getByRole('button', { name: 'Xem thêm' })).toBeVisible()
    await row.getByRole('button', { name: 'Xem thêm' }).click()
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/products/details/resolve', 'POST').length)
      .toBe(1)
    expect(callsTo(api.calls, '/api/v1/products/32/combo-items', 'GET')).toHaveLength(1)
    expect(callsTo(api.calls, '/api/v1/products/details/resolve', 'POST')[0]?.body).toEqual({
      product_ids: ['30', '33'],
    })
    expect(distinctPaths(api.calls).filter((path) => /\/\d+$/.test(path))).toHaveLength(0)
    await expect(page.getByRole('row').filter({ hasText: 'E2E-COMBO' })).toBeVisible()
    expect(api.unexpected).toEqual([])
  })

  test('Product Detail stays on General tab without loading conversions until selected', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page)
    await page.goto('/products')
    await expect(page.getByRole('cell', { name: 'E2E-SIMPLE', exact: true })).toBeVisible()
    await page
      .getByRole('row')
      .filter({ hasText: 'E2E-SIMPLE' })
      .getByRole('button', { name: 'E2E Simple' })
      .click()
    const detail = page.getByRole('dialog', { name: 'Chi tiết sản phẩm' })
    await expect(detail).toBeVisible()
    expect(callsTo(api.calls, '/api/v1/products/30/uom-conversions', 'GET')).toHaveLength(0)
    await detail.getByRole('tab', { name: 'Quy đổi đơn vị' }).click()
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/products/30/uom-conversions', 'GET').length)
      .toBe(1)
    expect(api.unexpected).toEqual([])
  })

  test('Price Lists initial load only requests paged Price Lists and owner labels', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page)
    await page.goto('/products/price-lists')
    await expect(page.getByRole('heading', { name: 'Chính sách giá' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'E2E-RETAIL', exact: true })).toBeVisible()
    await expect(page.getByRole('row').filter({ hasText: 'E2E-RETAIL' })).toContainText(
      'E2E-GRP - E2E Group',
    )
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/organizations/labels/resolve', 'POST').length)
      .toBe(1)
    expect(callsTo(api.calls, '/api/v1/organizations/labels/resolve', 'POST')[0]?.body).toEqual({
      organization_ids: ['1'],
    })
    expect(callsTo(api.calls, '/api/v1/price-lists', 'GET')).toHaveLength(1)
    expect(callsTo(api.calls, '/api/v1/organizations', 'GET')).toHaveLength(0)
    expect(callsTo(api.calls, '/api/v1/products', 'GET')).toHaveLength(0)
    expect(callsTo(api.calls, '/api/v1/product-uoms', 'GET')).toHaveLength(0)
    expect(callsTo(api.calls, '/api/v1/products/options', 'GET')).toHaveLength(0)
    expect(api.unexpected).toEqual([])
  })

  test('Price List Detail resolves Product and UOM only on demand, including inactive historical UOM label', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page)
    await page.goto('/products/price-lists')
    const dialog = await openPriceItems(page)
    await expect(dialog.getByRole('textbox', { name: 'Giá' })).toHaveValue('125,000.00')
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/product-uoms/labels/resolve', 'POST').length)
      .toBe(1)
    expect(callsTo(api.calls, '/api/v1/products/options/resolve', 'POST')[0]?.body).toEqual({
      product_ids: ['30'],
    })
    expect(
      callsTo(api.calls, '/api/v1/products/sales-uom-options/resolve', 'POST')[0]?.body,
    ).toEqual({ product_ids: ['30'] })
    expect(callsTo(api.calls, '/api/v1/product-uoms/labels/resolve', 'POST')[0]?.body).toEqual({
      uom_ids: ['99'],
    })
    expect(callsTo(api.calls, '/api/v1/price-lists/70/items', 'GET')).toHaveLength(1)
    expect(callsTo(api.calls, '/api/v1/products', 'GET')).toHaveLength(0)
    expect(
      distinctPaths(api.calls).filter((path) => path.endsWith('uom-conversions')),
    ).toHaveLength(0)
    expect(api.unexpected).toEqual([])
  })

  test('Price List Product select searches remotely; COMBO offers only base UOM; save sends canonical decimal strings', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page, undefined, { legacyItem: false })
    await page.goto('/products/price-lists')
    const dialog = await openPriceItems(page)
    await dialog.getByRole('button', { name: 'Thêm Product/UOM' }).click()
    const item = dialog.locator('tbody tr').last()
    await item.locator('.n-base-selection').first().click()
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/products/options', 'GET').length)
      .toBeGreaterThan(0)
    const search = callsTo(api.calls, '/api/v1/products/options', 'GET')[0]!
    expect(search.query.get('purpose')).toBe('price_list_item')
    expect(Number(search.query.get('page_size'))).toBeLessThanOrEqual(20)
    await expect(page.getByText('E2E Parent (PARENT)', { exact: true })).toHaveCount(0)
    await page.getByText('E2E Combo (COMBO)', { exact: true }).click()
    await expect
      .poll(() => callsTo(api.calls, '/api/v1/products/sales-uom-options/resolve', 'POST').length)
      .toBe(1)
    await item.locator('.n-base-selection').nth(1).click()
    await expect(page.getByText('BOX', { exact: true })).toHaveCount(0)
    await page.locator('.n-base-select-option').filter({ hasText: 'PCS' }).last().click()
    await item.getByRole('textbox', { name: 'Giá' }).fill('125000.50')
    await dialog.getByRole('button', { name: 'Lưu' }).click()
    await expect.poll(() => api.submittedItems).not.toBeNull()
    expect(api.submittedItems).toEqual({
      items: [{ product_id: '32', uom_id: '20', price: '125000.50' }],
    })
    expect(callsTo(api.calls, '/api/v1/products', 'GET')).toHaveLength(0)
    expect(api.unexpected).toEqual([])
  })

  test('Older Price List search response cannot overwrite a newer result or owner label', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page)
    let releaseSlow!: () => void
    let signalSlow!: () => void
    const oldRequestStarted = new Promise<void>((resolve) => {
      signalSlow = resolve
    })
    const waitSlow = new Promise<void>((resolve) => {
      releaseSlow = resolve
    })
    await page.route('**/api/v1/price-lists?*', async (route) => {
      const q = new URL(route.request().url()).searchParams.get('q')
      if (q !== 'slow' && q !== 'fast') return route.fallback()
      if (q === 'slow') {
        signalSlow()
        await waitSlow
      }
      const code = q === 'slow' ? 'SLOW-RESULT' : 'FAST-RESULT'
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '70',
              code,
              name: code,
              organization_id: '1',
              effective_from: '2026-10-01T00:00:00Z',
              effective_to: null,
              priority: 10,
              status: 'ACTIVE',
              description: null,
            },
          ],
          meta: { page: 1, page_size: 15, total: 1 },
        }),
      })
    })
    await page.goto('/products/price-lists')
    await expect(page.getByRole('cell', { name: 'E2E-RETAIL', exact: true })).toBeVisible()
    const search = page.getByRole('search').getByRole('searchbox', { name: 'Tìm kiếm' })
    try {
      await search.fill('slow')
      await page.getByRole('search').getByRole('button', { name: 'Áp dụng' }).click()
      await oldRequestStarted
      await search.fill('fast')
      await page.getByRole('search').getByRole('button', { name: 'Áp dụng' }).click()
      await expect(page.getByRole('cell', { name: 'FAST-RESULT', exact: true })).toBeVisible()
    } finally {
      const lateResponse = page.waitForResponse(
        (response) => new URL(response.url()).searchParams.get('q') === 'slow',
      )
      releaseSlow()
      await lateResponse
    }
    await expect(page.getByRole('cell', { name: 'SLOW-RESULT', exact: true })).toHaveCount(0)
    await expect(page.getByRole('cell', { name: 'FAST-RESULT', exact: true })).toBeVisible()
    expect(api.unexpected).toEqual([])
  })

  test('Base Permission snapshot only hides actions: UI must not be treated as backend PBAC proof', async ({
    page,
  }) => {
    const api = await mockLookupBatch(page, ['price_list.view'])
    await page.goto('/products/price-lists')
    await expect(page.getByRole('cell', { name: 'E2E-RETAIL', exact: true })).toBeVisible()
    const row = page.getByRole('row').filter({ hasText: 'E2E-RETAIL' })
    await expect(row.getByRole('button', { name: 'Xem chi tiết' })).toBeVisible()
    await expect(row.getByRole('button', { name: 'Sửa' })).toHaveCount(0)
    await expect(row.getByRole('button', { name: 'Ngừng hoạt động' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Tạo' })).toHaveCount(0)
    expect(api.unexpected).toEqual([])
  })
})
