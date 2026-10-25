import { test, expect } from '@playwright/test'
import { mockLookupBatch } from './support/lookupBatchMock'
import { productRow } from './support/productRows'

test('M10.2 Product: base edit granted but PBAC denies row edit', async ({ page }) => {
  const api = await mockLookupBatch(page, undefined, { denyProductEdit: true })
  await page.goto('/products')
  const row = productRow(page, 'E2E-SIMPLE')
  await expect(row).toBeVisible()
  await expect(row.getByRole('button', { name: 'Xem chi tiết', exact: true })).toBeVisible()
  await expect(row.getByRole('button', { name: 'Sửa', exact: true })).toHaveCount(0)
  expect(api.unexpected).toEqual([])
})

test('M10.2 Price List: scoped VIEW stays visible while mutation hint denies edit', async ({ page }) => {
  const api = await mockLookupBatch(page, undefined, { denyPriceListEdit: true })
  await page.goto('/products/price-lists')
  const row = page.getByRole('row').filter({ hasText: 'E2E-RETAIL' })
  await expect(row).toBeVisible()
  await expect(row.getByRole('button', { name: 'Xem chi tiết' })).toBeVisible()
  await expect(row.getByRole('button', { name: 'Sửa' })).toHaveCount(0)
  await row.getByRole('button', { name: 'Xem chi tiết' }).click()
  const dialog = page.getByRole('dialog', { name: 'Xem chi tiết dòng giá' })
  await expect(dialog).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Thêm Product/UOM' })).toHaveCount(0)
  await expect(dialog.getByRole('button', { name: 'Xóa' })).toHaveCount(0)
  await expect(dialog.getByRole('button', { name: 'Lưu' })).toHaveCount(0)
  await expect(dialog.getByRole('textbox', { name: 'Giá' })).toHaveCount(0)
  expect(api.calls.filter((call) => call.path === '/api/v1/price-lists/70/items' && call.method === 'PUT')).toHaveLength(0)
  expect(api.unexpected).toEqual([])
})
