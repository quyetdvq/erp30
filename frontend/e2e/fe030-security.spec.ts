import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

async function mockSession(page: Page): Promise<void> {
  await page.route('**/api/v1/auth/me', async (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'quyetdv',
            email: 'quyetdv@example.test',
            active: true,
            status: 'ACTIVE',
            groups: ['orgadmin'],
            base_permissions: [
              'user.view',
              'user.create',
              'user.edit',
              'authorization_group.view',
              'authorization_group.create',
              'authorization_group.edit',
              'authorization_group.setstate',
              'authorization_group.assign_member',
              'authorization_group.assign_permission',
              'resource.view',
              'resource.create',
              'resource.edit',
              'resource.delete',
              'policy.view',
              'policy.create',
              'policy.edit',
              'authorization_log.view',
            ],
          },
        },
      }),
    }),
  )
}

test.describe('P1-FE-030 security surfaces', () => {
  test.beforeEach(async ({ page }) => {
    await mockSession(page)
    await page.route('**/api/v1/security/users*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '7',
              username: 'quyetdv',
              name: 'Quyet Do Van',
              email: 'quyetdv@example.test',
              active: true,
              status: 'ACTIVE',
              created_at: null,
              groups: [
                {
                  id: '1',
                  code: 'SECURITY_ADMIN',
                  name: 'Security administrators',
                  status: 'ACTIVE',
                },
              ],
              direct_permission_count: 0,
            },
            {
              id: '8',
              username: 'linhpt',
              name: 'Linh Pham Thi',
              email: 'linhpt@example.test',
              active: true,
              status: 'ACTIVE',
              created_at: null,
              groups: [],
              direct_permission_count: 0,
            },
          ],
          meta: { page: 1, page_size: 25, total: 2, request_id: 'test-security-users' },
        }),
      }),
    )
    await page.route('**/api/v1/authorization-groups*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { id: '1', code: 'SECURITY_ADMIN', name: 'Security administrators', status: 'ACTIVE' },
          ],
          meta: { page: 1, page_size: 25, total: 1, request_id: 'test-group' },
        }),
      }),
    )
    await page.route('**/api/v1/authorization-groups/1/members', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { id: '7', username: 'quyetdv', active: true, status: 'ACTIVE' },
            { id: '8', username: 'linhpt', active: true, status: 'ACTIVE' },
          ],
          meta: { request_id: 'test-members' },
        }),
      }),
    )
    await page.route('**/api/v1/authorization-groups/1/permissions', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [{ resource_action_id: '20' }],
          meta: { request_id: 'test-group-permissions' },
        }),
      }),
    )
    await page.route('**/api/v1/resources*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '2',
              type: 'policy',
              name: 'Policy',
              domain: 'ORGANIZATION',
              lifecycle_id: '50',
            },
          ],
          meta: { page: 1, page_size: 25, total: 1, request_id: 'test-resource' },
        }),
      }),
    )
    await page.route('**/api/v1/resources/2/actions', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [{ id: '20', resource_id: '2', code: 'view', description: null }],
          meta: { request_id: 'test-actions' },
        }),
      }),
    )
    await page.route('**/api/v1/lifecycles/50/states', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '501',
              lifecycle_id: '50',
              state_id: '31',
              sequence: 1,
              is_initial: true,
              is_final: false,
            },
          ],
          meta: { request_id: 'test-lifecycle-state' },
        }),
      }),
    )
    await page.route('**/api/v1/policies*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '3',
              organization_id: '10',
              resource_id: '2',
              participant_type: 'GROUP',
              participant_ref_id: '1',
              actions: [{ resource_action_id: '20', effect: 'ABSOLUTE_DENY' }],
            },
          ],
          meta: { page: 1, page_size: 25, total: 1, request_id: 'test-policy' },
        }),
      }),
    )
    await page.route('**/api/v1/authorization-logs*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '4',
              user_id: '7',
              organization_id: '10',
              resource_id: '2',
              action: 'view',
              decision: 'DENY',
              reason: 'policy',
              request_id: 'req-fe030',
              created_at: '2026-10-07T01:00:00Z',
            },
          ],
          meta: { page: 1, page_size: 25, total: 1, request_id: 'test-log' },
        }),
      }),
    )
    await page.route('**/api/v1/organizations*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '10',
              code: 'ROOT',
              name: 'Root',
              parent_id: null,
              type: 'GROUP',
              status: 'ACTIVE',
            },
          ],
          meta: { page: 1, page_size: 200, total: 1, request_id: 'test-org' },
        }),
      }),
    )
    await page.route('**/api/v1/departments*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { page: 1, page_size: 200, total: 0, request_id: 'test-dept' },
        }),
      }),
    )
    await page.route('**/api/v1/positions*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { page: 1, page_size: 200, total: 0, request_id: 'test-position' },
        }),
      }),
    )
    await page.route('**/api/v1/workflow-states*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            { id: '31', code: 'DRAFT', name: 'Draft', status: 'ACTIVE' },
            { id: '32', code: 'APPROVED', name: 'Approved', status: 'ACTIVE' },
          ],
          meta: { page: 1, page_size: 200, total: 2, request_id: 'test-state' },
        }),
      }),
    )
  })

  test('users is a complete read-only table with modal details and one aggregate request', async ({
    page,
  }) => {
    let memberRequests = 0
    let securityUserRequests = 0
    page.on('request', (request) => {
      if (request.url().includes('/api/v1/authorization-groups/1/members')) memberRequests += 1
      if (request.url().includes('/api/v1/security/users')) securityUserRequests += 1
    })
    await page.goto('/security/users')
    await expect(page.getByRole('heading', { name: 'Người dùng' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'quyetdv', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'Quyet Do Van', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'linhpt', exact: true })).toBeVisible()
    await expect(
      page.getByRole('table', { name: 'Danh sách dữ liệu' }).getByRole('row'),
    ).toHaveCount(3)
    await expect(page.getByRole('button', { name: /Tạo|Create|Sửa|Edit/ })).toHaveCount(0)
    await page
      .getByRole('row', { name: /7 quyetdv/ })
      .getByRole('button', { name: 'Xem chi tiết' })
      .click()
    await expect(page.getByRole('dialog', { name: /Chi tiết người dùng/ })).toBeVisible()
    await expect(
      page.getByRole('dialog', { name: /Chi tiết người dùng/ }).getByText('quyetdv@example.test'),
    ).toBeVisible()
    expect(memberRequests).toBe(0)
    expect(securityUserRequests).toBe(1)
  })

  test('authorization groups show all members and resource/action permissions in a modal', async ({
    page,
  }) => {
    await page.goto('/security/authorization-groups')
    await expect(page.getByRole('heading', { name: 'Nhóm phân quyền' })).toBeVisible()
    await page.getByRole('button', { name: 'Xem chi tiết' }).click()
    const dialog = page.getByRole('dialog', { name: /Nhóm phân quyền/ })
    await expect(dialog).toBeVisible()
    await dialog.getByRole('button', { name: 'Members' }).click()
    await expect(dialog.getByRole('cell', { name: 'quyetdv' })).toBeVisible()
    await expect(dialog.getByRole('cell', { name: 'linhpt' })).toBeVisible()
    await expect(dialog.getByText('linhpt (#8)')).toBeVisible()
    await dialog.getByRole('button', { name: 'Permissions' }).click()
    await expect(dialog.getByRole('cell', { name: 'Policy' })).toBeVisible()
    await expect(dialog.getByRole('cell', { name: 'view' })).toBeVisible()
    await expect(dialog.getByText('ALLOW')).toBeVisible()
  })

  test('resources expose canonical Domain values and policies expose exact PBAC vocabularies', async ({
    page,
  }) => {
    let policyUserRequests = 0
    page.on('request', (request) => {
      if (request.url().includes('/api/v1/security/users')) policyUserRequests += 1
    })
    await page.goto('/security/resources')
    await expect(page.getByRole('heading', { name: 'Resource và Action' })).toBeVisible()
    await page.getByRole('button', { name: /Tạo|Create/ }).click()
    const domain = page
      .locator('select')
      .filter({ has: page.locator('option[value="SYSTEM"]') })
      .first()
    await expect(domain.locator('option')).toHaveCount(3)
    await expect(domain.locator('option[value="COMPANY"]')).toHaveCount(0)

    await page.goto('/security/policies')
    await expect(page.getByRole('heading', { name: 'Policy PBAC' })).toBeVisible()
    await page.getByRole('button', { name: /Tạo|Create/ }).click()
    await expect(page.locator('#policy-organization-scope')).toBeVisible()
    await expect(
      page.locator('select').filter({ has: page.locator('option[value="31"]') }),
    ).toBeVisible()
    await expect(page.locator('option[value="32"]')).toHaveCount(0)
    await expect.poll(() => policyUserRequests).toBe(1)
    await expect(page.locator('option[value="ROLE"]')).toHaveCount(0)
    await page.getByRole('button', { name: /Thêm Action|Add Action/ }).click()
    await expect(page.locator('option[value="ABSOLUTE_DENY"]')).toHaveCount(1)
    await expect(page.getByRole('button', { name: /Xóa|Delete/ })).toHaveCount(1)
    const accessibility = await new AxeBuilder({ page }).include('.modal-card').analyze()
    expect(accessibility.violations).toEqual([])
  })

  test('authorization logs remain read-only and support Request-ID copy control', async ({
    page,
  }) => {
    await page.goto('/security/authorization-logs')
    await expect(page.getByRole('heading', { name: 'Nhật ký phân quyền' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Sao chép Request ID' })).toBeVisible()
    await expect(page.getByRole('button', { name: /Tạo|Create|Sửa|Edit|Xóa|Delete/ })).toHaveCount(
      0,
    )
    const accessibility = await new AxeBuilder({ page }).include('.security-list-panel').analyze()
    expect(accessibility.violations).toEqual([])
  })
})
