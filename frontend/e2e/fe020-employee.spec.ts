import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page, type Route } from '@playwright/test'
import { mockPagination } from './support/mockPagination'
import { mockBusinessWorkflowStates } from './support/mockBusinessWorkflow'

test.beforeEach(async ({ page }) => {
  await mockBusinessWorkflowStates(page)
})

const collection = (route: Route, data: unknown[], total = data.length) =>
  JSON.stringify({ data, meta: mockPagination(route, total, 'fe020-test') })

async function mockBase(page: Page, status = 200): Promise<void> {
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
              'department.view',
              'department.create',
              'department.edit',
              'department.setstate',
              'position.view',
              'position.create',
              'position.edit',
              'position.setstate',
              'employee.view',
              'employee.create',
              'employee.edit',
              'employee.setstate',
              'employee_assignment.view',
              'employee_assignment.create',
              'employee_assignment.edit',
              'employee_assignment.setstate',
              'employee_contact.view',
              'employee_contact.create',
              'employee_contact.edit',
              'employee_contact.setstate',
            ],
          },
        },
      }),
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
  const contacts = [
    {
      id: '61',
      contact_type: 'EMPLOYEE',
      contact_ref_id: '41',
      name: 'Quyết',
      email: 'quyet@gmail.com',
      mobile: '0955757572',
      address: 'Hà Nội',
      province_id: '71',
      ward_id: '81',
      province_name: null,
      ward_name: null,
      status: 'ACTIVE',
    },
  ]
  await page.route('**/api/v1/departments**', (route) =>
    route.fulfill({
      status,
      contentType: 'application/json',
      body:
        status === 200
          ? collection(route, departments)
          : JSON.stringify({ error: { code: 'FORBIDDEN', message: 'Forbidden' } }),
    }),
  )
  await page.route('**/api/v1/positions**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, positions),
    }),
  )
  await page.route('**/api/v1/organizations**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, organizations),
    }),
  )
  await page.route('**/api/v1/employees**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, employees),
    }),
  )
  await page.route('**/api/v1/employee-assignments**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, assignments),
    }),
  )
  await page.route('**/api/v1/contacts**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, contacts),
    }),
  )
  await page.route('**/api/v1/provinces**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, [{ id: '71', code: 'HN', name: 'Hà Nội', status: 'ACTIVE' }]),
    }),
  )
  await page.route('**/api/v1/wards**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: collection(route, [
        {
          id: '81',
          province_id: '71',
          code: 'P-01',
          name: 'Phường Ba Đình',
          name_full: 'Phường Ba Đình, Hà Nội',
          status: 'ACTIVE',
        },
      ]),
    }),
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
  await page.getByRole('tab', { name: 'Liên hệ' }).click()
  await expect(page.getByRole('button', { name: 'Thêm liên hệ' })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Đang hoạt động' })).toBeVisible()
  await page
    .getByRole('region', { name: 'Liên hệ' })
    .getByRole('button', { name: 'Xem chi tiết' })
    .click()
  const contactDetail = page.getByRole('dialog', { name: 'Xem chi tiết - Liên hệ' })
  await expect(contactDetail.locator('dl dd').nth(5)).toHaveText('Hà Nội')
  await expect(contactDetail.locator('dl dd').nth(6)).toHaveText('Phường Ba Đình, Hà Nội')
  await contactDetail.locator('.table-detail-modal__close').click()
  await page.getByRole('tab', { name: 'Audit' }).click()
  const employeeDetail = page.getByRole('dialog', { name: 'Nguyen Van A' })
  await expect(employeeDetail.getByRole('heading', { name: 'Audit' })).toBeVisible()
  await expect(employeeDetail.getByText('Mã nhân viên', { exact: true })).toBeVisible()
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
