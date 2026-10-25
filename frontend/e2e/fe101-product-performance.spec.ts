import { expect, test } from '@playwright/test'
import { mockLookupBatch } from './support/lookupBatchMock'
import { productRow } from './support/productRows'

function countCalls(
  calls: ReadonlyArray<{ path: string; method: string }>,
  path: string,
  method = 'GET',
): number {
  return calls.filter((call) => call.path === path && call.method === method).length
}

test('M10.1 Product list reentry reuses scoped catalogs but refreshes Product/PBAC data', async ({
  page,
}) => {
  const api = await mockLookupBatch(page)
  await page.goto('/products')
  await expect(productRow(page, 'E2E-PARENT')).toBeVisible()
  await expect.poll(() => countCalls(api.calls, '/api/v1/products/relations-preview', 'POST'))
    .toBe(1)
  expect(countCalls(api.calls, '/api/v1/products')).toBe(1)
  expect(countCalls(api.calls, '/api/v1/product-groups')).toBe(1)
  expect(countCalls(api.calls, '/api/v1/product-uoms')).toBe(1)

  // The brand is a Vue RouterLink, so this navigates in the SAME SPA session.
  // A full page.goto would discard the in-memory cache and test a different case.
  await page.getByRole('link', { name: 'Dinktech' }).click()
  await expect(page).toHaveURL(/\/$/)
  await page.goBack()
  await expect(productRow(page, 'E2E-PARENT')).toBeVisible()
  await expect.poll(() => countCalls(api.calls, '/api/v1/products')).toBe(2)
  await expect.poll(() => countCalls(api.calls, '/api/v1/products/relations-preview', 'POST'))
    .toBe(2)

  // Group/UOM are presentation labels for 20s only; Product and relation data
  // still go back to PBAC endpoints on every list mount.
  expect(countCalls(api.calls, '/api/v1/product-groups')).toBe(1)
  expect(countCalls(api.calls, '/api/v1/product-uoms')).toBe(1)
  expect(countCalls(api.calls, '/api/v1/products/details/resolve', 'POST')).toBe(0)
})
