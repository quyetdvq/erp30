import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const group = {
  id: '10',
  parent_id: null,
  code: 'APP',
  name: 'Apparel',
  description: null,
  status: 'ACTIVE',
}
const uom = { id: '20', code: 'PCS', name: 'Piece', description: null, status: 'ACTIVE' }
const simple = {
  id: '30',
  product_code: 'PRD-001',
  sku: 'SKU-1',
  barcode: null,
  parent_product_id: null,
  product_name: 'Shirt',
  product_child_name: null,
  product_group_id: '10',
  product_type: 'STANDARD',
  base_uom_id: '20',
  price: '125000.00',
  description: null,
  serial_management_enabled: true,
  status: 'ACTIVE',
  role: 'SIMPLE',
  full_name: 'Shirt',
  selectable: true,
  leaf_transactionable: true,
}
const parent = {
  ...simple,
  id: '31',
  product_code: 'PRD-PARENT',
  product_name: 'Shirt',
  base_uom_id: null,
  price: null,
  serial_management_enabled: false,
  role: 'PARENT',
  full_name: 'Shirt',
  selectable: false,
  leaf_transactionable: false,
}

async function mockSession(page: Page): Promise<void> {
  await page.route('**/api/v1/auth/me', async (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'product-admin',
            email: 'product@example.test',
            active: true,
            status: 'ACTIVE',
            groups: [],
            base_permissions: [
              'product_group.view',
              'product_group.create',
              'product_group.edit',
              'product_group.setstate',
              'product_uom.view',
              'product_uom.create',
              'product_uom.edit',
              'product_uom.setstate',
              'product.view',
              'product.create',
              'product.edit',
              'product.setstate',
            ],
          },
        },
      }),
    }),
  )
}

async function mockCatalog(page: Page): Promise<{
  groupCreates: number
  uomCreates: number
  productListUrls: string[]
  comboWrites: number
}> {
  let groupCreates = 0
  let uomCreates = 0
  let comboWrites = 0
  const productListUrls: string[] = []
  await page.route('**/api/v1/product-groups*', async (route) => {
    if (route.request().method() === 'POST') {
      groupCreates += 1
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { ...group, id: '11', parent_id: '10', code: 'TSH', name: 'T-Shirts' },
        }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [group, { ...group, id: '11', parent_id: '10', code: 'TSH', name: 'T-Shirts' }],
        meta: { page: 1, page_size: 50, total: 2 },
      }),
    })
  })
  await page.route('**/api/v1/product-uoms*', async (route) => {
    if (route.request().method() === 'POST') {
      uomCreates += 1
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: uom }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [uom], meta: { page: 1, page_size: 50, total: 1 } }),
    })
  })
  await page.route('**/api/v1/products*', async (route) => {
    if (route.request().method() === 'POST')
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: simple }),
      })
    if (route.request().url().includes('/api/v1/products?'))
      productListUrls.push(route.request().url())
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [simple], meta: { page: 1, page_size: 50, total: 1 } }),
    })
  })
  await page.route('**/api/v1/products/*/combo-items', async (route) => {
    if (route.request().method() === 'PUT') comboWrites += 1
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    })
  })
  return {
    get groupCreates() {
      return groupCreates
    },
    get uomCreates() {
      return uomCreates
    },
    productListUrls,
    get comboWrites() {
      return comboWrites
    },
  }
}

test.describe('P1-FE-060 Product/UOM UI', () => {
  test('renders the nested Product menu and Table First Product list with axe clean output', async ({
    page,
  }) => {
    await mockSession(page)
    await mockCatalog(page)
    await page.goto('/products')
    await expect(page.getByRole('heading', { name: 'Sản phẩm' })).toBeVisible()
    await expect(page.getByRole('navigation', { name: /Phân trang|pagination/i })).toHaveCount(2)
    await expect(page.getByRole('columnheader', { name: /Product Code|Mã sản phẩm/ })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'PRD-001', exact: true })).toBeVisible()
    await expect(page.getByRole('menuitem', { name: 'Sản phẩm' })).toBeVisible()
    await page.goto('/products/groups')
    await expect(page.getByRole('heading', { name: 'Danh mục sản phẩm' })).toBeVisible()
    await page.goto('/products/uoms')
    await expect(page.getByRole('heading', { name: 'Đơn vị tính' })).toBeVisible()
    await expect(page.getByRole('menuitem', { name: 'Chính sách giá' })).toHaveCount(0)
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter(({ impact }) => impact === 'critical')).toEqual([])
  })

  test('supports Product Group hierarchy and UOM create flows', async ({ page }) => {
    await mockSession(page)
    const requests = await mockCatalog(page)
    await page.goto('/products/groups')
    await expect(page.getByRole('columnheader', { name: 'Tên' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Mở rộng' })).toBeVisible()
    await page.locator('.page-heading .primary-button').dispatchEvent('click')
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByRole('dialog').getByLabel('Mã').fill('NEW')
    await page.getByRole('dialog').getByLabel('Tên').fill('New group')
    await page
      .getByRole('dialog')
      .getByRole('button', { name: 'Lưu' })
      .evaluate((element) => (element as HTMLButtonElement).click())
    await expect.poll(() => requests.groupCreates).toBe(1)
    await page.goto('/products/uoms')
    await page.locator('.page-heading .primary-button').dispatchEvent('click')
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByRole('dialog').getByLabel('Mã').fill('BOX')
    await page.getByRole('dialog').getByLabel('Tên').fill('Box')
    await page
      .getByRole('dialog')
      .getByRole('button', { name: 'Lưu' })
      .evaluate((element) => (element as HTMLButtonElement).click())
    await expect.poll(() => requests.uomCreates).toBe(1)
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter(({ impact }) => impact === 'critical')).toEqual([])
  })

  test('keeps SIMPLE conversion scope and COMBO serial UX bounded', async ({ page }) => {
    await mockSession(page)
    const requests = await mockCatalog(page)
    await page.route('**/api/v1/products/30/uom-conversions', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '40',
              product_id: '30',
              uom_id: '20',
              conversion_factor: '12.0000',
              price: '140000.00',
              usage_type: 'SALES',
              status: 'ACTIVE',
            },
          ],
        }),
      }),
    )
    await page.goto('/products')
    await page
      .getByRole('button', { name: 'Shirt' })
      .evaluate((element) => (element as HTMLButtonElement).click())
    await expect(page.getByRole('dialog', { name: 'Chi tiết sản phẩm' })).toBeVisible()
    await page
      .getByRole('tab', { name: 'Quy đổi đơn vị' })
      .evaluate((element) => (element as HTMLButtonElement).click())
    await expect(page.getByRole('heading', { name: 'Quy đổi UOM' })).toBeVisible()
    await expect(page.getByRole('cell', { name: /12\.0000/ })).toBeVisible()
    await expect(page.getByRole('cell', { name: '140000.00', exact: true })).toBeVisible()
    await page.goto('/products/new')
    await expect(page.getByRole('heading', { name: 'Tạo sản phẩm' })).toBeVisible()
    expect(
      requests.productListUrls.some(
        (url) => new URL(url).searchParams.get('sort') === 'product_name',
      ),
    ).toBe(true)
    await page.getByRole('combobox', { name: 'Type' }).selectOption('COMBO')
    await expect(page.getByRole('heading', { name: 'Quy đổi UOM' })).toHaveCount(0)
    await expect(page.getByText('Sản phẩm cha')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Thành phần COMBO' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Thêm thành phần' })).toBeEnabled()
    await page
      .getByRole('button', { name: 'Thêm thành phần' })
      .evaluate((element) => (element as HTMLButtonElement).click())
    await expect(page.getByRole('textbox', { name: 'Số lượng' })).toBeVisible()
    await expect(page.getByText('COMBO luôn tắt quản lý Serial.')).toBeVisible()
  })

  test('keeps PARENT administrative edit available while locking operational fields', async ({
    page,
  }) => {
    await mockSession(page)
    await mockCatalog(page)
    let patchBody: Record<string, unknown> | null = null
    await page.route('**/api/v1/products/31', async (route) => {
      if (route.request().method() === 'PATCH') {
        patchBody = JSON.parse(route.request().postData() ?? '{}') as Record<string, unknown>
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: parent }),
        })
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: parent }),
      })
    })
    await page.goto('/products/31')
    await expect(page.getByText('PARENT là vai trò dẫn xuất')).toBeVisible()
    await expect(page.getByText('PARENT không có UOM cơ sở hoặc giá mặc định.')).toBeVisible()
    await expect(page.getByRole('checkbox', { name: 'Quản lý Serial' })).toHaveCount(0)
    await expect(page.locator('form .primary-button')).toBeEnabled()
    await page
      .getByRole('button', { name: 'Lưu' })
      .first()
      .evaluate((element) => (element as HTMLButtonElement).click())
    await expect.poll(() => patchBody).not.toBeNull()
    expect(patchBody).not.toHaveProperty('status')
  })
})
