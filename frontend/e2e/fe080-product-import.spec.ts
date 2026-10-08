import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const job = {
  id: '9007199254740993',
  entity_type: 'PRODUCT',
  mode: 'UPSERT',
  source_format: 'CSV',
  source_file_name: 'products.csv',
  source_file_hash: 'sha256:demo',
  status: 'COMPLETED_WITH_ERRORS',
  total_rows: 2,
  success_rows: 1,
  error_rows: 1,
  created_by: '7',
  updated_by: '7',
  started_at: '2026-10-08T08:00:00Z',
  completed_at: '2026-10-08T08:00:02Z',
  created_at: '2026-10-08T08:00:00Z',
  updated_at: '2026-10-08T08:00:02Z',
}

async function mockSession(page: Page, permissions: string[]): Promise<void> {
  await page.route('**/api/v1/auth/me', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'import-admin',
            email: 'import@example.test',
            active: true,
            status: 'ACTIVE',
            groups: [],
            base_permissions: permissions,
          },
        },
      }),
    }),
  )
}

async function mockImportApi(page: Page, startStatus = 201): Promise<void> {
  await page.route('**/api/v1/products/import-jobs', async (route) => {
    if (route.request().method() === 'POST') {
      return route.fulfill({
        status: startStatus,
        contentType: 'application/json',
        body:
          startStatus === 201
            ? JSON.stringify({ data: job })
            : JSON.stringify({ message: 'forbidden' }),
      })
    }
    return route.continue()
  })
  await page.route('**/api/v1/import-jobs?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [job], meta: { page: 1, page_size: 50, total: 1 } }),
    }),
  )
  await page.route(`**/api/v1/import-jobs/${job.id}`, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: job }),
    }),
  )
  await page.route(`**/api/v1/import-jobs/${job.id}/rows?*`, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            row_number: 2,
            row_status: 'SUCCESS',
            entity_key: 'PRD-GENERATED-001',
            target_ref_id: '9007199254740994',
            error_code: null,
            error_message: null,
            created_at: '2026-10-08T08:00:01Z',
          },
          {
            row_number: 3,
            row_status: 'ERROR',
            entity_key: null,
            target_ref_id: null,
            error_code: 'PRODUCT_INVALID',
            error_message: 'Name is required',
            created_at: '2026-10-08T08:00:02Z',
          },
        ],
        meta: { page: 1, page_size: 50, total: 2 },
      }),
    }),
  )
}

test.describe('P1-FE-080 Product Import UI/report', () => {
  test('runs the wizard, lists the job, shows canonical row DTO fields, and stays axe-clean', async ({
    page,
  }) => {
    await mockSession(page, ['product.import', 'import_job.view'])
    await mockImportApi(page)

    await page.goto('/products/import')
    await expect(page.getByRole('heading', { name: 'Nhập Product' })).toBeVisible()
    await expect(page.getByText('source_key là khóa nguồn')).toBeVisible()
    await expect(page.getByRole('radio', { name: 'UPSERT' })).toBeVisible()
    expect(
      (await new AxeBuilder({ page }).analyze()).violations.filter(
        ({ impact }) => impact === 'critical',
      ),
    ).toEqual([])

    await page.locator('input[type="file"]').setInputFiles({
      name: 'products.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from('product_code,name\n,Generated product\n'),
    })
    await page.getByRole('radio', { name: 'UPSERT' }).check()
    const importRequest = page.waitForRequest(
      (request) =>
        request.url().includes('/api/v1/products/import-jobs') && request.method() === 'POST',
    )
    await page.getByRole('button', { name: 'Bắt đầu import' }).click()
    const submitted = await importRequest
    expect(submitted.postData()).toContain('UPSERT')
    expect(submitted.postData()).toContain('products.csv')
    await expect(page).toHaveURL(new RegExp(`/products/import-jobs/${job.id}$`))
    await expect(page.getByRole('heading', { name: 'Chi tiết import job' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'PRD-GENERATED-001', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'PRODUCT_INVALID', exact: true })).toBeVisible()
    await expect(page.getByText('source_key', { exact: true })).toHaveCount(0)
    await expect(page.getByText('record_type', { exact: true })).toHaveCount(0)
    expect(
      (await new AxeBuilder({ page }).analyze()).violations.filter(
        ({ impact }) => impact === 'critical',
      ),
    ).toEqual([])

    await page.goto('/products/import-jobs')
    await expect(page.getByRole('heading', { name: 'Import jobs', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'products.csv', exact: true })).toBeVisible()
  })

  test('opens persisted job results from the Table First action by keyboard', async ({ page }) => {
    await mockSession(page, ['import_job.view'])
    await mockImportApi(page)

    await page.goto('/products/import-jobs')
    const viewAction = page.getByRole('button', { name: 'Xem' }).first()
    await expect(viewAction).toBeVisible()
    await viewAction.focus()
    await page.keyboard.press('Enter')
    await expect(page).toHaveURL(new RegExp(`/products/import-jobs/${job.id}$`))
    await expect(page.getByRole('cell', { name: 'PRODUCT_INVALID', exact: true })).toBeVisible()
    await expect(page.getByText('Name is required', { exact: true })).toBeVisible()
    expect(
      (await new AxeBuilder({ page }).analyze()).violations.filter(
        ({ impact }) => impact === 'critical',
      ),
    ).toEqual([])
  })

  test('keeps the start action hidden/disabled without the Base Permission hint', async ({
    page,
  }) => {
    await mockSession(page, ['product.view'])
    await page.goto('/products/import')
    await expect(page.getByRole('button', { name: 'Bắt đầu import' })).toBeDisabled()
    await expect(page.getByText('Base Permission product.import')).toBeVisible()
  })

  test('keeps backend 403 visible on the wizard', async ({ page }) => {
    await mockSession(page, ['product.import'])
    await mockImportApi(page, 403)
    await page.goto('/products/import')
    await page.locator('input[type="file"]').setInputFiles({
      name: 'products.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from('product_code,name\n,Generated product\n'),
    })
    await page.getByRole('button', { name: 'Bắt đầu import' }).click()
    await expect(page.getByText('Không thể bắt đầu import')).toBeVisible()
  })
})
