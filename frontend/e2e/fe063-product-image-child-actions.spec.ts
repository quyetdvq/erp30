import { expect, test, type Route } from '@playwright/test'
import { child, mockLookupBatch, simple } from './support/lookupBatchMock'
import { mockPagination } from './support/mockPagination'

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWZ8AAAAASUVORK5CYII=',
  'base64',
)

const fileProduct = {
  ...simple,
  product_group_id: '10',
  sku: 'SIMPLE-30',
  image_available: false,
  price: '1200.00',
}

// Route handlers live outside tests, so each test's assertions stay unconditional.
function imageRoute(onUpload: (route: Route) => void, isUploaded: () => boolean) {
  return async (route: Route) => {
    const method = route.request().method()
    if (method === 'OPTIONS') return route.fallback()
    if (method === 'POST') {
      onUpload(route)
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...fileProduct, image_available: isUploaded() } }),
      })
    }
    return route.fulfill({ status: 200, contentType: 'image/png', body: png })
  }
}

async function childListRoute(route: Route) {
  const query = new URL(route.request().url()).searchParams
  if (!Array.from(query.keys()).some((name) => name.includes('parent_product_id')))
    return route.fallback()
  const row = { ...child, status: 'DRAFT', full_name: 'E2E Draft Child', product_code: 'E2E-DRAFT', allowed_actions: { edit: true, setstate: false } }
  return route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: [row], meta: mockPagination(route, 1) }),
  })
}

test('Product Editor uploads a representative image through authorized multipart API', async ({
  page,
}) => {
  const api = await mockLookupBatch(page)
  let uploaded = false
  let savedCount = 0
  let uploadHeaders: Record<string, string> = {}
  let uploadBody: Buffer | null = null
  await page.route(
    '**/api/v1/products/30/image',
    imageRoute(
      (route) => {
        uploaded = true
        uploadHeaders = route.request().headers()
        uploadBody = route.request().postDataBuffer()
      },
      () => uploaded,
    ),
  )
  await page.route('**/api/v1/products/30', async (route) => {
    savedCount += Number(route.request().method() === 'PATCH')
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { ...fileProduct, image_available: uploaded } }),
    })
  })
  await page.goto('/products/30')
  await expect(page.getByText('Ảnh minh họa').first()).toBeVisible()
  await page
    .getByLabel('Chọn ảnh')
    .setInputFiles({ name: 'sample.png', mimeType: 'image/png', buffer: png })
  await expect(page.locator('.product-image-field img')).toBeVisible()
  await page.getByRole('button', { name: 'Lưu' }).first().click()
  await expect.poll(() => uploaded).toBe(true)
  expect(uploadHeaders['content-type']).toContain('multipart/form-data')
  expect(uploadBody).not.toBeNull()
  expect(uploadBody?.includes(Buffer.from('image'))).toBe(true)
  expect(savedCount).toBe(1)
  expect(api.unexpected).toEqual([])
})

test('Product child table uses icon State and permission-scoped view/edit actions', async ({
  page,
}) => {
  const api = await mockLookupBatch(page)
  await page.route('**/api/v1/products?*', childListRoute)
  await page.goto('/products')
  await page.getByRole('button', { name: 'E2E Parent', exact: true }).first().click()
  const modal = page.getByRole('dialog', { name: 'Chi tiết sản phẩm' })
  await modal.getByRole('tab', { name: 'Sản phẩm con' }).click()
  const row = modal.getByRole('row').filter({ hasText: 'E2E Draft Child' })
  await expect(row.getByRole('img', { name: 'Bản nháp' })).toBeVisible()
  await expect(row.getByRole('button', { name: 'Xem chi tiết E2E Draft Child' })).toBeVisible()
  await expect(row.getByRole('button', { name: 'Sửa E2E Draft Child' })).toBeVisible()
  expect(api.unexpected).toEqual([])
})
