import { expect, test, type Page } from '@playwright/test'
import { mockLookupBatch, simple } from './support/lookupBatchMock'

/** PR02: authenticated browser fixture. No real Product writes or backend changes. */
async function openProductEditor(page: Page, url = '/products/new') {
  const mock = await mockLookupBatch(page)
  await page.goto(url)
  await expect(page.getByRole('heading', { name: 'Thông tin sản phẩm' })).toBeVisible()
  await expect(page.locator('.page-heading button[form="product-editor-form"]')).toBeEnabled()
  return mock
}

test('PR02 STANDARD editor: compact desktop cards and a read-only truthful summary', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  const mock = await openProductEditor(page)
  const main = (await page.locator('.product-editor-main').boundingBox())!
  const side = (await page.locator('.product-editor-side').boundingBox())!
  expect(side.x).toBeGreaterThan(main.x + main.width)
  await expect(page.getByRole('heading', { name: 'Đóng gói & kích thước' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Tóm tắt nhanh' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Ghi chú tương tác' })).toBeVisible()
  await expect(page.locator('.product-editor-summary')).toContainText('Chưa chọn')
  // Group, code, price and image are optional in the *real* Product validator.
  const basics = page.locator('#product-editor-basics-title').locator('..').locator('..')
  await expect(basics.getByText('Mã sản phẩm', { exact: true })).toBeVisible()
  await page.getByLabel('Tên sản phẩm').fill('Sản phẩm thử nghiệm')
  await expect(page.locator('.product-editor-summary')).toContainText('Sản phẩm thử nghiệm')
  await expect(page.locator('.product-editor-status-note')).toContainText('DRAFT')
  await expect(page.locator('.page-heading button[form="product-editor-form"]')).toBeEnabled()
  expect(mock.unexpected).toEqual([])
})

test('PR02 COMBO create keeps component table before final Save and disables Serial', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  const mock = await openProductEditor(page)
  await page.getByLabel('Loại sản phẩm').selectOption('COMBO')
  await expect(page.getByRole('heading', { name: 'Thành phần COMBO' })).toBeVisible()
  const section = page.locator('.relationship-section').filter({ has: page.getByRole('heading', { name: 'Thành phần COMBO' }) })
  await expect(page.locator('.product-editor-serial input[type="checkbox"]')).toBeDisabled()
  await section.getByRole('button', { name: 'Thêm thành phần' }).click()
  await expect(section.locator('tbody tr')).toHaveCount(1)
  const footer = (await page.locator('.editor-actions').boundingBox())!
  const components = (await section.boundingBox())!
  expect(components.y + components.height).toBeLessThanOrEqual(footer.y + 2)
  expect(mock.unexpected).toEqual([])
})

test('PR02 mobile editor stacks cards without overflowing or resizing shared controls', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 })
  const mock = await openProductEditor(page)
  const main = (await page.locator('.product-editor-main').boundingBox())!
  const side = (await page.locator('.product-editor-side').boundingBox())!
  expect(side.y).toBeGreaterThan(main.y)
  const code = (await page.getByLabel('Mã sản phẩm').boundingBox())!
  const sku = (await page.getByLabel('SKU').boundingBox())!
  expect(sku.y).toBeGreaterThan(code.y)
  expect(code.height).toBe(36)
  await expect(page.getByRole('heading', { name: 'Hình ảnh sản phẩm' })).toBeVisible()
  await expect(page.getByLabel('Chọn ảnh')).toHaveAttribute('type', 'file')
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(2)
  expect(mock.unexpected).toEqual([])
})

test('PR02 missing create privilege keeps form, Save and upload read-only', async ({ page }) => {
  const mock = await mockLookupBatch(page, ['product.view', 'product_uom.view'])
  await page.goto('/products/new')
  await expect(page.getByRole('heading', { name: 'Thông tin sản phẩm' })).toBeVisible()
  await expect(page.locator('.page-heading button[form="product-editor-form"]')).toBeDisabled()
  // fieldset.disabled is the native propagation contract; Playwright's
  // toBeDisabled() checks interactive controls, not the fieldset node itself.
  await expect(page.locator('#product-editor-form .product-editor-card fieldset').first()).toHaveAttribute('disabled', '')
  await expect(page.getByLabel('Tên sản phẩm')).toBeDisabled()
  // Naive NSelect is not a native fieldset control: pass disabled explicitly.
  await expect(page.getByLabel('Đơn vị cơ bản', { exact: true }).locator('.n-base-selection'))
    .toHaveClass(/n-base-selection--disabled/)
  await expect(page.locator('.product-image-field .ds-file-trigger')).toHaveCount(0)
  expect(mock.unexpected).toEqual([])
})

test('PR02 saving with current fields still posts canonical Product payload and navigates to Edit', async ({ page }) => {
  const mock = await mockLookupBatch(page)
  const submitted: Array<Record<string, unknown>> = []
  const saved = { ...simple, id: '77', product_code: null, sku: null, product_name: 'SP PR02', full_name: 'SP PR02', product_group_id: null, base_uom_id: '20', price: '100.00', allowed_actions: { edit: true, setstate: false } }
  await page.route('**/api/v1/products', async (route) => {
    if (route.request().method() !== 'POST') return route.fallback()
    submitted.push(route.request().postDataJSON() as Record<string, unknown>)
    return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: saved }) })
  })
  await page.route('**/api/v1/products/77', async (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ data: saved }),
  }))
  await page.route('**/api/v1/products/77/uom-conversions', async (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }),
  }))
  await page.goto('/products/new')
  await page.getByLabel('Tên sản phẩm').fill('SP PR02')
  await page.getByLabel('Giá mặc định').fill('100')
  // Naive NSelect renders its options in a portalled .n-base-select-menu.
  // They are not reliably exposed through ARIA role=option across versions.
  // Scope the trigger to this field and select the visible fixture label.
  const baseUom = page.getByLabel('Đơn vị cơ bản', { exact: true })
  await baseUom.locator('.n-base-selection').click()
  const pieceOption = page.locator('.n-base-select-menu .n-base-select-option')
    .filter({ hasText: 'Piece (PCS)' })
  await expect(pieceOption).toBeVisible()
  await pieceOption.click()
  await expect(baseUom).toContainText('Piece (PCS)')
  await page.locator('.page-heading button[form="product-editor-form"]').click()
  await expect.poll(() => submitted.length).toBe(1)
  expect(submitted[0]).toMatchObject({ product_name: 'SP PR02', product_type: 'STANDARD', base_uom_id: '20', price: '100', serial_management_enabled: false })
  expect(submitted[0]).not.toHaveProperty('product_group_id')
  await expect(page).toHaveURL(/\/products\/77(?:\?|$)/)
  expect(mock.unexpected).toEqual([])
})

test('PR02 edit mode keeps the existing Product role restrictions and record permissions', async ({ page }) => {
  const mock = await mockLookupBatch(page)
  const row = { ...simple, product_name: 'Sản phẩm đang sửa', product_type: 'STANDARD',
    role: 'SIMPLE', allowed_actions: { edit: true, setstate: false } }
  await page.route('**/api/v1/products/30', async (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ data: row }),
  }))
  await page.goto('/products/30')
  await expect(page.getByRole('heading', { name: 'Sửa sản phẩm' })).toBeVisible()
  await expect(page.locator('.page-heading button[form="product-editor-form"]')).toHaveText('Lưu thay đổi')
  await expect(page.locator('.page-heading button[form="product-editor-form"]')).toBeEnabled()
  await expect(page.getByLabel('Tên sản phẩm')).toHaveValue('Sản phẩm đang sửa')
  await expect(page.getByLabel('Loại sản phẩm')).toBeDisabled()
  await expect(page.locator('.product-editor-status-note')).toHaveCount(0)
  await expect(page.locator('.product-editor-summary')).toContainText('Sản phẩm đang sửa')
  expect(mock.unexpected).toEqual([])
})
