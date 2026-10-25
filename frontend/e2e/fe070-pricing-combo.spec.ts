import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { fulfillWorkflowMock, mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

test.beforeEach(async ({ page }) => {
  await mockBusinessWorkflowStates(page)
})

const organization = {
  id: '1',
  code: 'GRP',
  name: 'Demo Group',
  type: 'GROUP',
  status: 'ACTIVE',
}
const baseUom = { id: '20', code: 'PCS', name: 'Piece', status: 'ACTIVE' }
const alternateUom = { id: '21', code: 'BOX', name: 'Box', status: 'ACTIVE' }
const simple = {
  id: '30',
  product_code: 'PRD-SIMPLE',
  sku: 'SKU-SIMPLE',
  full_name: 'Simple product',
  product_name: 'Simple product',
  product_type: 'STANDARD',
  role: 'SIMPLE',
  base_uom_id: '20',
  status: 'ACTIVE',
  selectable: true,
}
const parent = {
  ...simple,
  id: '31',
  product_code: 'PRD-PARENT',
  full_name: 'Parent product',
  product_name: 'Parent product',
  role: 'PARENT',
  base_uom_id: null,
  status: 'ACTIVE',
  selectable: false,
}
const combo = {
  ...simple,
  id: '32',
  product_code: 'PRD-COMBO',
  full_name: 'Combo product',
  product_name: 'Combo product',
  product_type: 'COMBO',
  role: 'COMBO',
}
const priceList = {
  id: '70',
  code: 'RETAIL',
  name: 'Retail prices',
  organization_id: '1',
  effective_from: '2026-10-01T00:00:00Z',
  effective_to: null,
  priority: 10,
  status: 'ACTIVE',
  description: null,
  allowed_actions: { edit: true, setstate: true },
}

async function mockSession(
  page: Page,
  permissions = ['price_list.view', 'price_list.create', 'price_list.edit', 'price_list.setstate'],
): Promise<void> {
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
            username: 'pricing-admin',
            email: 'pricing@example.test',
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

async function mockCatalog(page: Page): Promise<{
  createBody: Record<string, unknown> | null
  patchBody: Record<string, unknown> | null
  stateAction: { method: string; body: Record<string, unknown> } | null
  itemBody: unknown
}> {
  let createBody: Record<string, unknown> | null = null
  let patchBody: Record<string, unknown> | null = null
  let stateAction: { method: string; body: Record<string, unknown> } | null = null
  let currentPriceListStatus = 'ACTIVE'
  let itemBody: unknown = null
  let priceListItems = [
    { id: '71', price_list_id: '70', product_id: '30', uom_id: '20', price: '125000.00' },
  ]
  await page.route('**/api/v1/organizations*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [organization], meta: { page: 1, page_size: 200, total: 1 } }),
    }),
  )
  await page.route('**/api/v1/products/*/uom-conversions', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: '90',
            product_id: '30',
            uom_id: '21',
            conversion_factor: '12.0000',
            usage_type: 'SALES',
            status: 'ACTIVE',
          },
        ],
      }),
    }),
  )
  await page.route('**/api/v1/products*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [simple, parent, combo],
        meta: { page: 1, page_size: 200, total: 3 },
      }),
    }),
  )
  await page.route('**/api/v1/product-uoms*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [baseUom, alternateUom],
        meta: { page: 1, page_size: 200, total: 2 },
      }),
    }),
  )
  // The post-v1.0.7 frontend uses lookup/batch APIs. Exact route mocks must
  // supersede the broad legacy products*/organizations*/product-uoms* mocks;
  // otherwise a Product list response is incorrectly returned for a resolve.
  const lookupResponse = (
    route: import('@playwright/test').Route,
    data: unknown,
    meta?: object,
  ) => {
    const headers = {
      'Access-Control-Allow-Origin': route.request().headers()['origin'] ?? 'http://localhost:5173',
      'Access-Control-Allow-Credentials': 'true',
      'Access-Control-Allow-Methods': 'GET,POST,PATCH,PUT,OPTIONS',
      'Access-Control-Allow-Headers':
        'Accept,Accept-Language,Content-Type,X-Request-ID,X-CSRF-TOKEN',
    }
    if (route.request().method() === 'OPTIONS') return route.fulfill({ status: 204, headers })
    return route.fulfill({
      status: 200,
      headers,
      contentType: 'application/json',
      body: JSON.stringify({ data, ...(meta ? { meta } : {}) }),
    })
  }
  await page.route('**/api/v1/products/options?*', (route) =>
    lookupResponse(route, [simple, combo], { page: 1, page_size: 20, total: 2 }),
  )
  await page.route('**/api/v1/products/options/resolve', (route) =>
    lookupResponse(route, [simple, combo]),
  )
  await page.route('**/api/v1/products/sales-uom-options/resolve', (route) =>
    lookupResponse(route, [
      {
        product_id: '30',
        options: [
          { id: '20', code: 'PCS' },
          { id: '21', code: 'BOX' },
        ],
      },
      { product_id: '32', options: [{ id: '20', code: 'PCS' }] },
    ]),
  )
  await page.route('**/api/v1/organizations/labels/resolve', (route) =>
    lookupResponse(route, [organization]),
  )
  await page.route('**/api/v1/product-uoms/labels/resolve', (route) =>
    lookupResponse(route, [baseUom, alternateUom]),
  )
  await page.route('**/api/v1/business-workflow/price_list/70/options', (route) =>
    fulfillWorkflowMock(route, {
      data: {
        mode: 'LIFECYCLE',
        current: currentPriceListStatus,
        options:
          currentPriceListStatus === 'ACTIVE'
            ? [{ action_code: 'deactivate', status: 'INACTIVE' }]
            : [],
      },
    }),
  )
  await page.route('**/api/v1/business-workflow/price_list/70/actions/deactivate', (route) => {
    if (route.request().method() === 'OPTIONS') return fulfillWorkflowMock(route, {})
    stateAction = {
      method: route.request().method(),
      body: route.request().postDataJSON() as Record<string, unknown>,
    }
    currentPriceListStatus = 'INACTIVE'
    return fulfillWorkflowMock(route, { data: { action_code: 'deactivate', status: 'INACTIVE' } })
  })
  await page.route(/\/api\/v1\/price-lists(?:\/|\?|$)/, async (route) => {
    const request = route.request()
    const url = request.url()
    const pathname = new URL(url).pathname
    if (request.method() === 'OPTIONS') {
      return route.fulfill({
        status: 204,
        headers: {
          'Access-Control-Allow-Origin': request.headers()['origin'] ?? 'http://localhost:5173',
          'Access-Control-Allow-Credentials': 'true',
          'Access-Control-Allow-Methods': 'GET,POST,PATCH,PUT,OPTIONS',
          'Access-Control-Allow-Headers':
            'Accept, Accept-Language, Content-Type, X-Request-ID, X-CSRF-TOKEN',
        },
      })
    }
    if (pathname.endsWith('/items') && request.method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: priceListItems }),
      })
    }
    if (pathname.endsWith('/items') && request.method() === 'PUT') {
      // OpenAPI PriceListItemsRequest accepts both an array and { items: [...] }.
      const body = request.postDataJSON() as
        Array<Record<string, string>> | { items: Array<Record<string, string>> }
      const submittedItems = Array.isArray(body) ? body : body.items
      if (!Array.isArray(submittedItems)) {
        throw new Error('Invalid PriceListItemsRequest in E2E mock')
      }
      itemBody = submittedItems
      priceListItems = submittedItems.map((item, index) => ({
        id: String(71 + index),
        price_list_id: '70',
        product_id: item.product_id!,
        uom_id: item.uom_id!,
        price: item.price!,
      }))
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [] }),
      })
    }
    if (request.method() === 'PATCH') {
      patchBody = request.postDataJSON() as Record<string, unknown>
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: priceList }),
      })
    }
    if (request.method() === 'POST') {
      createBody = request.postDataJSON() as Record<string, unknown>
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...priceList, ...createBody, id: '71', status: 'DRAFT' } }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [{ ...priceList, status: currentPriceListStatus }],
        meta: { page: 1, page_size: 50, total: 1 },
      }),
    })
  })
  return {
    get createBody() {
      return createBody
    },
    get patchBody() {
      return patchBody
    },
    get stateAction() {
      return stateAction
    },
    get itemBody() {
      return itemBody
    },
  }
}

test.describe('P1-FE-070 Pricing/COMBO UI', () => {
  test('renders permission-aware Price List Table First screen and keeps critical axe clean', async ({
    page,
  }) => {
    await mockSession(page)
    await mockCatalog(page)
    await page.goto('/products/price-lists')
    await expect(page.getByRole('heading', { name: 'Chính sách giá' })).toBeVisible()
    await expect(page.getByRole('columnheader', { name: 'Mã' })).toBeVisible()
    await expect(page.getByRole('columnheader', { name: 'Phạm vi Organization' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'RETAIL', exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Làm mới' })).toBeVisible()
    await expect(page.getByLabel('Số dòng mỗi trang')).toHaveCount(2)
    await expect(page.getByRole('img', { name: 'Đang hoạt động' })).toBeVisible()
    await expect(page.getByRole('menuitem', { name: 'Chính sách giá' })).toBeVisible()
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter(({ impact }) => impact === 'critical')).toEqual([])
  })

  test('creates a Price List with the canonical organization scope fields', async ({ page }) => {
    await mockSession(page)
    const requests = await mockCatalog(page)
    await page.goto('/products/price-lists')
    await page.getByRole('button', { name: 'Tạo' }).click()
    const dialog = page.getByRole('dialog', { name: 'Biểu mẫu Price List' })
    const backdrop = page.locator('.modal-backdrop')
    await expect(backdrop).toHaveCSS('position', 'fixed')
    await expect(backdrop).toHaveCSS('z-index', '1000')
    await expect(dialog).toBeVisible()
    await dialog.getByLabel('Mã').fill('WHOLESALE')
    await dialog.getByLabel('Tên').fill('Wholesale prices')
    await dialog.locator('.n-base-selection').click()
    await page.getByText('GRP - Demo Group (GROUP)', { exact: true }).click()
    await dialog.getByLabel('Hiệu lực từ').fill('2026-10-01T00:00')
    await dialog.getByLabel('Độ ưu tiên').fill('20')
    await dialog.getByRole('button', { name: 'Lưu' }).click()
    await expect.poll(() => requests.createBody).not.toBeNull()
    expect(requests.createBody).toMatchObject({
      code: 'WHOLESALE',
      name: 'Wholesale prices',
      organization_id: '1',
      priority: 20,
    })
    expect(requests.createBody).not.toHaveProperty('status')
  })

  test('keeps the Price List modal open for backdrop clicks and Escape', async ({ page }) => {
    await mockSession(page)
    await mockCatalog(page)
    await page.goto('/products/price-lists')
    await page.getByRole('button', { name: 'Tạo' }).click()

    const dialog = page.getByRole('dialog', { name: 'Biểu mẫu Price List' })
    const backdrop = page.locator('.modal-backdrop')
    await expect(dialog).toBeVisible()
    await backdrop.click({ position: { x: 8, y: 8 } })
    await expect(dialog).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(dialog).toBeVisible()
    await dialog.getByRole('button', { name: 'Hủy' }).click()
    await expect(dialog).toBeHidden()
  })

  test('uses PATCH for editable fields, Lifecycle Transition for status, and preserves item decimals', async ({
    page,
  }) => {
    await mockSession(page)
    const requests = await mockCatalog(page)
    await page.goto('/products/price-lists')
    const row = page.getByRole('row').filter({ hasText: 'RETAIL' })
    await row.getByRole('button', { name: 'Sửa' }).click()
    await page.getByRole('dialog').getByLabel('Tên').fill('Retail prices updated')
    await page.getByRole('dialog').getByRole('button', { name: 'Lưu' }).click()
    await expect.poll(() => requests.patchBody).not.toBeNull()
    expect(requests.patchBody).not.toHaveProperty('status')
    await row.getByRole('button', { name: 'Chuyển trạng thái' }).click()
    const transitionDialog = page.getByRole('dialog', { name: 'Chuyển trạng thái' })
    const transitionSelect = transitionDialog.getByRole('combobox', {
      name: 'Transition được phép',
    })
    await expect(
      transitionSelect.getByRole('option', { name: 'INACTIVE (deactivate)' }),
    ).toHaveCount(1)
    await transitionSelect.selectOption({ label: 'INACTIVE (deactivate)' })
    await transitionDialog.getByRole('button', { name: 'Lưu' }).click()
    await expect.poll(() => requests.stateAction).toEqual({ method: 'POST', body: {} })
    await page
      .getByRole('row')
      .filter({ hasText: 'RETAIL' })
      .getByRole('button', { name: 'Xem chi tiết' })
      .click()
    const viewDialog = page.getByRole('dialog', { name: 'Xem chi tiết dòng giá' })
    await expect(viewDialog).toBeVisible()
    await expect(viewDialog.getByText('125,000.00')).toBeVisible()
    await expect(viewDialog.getByRole('textbox', { name: 'Giá' })).toHaveCount(0)
    await expect(viewDialog.getByRole('button', { name: 'Thêm Product/UOM' })).toHaveCount(0)
    await expect(viewDialog.getByRole('button', { name: 'Lưu' })).toHaveCount(0)
    await viewDialog.locator('.modal-card__footer').getByRole('button', { name: 'Đóng', exact: true }).click()

    const activeRow = page.getByRole('row').filter({ hasText: 'RETAIL' })
    await activeRow.getByRole('button', { name: 'Sửa', exact: true }).click()
    const editForm = page.getByRole('dialog', { name: 'Biểu mẫu Price List' })
    await editForm.getByRole('button', { name: 'Chỉnh sửa dòng giá' }).click()
    const itemDialog = page.getByRole('dialog', { name: 'Chỉnh sửa dòng giá' })
    await expect(itemDialog).toBeVisible()
    await expect(itemDialog.getByRole('textbox', { name: 'Giá' }).first()).toHaveValue('125,000.00')
    await itemDialog.getByRole('button', { name: 'Thêm Product/UOM' }).click()
    const itemRow = itemDialog.locator('tbody tr').last()
    const productSelect = itemRow.locator('.n-base-selection').first()
    await productSelect.click()
    await expect(page.getByText('Parent product (PARENT)', { exact: true })).toHaveCount(0)
    await page.getByText('Combo product (COMBO)', { exact: true }).click()
    const uomSelect = itemRow.locator('.n-base-selection').nth(1)
    await uomSelect.click()
    await expect(page.getByText('BOX', { exact: true })).toHaveCount(0)
    await page.locator('.n-base-select-option').filter({ hasText: 'PCS' }).last().click()
    await itemDialog.getByRole('textbox', { name: 'Giá' }).last().fill('999999999999.0000')
    await itemDialog.getByRole('button', { name: 'Lưu' }).click()
    await expect
      .poll(() => requests.itemBody)
      .toEqual([
        { product_id: '30', uom_id: '20', price: '125000.00' },
        { product_id: '32', uom_id: '20', price: '999999999999.00' },
      ])
    await expect(itemDialog.getByRole('textbox', { name: 'Giá' }).last()).toHaveValue(
      '999,999,999,999.00',
    )
  })

  test('hides create/edit/state actions without base permissions while retaining backend-authoritative list access', async ({
    page,
  }) => {
    await mockSession(page, ['price_list.view'])
    await mockCatalog(page)
    await page.goto('/products/price-lists')
    await expect(page.getByRole('button', { name: 'Tạo' })).toHaveCount(0)
    const row = page.getByRole('row').filter({ hasText: 'RETAIL' })
    await expect(row.getByRole('button', { name: 'Xem chi tiết' })).toBeVisible()
    await expect(row.getByRole('button', { name: 'Sửa' })).toHaveCount(0)
    await expect(row.getByRole('button', { name: 'Chuyển trạng thái' })).toHaveCount(0)
    await row.getByRole('button', { name: 'Xem chi tiết' }).click()
    const viewDialog = page.getByRole('dialog', { name: 'Xem chi tiết dòng giá' })
    await expect(viewDialog).toBeVisible()
    await expect(viewDialog.getByRole('button', { name: 'Thêm Product/UOM' })).toHaveCount(0)
    await expect(viewDialog.getByRole('button', { name: 'Lưu' })).toHaveCount(0)
  })
})
