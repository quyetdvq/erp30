import { expect, test } from '@playwright/test'
import { mockLookupBatch } from './support/lookupBatchMock'

test('Price List View is read-only; Edit exposes its own item editor', async ({ page }) => {
  const api = await mockLookupBatch(page)
  await page.goto('/products/price-lists')
  const row = page.getByRole('row').filter({ hasText: 'E2E-RETAIL' })

  await row.getByRole('button', { name: 'Xem chi tiết' }).click()
  const viewing = page.getByRole('dialog', { name: 'Xem chi tiết dòng giá' })
  await expect(viewing).toBeVisible()
  await expect(viewing.getByText('Chế độ xem — chỉ đọc')).toBeVisible()
  await expect(viewing.getByText('125,000.00')).toBeVisible()
  await expect(viewing.getByRole('textbox')).toHaveCount(0)
  await expect(viewing.getByRole('button', { name: 'Thêm Product/UOM' })).toHaveCount(0)
  await expect(viewing.getByRole('button', { name: 'Xóa' })).toHaveCount(0)
  await expect(viewing.getByRole('button', { name: 'Lưu' })).toHaveCount(0)
  await viewing.locator('.modal-card__footer').getByRole('button', { name: 'Đóng', exact: true }).click()
  expect(api.calls.filter((call) => call.path === '/api/v1/price-lists/70/items' && call.method === 'PUT')).toHaveLength(0)

  await row.getByRole('button', { name: 'Sửa', exact: true }).click()
  const editForm = page.getByRole('dialog', { name: 'Biểu mẫu Price List' })
  await expect(editForm.getByLabel('Tên')).toBeEditable()
  await expect(editForm.getByRole('button', { name: 'Lưu' })).toBeVisible()
  await editForm.getByRole('button', { name: 'Chỉnh sửa dòng giá' }).click()

  const itemEditor = page.getByRole('dialog', { name: 'Chỉnh sửa dòng giá' })
  await expect(itemEditor).toBeVisible()
  await expect(itemEditor.getByRole('textbox', { name: 'Giá' })).toHaveValue('125,000.00')
  await expect(itemEditor.getByRole('button', { name: 'Thêm Product/UOM' })).toBeVisible()
  await expect(itemEditor.getByRole('button', { name: 'Xóa' })).toBeVisible()
  await expect(itemEditor.getByRole('button', { name: 'Lưu' })).toBeVisible()
  expect(api.unexpected).toEqual([])
})

test('Switching from an unsaved Price List form to item editor asks before discarding', async ({ page }) => {
  const api = await mockLookupBatch(page)
  await page.goto('/products/price-lists')
  await page.getByRole('row').filter({ hasText: 'E2E-RETAIL' })
    .getByRole('button', { name: 'Sửa', exact: true }).click()
  const form = page.getByRole('dialog', { name: 'Biểu mẫu Price List' })
  await form.getByLabel('Tên').fill('Changed but not saved')

  page.once('dialog', (dialog) => void dialog.dismiss())
  await form.getByRole('button', { name: 'Chỉnh sửa dòng giá' }).click()
  await expect(form).toBeVisible()
  await expect(form.getByLabel('Tên')).toHaveValue('Changed but not saved')

  page.once('dialog', (dialog) => void dialog.accept())
  await form.getByRole('button', { name: 'Chỉnh sửa dòng giá' }).click()
  await expect(page.getByRole('dialog', { name: 'Chỉnh sửa dòng giá' })).toBeVisible()
  expect(api.calls.filter((call) => call.path === '/api/v1/price-lists' && call.method === 'PATCH')).toHaveLength(0)
  expect(api.unexpected).toEqual([])
})