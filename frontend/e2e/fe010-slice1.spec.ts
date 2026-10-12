import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { mockPagination } from './support/mockPagination'
import { fulfillWorkflowMock, mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

test.beforeEach(async ({ page }) => {
  await mockBusinessWorkflowStates(page)
})

const groupId = '9007199254740993'
const companyId = '9007199254740994'
const warehouseId = '9007199254740995'

const collection = (data: unknown[], total = data.length) =>
  JSON.stringify({ data, meta: { page: 1, page_size: 50, total, request_id: 'fe010-test' } })

async function mockSession(page: Page): Promise<void> {
  await page.route('**/api/v1/auth/csrf', (route) =>
    route.fulfill({
      status: 200,
      headers: { 'X-CSRF-TOKEN': 'csrf-e2e' },
      contentType: 'application/json',
      body: JSON.stringify({ data: { csrf_token: 'csrf-e2e', header_name: 'X-CSRF-TOKEN' } }),
    }),
  )
  await page.route('**/api/v1/auth/me', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'admin',
            groups: ['orgadmin'],
            base_permissions: [
              'organization.view',
              'organization.create',
              'organization.edit',
              'organization.setstate',
              'organization_contact.view',
              'organization_contact.create',
              'organization_contact.edit',
              'organization_contact.setstate',
              'province.view',
              'province.create',
              'province.edit',
              'province.setstate',
              'ward.view',
              'ward.create',
              'ward.edit',
              'ward.setstate',
            ],
          },
        },
      }),
    }),
  )
}

async function mockOrganizationApi(
  page: Page,
  options: {
    treeStatus?: number
    contactUpdateBody?: { value: Record<string, unknown> | null }
  } = {},
): Promise<void> {
  const tree = [
    {
      id: groupId,
      parent_id: undefined,
      type: 'GROUP',
      code: 'G-01',
      name: 'Root Group',
      status: 'ACTIVE',
      active_from: null,
      active_to: null,
    },
    {
      id: companyId,
      parent_id: groupId,
      type: 'COMPANY',
      code: 'C-01',
      name: 'Main Company',
      status: 'ACTIVE',
      legal_name: 'Main Company LLC',
      tax_code: 'T-01',
      active_from: '2026-01-01',
      active_to: null,
    },
    {
      id: warehouseId,
      parent_id: companyId,
      type: 'WAREHOUSE',
      code: 'W-01',
      name: 'Main Warehouse',
      status: 'ACTIVE',
      active_from: null,
      active_to: null,
    },
  ]
  await page.route('**/api/v1/organizations/tree**', (route) =>
    route.fulfill({
      status: options.treeStatus ?? 200,
      contentType: 'application/json',
      body: options.treeStatus
        ? JSON.stringify({
            error: { code: 'NOT_FOUND', message: 'Not found' },
            meta: { request_id: 'fe010-not-found' },
          })
        : collection(tree),
    }),
  )
  await page.route('**/api/v1/organizations', async (route) => {
    if (route.request().method() === 'POST' || route.request().method() === 'PATCH') {
      return route.fulfill({
        status: route.request().method() === 'POST' ? 201 : 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: tree[1], meta: { request_id: 'fe010-write' } }),
      })
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: collection(tree) })
  })
  await page.route('**/api/v1/contacts**', async (route) => {
    if (route.request().method() === 'PATCH') {
      if (options.contactUpdateBody) {
        options.contactUpdateBody.value = route.request().postDataJSON() as Record<string, unknown>
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: '7001',
            contact_type: 'ORGANIZATION',
            contact_ref_id: warehouseId,
            name: 'Alice Updated',
            email: 'alice@test',
            phone: '0900',
            mobile: null,
            status: 'ACTIVE',
          },
          meta: { request_id: 'fe010-contact-update' },
        }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection([
        {
          id: '7001',
          contact_type: 'ORGANIZATION',
          contact_ref_id: warehouseId,
          name: 'Alice',
          email: 'alice@test',
          phone: '0900',
          mobile: null,
          status: 'ACTIVE',
        },
      ]),
    })
  })
}

test('Organization tree, parent matrix, contacts history, and accessibility', async ({ page }) => {
  await mockSession(page)
  const contactUpdateBody: { value: Record<string, unknown> | null } = { value: null }
  await mockOrganizationApi(page, { contactUpdateBody })
  await page.route('**/api/v1/provinces**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: mockPagination(route, 0, 'fe010-provinces') }),
    }),
  )
  await page.goto('/organizations')

  await expect(page.getByRole('button', { name: /G-01/ })).toBeVisible()
  await expect(page.getByText('Main Company')).toBeVisible()
  await page
    .getByRole('button', { name: /G-01/ })
    .evaluate((element) => (element as HTMLElement).click())
  await page
    .getByRole('button', { name: /Tạo đơn vị con/ })
    .evaluate((element) => (element as HTMLElement).click())
  await expect(
    page.locator('.modal-card').getByRole('option', { name: 'COMPANY', exact: true }),
  ).toHaveCount(1)
  await expect(page.getByText('Thông tin Company')).toBeVisible()
  await page
    .getByRole('button', { name: 'Đóng' })
    .evaluate((element) => (element as HTMLElement).click())

  await page
    .getByRole('button', { name: /W-01/ })
    .evaluate((element) => (element as HTMLElement).click())
  await expect(page.getByRole('button', { name: /Tạo đơn vị con/ })).toBeDisabled()
  await page
    .getByRole('tab', { name: 'Liên hệ' })
    .evaluate((element) => (element as HTMLElement).click())
  await expect(page.getByRole('cell', { name: 'Alice', exact: true })).toBeVisible()
  const contactRegion = page.getByRole('region', { name: 'Liên hệ' })
  await contactRegion.getByRole('button', { name: 'Xem chi tiết' }).click()
  await expect(page.getByRole('dialog', { name: /Xem chi tiết/ })).toContainText('Alice')
  await page
    .getByRole('dialog', { name: /Xem chi tiết/ })
    .locator('.table-detail-modal__close')
    .click()
  await contactRegion.getByRole('button', { name: 'Sửa liên hệ' }).click()
  await page.locator('.modal-card input').first().fill('Alice Updated')
  await page.getByRole('button', { name: 'Lưu' }).click()
  await expect.poll(() => contactUpdateBody.value).not.toBeNull()
  expect(contactUpdateBody.value).not.toHaveProperty('contact_type')
  expect(contactUpdateBody.value).not.toHaveProperty('contact_ref_id')
  // Contact PATCH cannot change status; state mutations use set-state.
  expect(contactUpdateBody.value).not.toHaveProperty('status')
  expect(contactUpdateBody.value).toMatchObject({ name: 'Alice Updated' })
  await contactRegion
    .getByRole('tab', { name: 'Lịch sử' })
    .evaluate((element) => (element as HTMLElement).click())
  await expect(contactRegion.getByRole('tab', { name: 'Lịch sử' })).toHaveAttribute(
    'aria-selected',
    'true',
  )

  const accessibility = await new AxeBuilder({ page }).include('.organization-detail').analyze()
  expect(accessibility.violations).toEqual([])
})

test('Ward filtering and active Province-only create selection', async ({ page }) => {
  await mockSession(page)
  const provinces = [
    { id: '11', code: 'P1', name: 'Active Province', status: 'ACTIVE' },
    { id: '13', code: 'P3', name: 'North Province', status: 'ACTIVE' },
    { id: '12', code: 'P2', name: 'Inactive Province', status: 'INACTIVE' },
  ]
  await page.route('**/api/v1/provinces**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: provinces,
        meta: mockPagination(route, provinces.length, 'fe010-province-lookup'),
      }),
    }),
  )
  await page.route('**/api/v1/wards**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection([
        {
          id: '21',
          province_id: '11',
          code: 'W1',
          name: 'Ward One',
          name_full: 'Ward One, Active Province',
          status: 'ACTIVE',
        },
      ]),
    }),
  )
  await page.goto('/settings/locations/wards')
  await expect(page.getByRole('cell', { name: 'Ward One', exact: true })).toBeVisible()
  await page
    .getByRole('button', { name: /Tạo/ })
    .evaluate((element) => (element as HTMLElement).click())
  const provinceSearch = page.locator('.modal-card').getByRole('textbox').first()
  await provinceSearch.click()
  await provinceSearch.fill('NORTH')
  const provinceMenu = page.locator('.n-base-select-menu').last()
  await expect(provinceMenu.getByText('P3 - North Province', { exact: true })).toBeVisible()
  await expect(provinceMenu.getByText('P2 - Inactive Province', { exact: true })).toHaveCount(0)
})

test('Ward edit keeps status on set-state only, without changing the canonical Province', async ({
  page,
}) => {
  await mockSession(page)
  const ward = {
    id: '21',
    province_id: '11',
    code: 'W1',
    name: 'Ward One',
    name_full: 'Ward One, Province One',
    status: 'ACTIVE',
  }
  const province = { id: '11', code: 'P1', name: 'Province One', status: 'ACTIVE' }
  const patched: { value: Record<string, unknown> | null } = { value: null }
  await page.route('**/api/v1/provinces**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [province], meta: mockPagination(route, 1) }),
    }),
  )
  await page.route('**/api/v1/wards**', (route) => {
    if (route.request().method() === 'PATCH') {
      patched.value = route.request().postDataJSON() as Record<string, unknown>
      ward.name = String(patched.value.name)
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: ward, meta: { request_id: 'ward-patch' } }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [ward], meta: mockPagination(route, 1) }),
    })
  })

  await page.goto('/settings/locations/wards')
  const row = page
    .getByRole('row')
    .filter({ has: page.getByRole('cell', { name: 'Ward One', exact: true }) })
  await row.getByRole('button', { name: 'Sửa' }).click()
  const dialog = page.getByRole('dialog', { name: 'Biểu mẫu' })
  await expect(dialog.getByRole('combobox', { name: 'Trạng thái' })).toBeDisabled()
  await dialog.getByRole('textbox', { name: 'Tên', exact: true }).fill('Ward Renamed')
  await dialog.getByRole('button', { name: 'Lưu' }).click()
  await expect.poll(() => patched.value).not.toBeNull()
  expect(patched.value).toMatchObject({ name: 'Ward Renamed', province_id: '11' })
  expect(patched.value).not.toHaveProperty('status')
})

test('Province selector retrieves later pages rather than silently truncating at 200', async ({
  page,
}) => {
  await mockSession(page)
  const provinces = Array.from({ length: 205 }, (_, index) => ({
    id: String(index + 1),
    code: `P${index + 1}`,
    name: `Province ${index + 1}`,
    status: 'ACTIVE',
  }))
  await page.route('**/api/v1/provinces**', (route) => {
    const query = new URL(route.request().url()).searchParams
    const pageIndex = Number(query.get('page') || '1')
    const pageSize = Number(query.get('page_size') || '50')
    const start = (pageIndex - 1) * pageSize
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: provinces.slice(start, start + pageSize),
        meta: mockPagination(route, provinces.length),
      }),
    })
  })
  await page.route('**/api/v1/wards**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: mockPagination(route, 0) }),
    }),
  )
  await page.goto('/settings/locations/wards')
  await page.getByRole('button', { name: /Tạo/ }).click()
  const selector = page.locator('.modal-card').getByRole('textbox').first()
  await selector.click()
  await selector.fill('P205')
  await expect(
    page.locator('.n-base-select-menu').last().getByText('P205 - Province 205'),
  ).toBeVisible()
})

test('Province create lets the server assign the initial Workflow State', async ({ page }) => {
  await mockSession(page)
  const posted: { value: Record<string, unknown> | null } = { value: null }
  await page.route('**/api/v1/provinces**', (route) => {
    if (route.request().method() === 'POST') {
      posted.value = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { id: '51', code: 'PV51', name: 'New Province', status: 'DRAFT' },
          meta: { request_id: 'fe010-workflow-create' },
        }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [], meta: mockPagination(route, 0) }),
    })
  })

  await page.goto('/settings/locations/provinces')
  await page.getByRole('button', { name: /Tạo/ }).click()
  const dialog = page.getByRole('dialog', { name: 'Biểu mẫu' })
  await expect(dialog.getByRole('note')).toContainText('Workflow State')
  await expect(dialog.getByRole('combobox', { name: 'Trạng thái' })).toHaveCount(0)
  await dialog.getByRole('textbox', { name: 'Mã' }).fill('PV51')
  await dialog.getByRole('textbox', { name: 'Tên' }).fill('New Province')
  await dialog.getByRole('button', { name: 'Lưu' }).click()
  await expect.poll(() => posted.value).not.toBeNull()
  expect(posted.value).toMatchObject({ code: 'PV51', name: 'New Province' })
  expect(posted.value).not.toHaveProperty('status')
})

test('403 and 404 list responses remain visible as common error states', async ({ page }) => {
  await mockSession(page)
  await page.route('**/api/v1/provinces**', (route) =>
    route.fulfill({
      status: 403,
      contentType: 'application/json',
      body: JSON.stringify({
        error: { code: 'FORBIDDEN', message: 'Forbidden' },
        meta: { request_id: 'fe010-forbidden' },
      }),
    }),
  )
  await page.goto('/settings/locations/provinces')
  await expect(page.locator('.list-shell__state[role="alert"]')).toBeVisible()

  await mockOrganizationApi(page, { treeStatus: 404 })
  await page.goto('/organizations')
  await expect(page.locator('.list-shell__state[role="alert"]')).toBeVisible()
})

test('Lifecycle-bound Province CREATE and authorized Transition use configured States', async ({
  page,
}) => {
  await mockSession(page)
  const rows = [{ id: '51', code: 'PV51', name: 'Draft Province', status: 'DRAFT' }]
  const posted: { value: Record<string, unknown> | null } = { value: null }
  const actions: string[] = []
  await page.route('**/api/v1/business-workflow/province/states', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { mode: 'LIFECYCLE', initial: 'DRAFT', states: ['DRAFT', 'APPROVED', 'CLOSED'] },
        meta: { request_id: 'workflow-states' },
      }),
    }),
  )
  await page.route('**/api/v1/business-workflow/province/51/options', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          mode: 'LIFECYCLE',
          current: rows[0]!.status,
          options:
            rows[0]!.status === 'DRAFT' ? [{ action_code: 'approve', status: 'APPROVED' }] : [],
        },
        meta: { request_id: 'workflow-options' },
      }),
    }),
  )
  await page.route('**/api/v1/business-workflow/province/51/actions/approve', (route) => {
    if (route.request().method() === 'OPTIONS') return fulfillWorkflowMock(route, {})
    actions.push(route.request().method())
    rows[0]!.status = 'APPROVED'
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { status: 'APPROVED', action_code: 'approve' } }),
    })
  })
  await page.route('**/api/v1/provinces**', (route) => {
    if (route.request().method() === 'POST') {
      posted.value = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: rows[0] }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: rows, meta: mockPagination(route, 1) }),
    })
  })
  await page.goto('/settings/locations/provinces')
  await expect(page.getByRole('cell', { name: 'Draft Province' })).toBeVisible()
  await expect(
    page.getByRole('combobox', { name: 'Trạng thái' }).getByRole('option', { name: 'DRAFT' }),
  ).toHaveCount(1)
  await page.getByRole('button', { name: /Tạo/ }).click()
  const createDialog = page.getByRole('dialog', { name: 'Biểu mẫu' })
  await createDialog.getByRole('textbox', { name: 'Mã' }).fill('PV51')
  await createDialog.getByRole('textbox', { name: 'Tên' }).fill('Draft Province')
  await createDialog.getByRole('button', { name: 'Lưu' }).click()
  await expect.poll(() => posted.value).not.toBeNull()
  expect(posted.value).not.toHaveProperty('status')
  const row = page
    .getByRole('row')
    .filter({ has: page.getByRole('cell', { name: 'Draft Province' }) })
  await row.getByRole('button', { name: 'Chuyển trạng thái' }).click()
  const actionDialog = page.getByRole('dialog', { name: 'Chuyển trạng thái' })
  await expect(actionDialog.getByRole('option', { name: 'APPROVED (approve)' })).toHaveCount(1)
  await actionDialog
    .getByRole('combobox', { name: 'Transition được phép' })
    .selectOption({ label: 'APPROVED (approve)' })
  await actionDialog.getByRole('button', { name: 'Lưu' }).click()
  await expect.poll(() => actions).toEqual(['POST'])
})
