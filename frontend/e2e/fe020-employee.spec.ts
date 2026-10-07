import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const collection = (data: unknown[], total = data.length) =>
  JSON.stringify({ data, meta: { page: 1, page_size: 50, total, request_id: 'fe020-test' } })

async function mockBase(page: Page, status = 200): Promise<void> {
  await page.route('**/api/v1/auth/me', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { user: { id: 7, username: 'admin', groups: ['orgadmin'] } } }),
    }),
  )
  const departments = [
    { id: '11', code: 'D-01', name: 'Operations', parent_id: null, status: 'ACTIVE' },
    { id: '12', code: 'D-02', name: 'Field team', parent_id: '11', status: 'INACTIVE' },
  ]
  const positions = [{ id: '21', code: 'P-01', name: 'Manager', level: 2, status: 'ACTIVE' }]
  const organizations = [
    { id: '31', code: 'C-01', name: 'Main Company', type: 'COMPANY', status: 'ACTIVE' },
  ]
  const employees = [
    {
      id: '41',
      employee_code: 'E-01',
      employee_name: 'Nguyen Van A',
      gender: null,
      status: 'ACTIVE',
    },
    {
      id: '42',
      employee_code: 'E-02',
      employee_name: 'Tran Thi B',
      gender: 'FEMALE',
      status: 'ACTIVE',
    },
  ]
  const assignments = [
    {
      id: '51',
      employee_id: '41',
      organization_id: '31',
      department_id: '11',
      position_id: '21',
      is_primary: true,
      effective_from: '2026-01-01',
      effective_to: null,
      status: 'ACTIVE',
    },
    {
      id: '52',
      employee_id: '41',
      organization_id: '31',
      department_id: '11',
      position_id: null,
      is_primary: false,
      effective_from: '2026-03-01',
      effective_to: null,
      status: 'ACTIVE',
    },
  ]
  await page.route('**/api/v1/departments**', (route) =>
    route.fulfill({
      status,
      contentType: 'application/json',
      body:
        status === 200
          ? collection(departments)
          : JSON.stringify({ error: { code: 'FORBIDDEN', message: 'Forbidden' } }),
    }),
  )
  await page.route('**/api/v1/positions**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: collection(positions) }),
  )
  await page.route('**/api/v1/organizations**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(organizations),
    }),
  )
  await page.route('**/api/v1/employees**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: collection(employees) }),
  )
  await page.route('**/api/v1/employee-assignments**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: collection(assignments) }),
  )
  await page.route('**/api/v1/contacts**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: collection([]) }),
  )
  await page.route('**/api/v1/provinces**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: collection([]) }),
  )
  await page.route('**/api/v1/wards**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: collection([]) }),
  )
}

test('Employee structure routes expose hierarchy, canonical gender, tabs, and assignments', async ({
  page,
}) => {
  await mockBase(page)
  await page.goto('/people/departments')
  await expect(page.getByRole('cell', { name: 'Operations' }).first()).toBeVisible()
  await page.getByRole('button', { name: /Tạo/ }).click()
  await expect(page.getByRole('dialog', { name: 'Biểu mẫu phòng ban' })).toBeVisible()
  await expect(page.getByRole('dialog', { name: 'Biểu mẫu phòng ban' })).not.toContainText(
    'Organization owner',
  )
  await page.getByRole('button', { name: 'Đóng' }).click()

  await page.goto('/people/positions')
  await expect(page.getByRole('cell', { name: 'Manager' }).first()).toBeVisible()

  await page.goto('/people/employees')
  await expect(page.getByRole('cell', { name: 'Nguyen Van A' }).first()).toBeVisible()
  await page.getByRole('button', { name: 'Xem chi tiết' }).first().click()
  await expect(page.getByRole('dialog', { name: 'Nguyen Van A' })).toBeVisible()
  await page.getByRole('tab', { name: 'Assignments' }).click()
  await expect(page.getByRole('cell', { name: 'Primary', exact: true })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'Secondary', exact: true })).toBeVisible()
  await page.getByRole('tab', { name: 'Tổng quan' }).click()
  await expect(page.getByText('Chưa khai báo / Not specified', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Sửa' }).last().click()
  const gender = page.getByRole('dialog', { name: 'Biểu mẫu nhân viên' }).locator('select').first()
  await expect(gender.locator('option')).toHaveText([
    'Chưa khai báo / Not specified',
    'Nam / Male',
    'Nữ / Female',
    'Khác / Other',
  ])
  await page
    .getByRole('dialog', { name: 'Biểu mẫu nhân viên' })
    .getByRole('button', { name: 'Đóng' })
    .click()
  const accessibility = await new AxeBuilder({ page }).include('.employee-detail-modal').analyze()
  expect(accessibility.violations).toEqual([])
})

test('Employee structure keeps 403 list errors visible', async ({ page }) => {
  await mockBase(page, 403)
  await page.goto('/people/departments')
  await expect(page.locator('.list-shell__state[role="alert"]')).toBeVisible()
})
