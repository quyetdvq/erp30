import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const resources = [
  { id: '2', type: 'PRODUCT', name: 'Products', domain: 'MASTER' },
  { id: '3', type: 'ORDER', name: 'Orders', domain: 'MASTER' },
]

const series = {
  id: '11',
  resource_id: '2',
  series_code: 'DEFAULT',
  name: 'Default Product Number',
  scope_type: 'GROUP',
  pattern: 'PRD-{SEQ:6}',
  reset_policy: 'NEVER',
  generation_mode: 'AUTO_REQUIRED',
  start_number: '1',
  increment_by: 1,
  is_default: true,
  status: 'ACTIVE',
  issued: false,
}

async function mockSession(
  page: Page,
  permissions = ['resource.view', 'resource.configure_numbering'],
) {
  await page.route('**/api/v1/auth/csrf', (route) =>
    route.fulfill({
      status: 200,
      headers: { 'X-CSRF-TOKEN': 'csrf-e2e' },
      contentType: 'application/json',
      body: JSON.stringify({ data: { csrf_token: 'csrf-e2e', header_name: 'X-CSRF-TOKEN' } }),
    }),
  )
  await page.route('**/api/v1/auth/me', async (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'numbering-admin',
            email: 'numbering@example.test',
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

async function mockNumberingApi(page: Page, options: { conflict?: boolean } = {}) {
  let validateRequests = 0
  let counterRequests = 0
  let patchRequests = 0
  let createRequests = 0
  let stateRequests = 0
  let detailRequests = 0

  await page.route('**/api/v1/resources*', async (route) => {
    const url = new URL(route.request().url())
    if (url.pathname === '/api/v1/resources' && route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: resources, meta: { page: 1, page_size: 200, total: 2 } }),
      })
    }
    return route.continue()
  })

  await page.route('**/api/v1/resources/2/numbering-series**', async (route) => {
    const url = new URL(route.request().url())
    const method = route.request().method()
    if (url.pathname.endsWith('/actions/validate-config')) {
      validateRequests += 1
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { valid: true, normalized_pattern: 'PRD-{SEQ:6}', validation_messages: [] },
          meta: { request_id: 'validate-1' },
        }),
      })
    }
    if (url.pathname.endsWith('/actions/set-state')) {
      stateRequests += 1
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...series, status: 'INACTIVE' } }),
      })
    }
    if (/\/numbering-series\/\d+$/.test(url.pathname) && method === 'GET') {
      detailRequests += 1
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: series }),
      })
    }
    if (/\/numbering-series\/\d+$/.test(url.pathname) && method === 'PATCH') {
      patchRequests += 1
      if (options.conflict) {
        return route.fulfill({
          status: 409,
          contentType: 'application/json',
          body: JSON.stringify({
            error: { code: 'NUM_ISSUED_FIELD_IMMUTABLE', message: 'issued' },
            meta: { request_id: 'conflict-1' },
          }),
        })
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: series }),
      })
    }
    if (url.pathname.endsWith('/numbering-series') && method === 'POST') {
      createRequests += 1
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: series }),
      })
    }
    if (url.pathname.endsWith('/numbering-series') && method === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [series], meta: { page: 1, page_size: 1, total: 1 } }),
      })
    }
    if (url.pathname.includes('/counters')) counterRequests += 1
    return route.continue()
  })

  return {
    get validateRequests() {
      return validateRequests
    },
    get counterRequests() {
      return counterRequests
    },
    get patchRequests() {
      return patchRequests
    },
    get createRequests() {
      return createRequests
    },
    get stateRequests() {
      return stateRequests
    },
    get detailRequests() {
      return detailRequests
    },
  }
}

test.describe('P1-FE-050 Auto Numbering configuration UI', () => {
  test('keeps Table First structure and covers CRUD/state/validation flows', async ({ page }) => {
    await mockSession(page)
    const requests = await mockNumberingApi(page)
    page.on('dialog', (dialog) => dialog.accept())
    await page.goto('/configuration/auto-numbering')

    await expect(page.getByRole('heading', { name: 'Auto Numbering' })).toBeVisible()
    await expect(page.getByRole('search')).toBeVisible()
    await expect(page.getByRole('navigation', { name: /Phân trang|pagination/i })).toHaveCount(2)
    const headers = await page
      .getByRole('table', { name: 'Danh sách dữ liệu' })
      .getByRole('columnheader')
      .allTextContents()
    expect(headers.map((header) => header.trim()).slice(-2)).toEqual(['Trạng thái', 'Thao tác'])
    await expect(page.getByRole('cell', { name: 'DEFAULT', exact: true })).toBeVisible()

    await page
      .getByRole('button', { name: /Tạo numbering series|Create numbering series/ })
      .dispatchEvent('click')
    const dialog = page.getByRole('dialog', {
      name: /Biểu mẫu Auto Numbering|Auto Numbering form/,
    })
    await expect(dialog).toBeVisible()
    await dialog.getByLabel('Mã series').fill('MONTHLY')
    await dialog.getByLabel('Tên').fill('Monthly series')
    await dialog.getByRole('textbox', { name: 'Pattern' }).fill('PRD-')
    await dialog
      .getByRole('button', { name: /Chèn token: SEQ|Insert token: SEQ/ })
      .dispatchEvent('click')
    await expect(dialog.getByRole('textbox', { name: 'Pattern' })).toHaveValue('PRD-{SEQ:6}')
    await dialog
      .getByRole('button', { name: /Kiểm tra cấu hình|Validate configuration/ })
      .dispatchEvent('click')
    await expect(dialog.getByText(/Cấu hình hợp lệ|Configuration is valid/)).toBeVisible()
    await expect(dialog.getByText(/không giữ chỗ|does not reserve/)).toBeVisible()
    expect(requests.validateRequests).toBe(1)
    expect(requests.counterRequests).toBe(0)
    await dialog.getByLabel('Số bắt đầu').fill('10')
    await dialog.getByLabel('Bước tăng').fill('2')
    await dialog.getByRole('button', { name: /Lưu|Save/ }).dispatchEvent('click')
    await expect(dialog).toHaveCount(0)
    await expect.poll(() => requests.createRequests).toBe(1)

    // Batch 3 disables row actions until the save AND subsequent list refresh finish.
    const defaultRow = page.getByRole('row', { name: /DEFAULT/ })
    const viewAction = defaultRow.getByRole('button', { name: /Xem chi tiết|View details/ })
    await expect(viewAction).toBeEnabled()
    await viewAction.dispatchEvent('click')
    await expect(
      page.getByRole('dialog', { name: /Chi tiết numbering|Numbering series details/ }),
    ).toBeVisible()
    await expect.poll(() => requests.detailRequests).toBe(1)
    await page
      .getByRole('button', { name: /Đóng|Close/ })
      .last()
      .dispatchEvent('click')
    const editAction = defaultRow.getByRole('button', { name: /Sửa|Edit/ })
    await expect(editAction).toBeEnabled()
    await editAction.dispatchEvent('click')
    const editDialog = page.getByRole('dialog', {
      name: /Biểu mẫu Auto Numbering|Auto Numbering form/,
    })
    await editDialog.getByLabel('Tên').fill('Updated series')
    await editDialog.getByRole('button', { name: /Lưu|Save/ }).dispatchEvent('click')
    await expect.poll(() => requests.patchRequests).toBe(1)
    await expect(editDialog).toHaveCount(0)
    // A PATCH reaching the mock does not mean busy has cleared after loadSeries().
    // Do not bypass disabled controls with dispatchEvent while a save is in progress.
    const deactivateAction = defaultRow.getByRole('button', { name: /Ngừng hoạt động|Deactivate/ })
    await expect(deactivateAction).toBeEnabled()
    await deactivateAction.dispatchEvent('click')
    await expect.poll(() => requests.stateRequests).toBe(1)

    const panelA11y = await new AxeBuilder({ page }).include('.numbering-panel').analyze()
    expect(panelA11y.violations).toEqual([])
  })

  test('asks before discarding an edited numbering form and keeps the draft if cancelled', async ({
    page,
  }) => {
    await mockSession(page)
    const requests = await mockNumberingApi(page)
    let confirmations = 0
    page.on('dialog', async (confirmation) => {
      confirmations += 1
      expect(confirmation.type()).toBe('confirm')
      if (confirmations === 1) await confirmation.dismiss()
      else await confirmation.accept()
    })
    await page.goto('/configuration/auto-numbering')
    await expect(page.getByRole('cell', { name: 'DEFAULT', exact: true })).toBeVisible()
    await page
      .getByRole('button', { name: /Tạo numbering series|Create numbering series/ })
      .dispatchEvent('click')
    const form = page.getByRole('dialog', { name: /Biểu mẫu Auto Numbering|Auto Numbering form/ })
    await expect(form).toBeVisible()
    await form.getByLabel('Mã series').fill('NOT_SAVED')
    await form.getByRole('button', { name: /Hủy|Cancel/ }).click()
    await expect(form).toBeVisible()
    await expect(form.getByLabel('Mã series')).toHaveValue('NOT_SAVED')
    expect(requests.createRequests).toBe(0)
    await form.getByRole('button', { name: /Hủy|Cancel/ }).click()
    await expect(form).toHaveCount(0)
    expect(confirmations).toBe(2)
    expect(requests.createRequests).toBe(0)
  })

  test('hides controls without configure permission', async ({ page }) => {
    await mockSession(page, [])
    await mockNumberingApi(page)
    await page.goto('/configuration/auto-numbering')
    await expect(
      page.getByRole('button', { name: /Tạo numbering series|Create numbering series/ }),
    ).toHaveCount(0)
    await expect(page.getByText(/không có quyền|do not have permission/)).toBeVisible()
  })

  test('locks immutable fields after the backend reports issued conflict', async ({ page }) => {
    await mockSession(page)
    const conflictRequests = await mockNumberingApi(page, { conflict: true })
    await page.goto('/configuration/auto-numbering')
    await page
      .getByRole('row', { name: /DEFAULT/ })
      .getByRole('button', { name: /Sửa|Edit/ })
      .dispatchEvent('click')
    const dialog = page.getByRole('dialog', {
      name: /Biểu mẫu Auto Numbering|Auto Numbering form/,
    })
    await dialog.getByLabel('Tên').fill('Attempted issued edit')
    await dialog.getByRole('button', { name: /Lưu|Save/ }).dispatchEvent('click')
    await expect(dialog.getByText(/NUM_ISSUED_FIELD_IMMUTABLE/)).toBeVisible()
    await expect(dialog.getByLabel('Mã series')).toBeDisabled()
    await expect(dialog.getByRole('combobox', { name: /Phạm vi|Scope/ })).toBeDisabled()
    await expect.poll(() => conflictRequests.patchRequests).toBe(1)
  })
})
