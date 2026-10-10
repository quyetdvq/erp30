import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { mockPagination } from './support/mockPagination'

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
              'user.setstate',
              'user.assign_permission',
              'user.reset_password',
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
              'lifecycle.view',
              'lifecycle.edit',
              'lifecycle.setstate',
              'workflow_state.edit',
              'workflow_state.setstate',
            ],
          },
        },
      }),
    }),
  )
}

test.describe('P1-FE-030 security surfaces', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/api/v1/auth/csrf', async (route) =>
      route.fulfill({
        status: 200,
        headers: { 'X-CSRF-TOKEN': 'csrf-fe030' },
        contentType: 'application/json',
        body: JSON.stringify({
          data: { csrf_token: 'csrf-fe030', header_name: 'X-CSRF-TOKEN' },
          meta: {},
        }),
      }),
    )
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
          meta: mockPagination(route, 2, 'test-security-users'),
        }),
      }),
    )
    await page.route('**/api/v1/users/7/permissions', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { permissions: [] } }),
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
          meta: mockPagination(route, 1, 'test-group'),
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
    await page.route('**/api/v1/resources*', async (route) => {
      const includeActions =
        new URL(route.request().url()).searchParams.get('include') === 'actions'
      await route.fulfill({
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
              ...(includeActions
                ? { actions: [{ id: '20', code: 'view', description: null }] }
                : {}),
            },
          ],
          meta: mockPagination(route, 1, 'test-resource'),
        }),
      })
    })
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
    // Mock the complete Lifecycle detail dependency graph. Leaving transitions
    // unmocked reaches the real API, whose 404 marks the permission snapshot stale
    // and removes TableRowActions buttons while Playwright is clicking them.
    await page.route('**/api/v1/lifecycles/50/transitions', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: { request_id: 'test-lifecycle-transition' },
        }),
      }),
    )
    await page.route(
      (url) => url.pathname === '/api/v1/lifecycles',
      async (route) =>
        route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            data: [
              {
                id: '50',
                code: 'PRODUCT_FLOW',
                name: 'Product flow',
                description: 'Product lifecycle',
                status: 'ACTIVE',
              },
            ],
            meta: { page: 1, page_size: 25, total: 1, request_id: 'test-lifecycle' },
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

  test('users exposes permission-aware mutation controls and canonical detail tabs', async ({
    page,
  }) => {
    let memberRequests = 0
    let securityUserRequests = 0
    let resourceActionRequests = 0
    let resourceActionCatalogRequests = 0
    let resourceWithActionsRequests = 0
    let permissionPutRequests = 0
    page.on('request', (request) => {
      if (request.url().includes('/api/v1/authorization-groups/1/members')) memberRequests += 1
      if (request.url().includes('/api/v1/security/users')) securityUserRequests += 1
      if (/\/api\/v1\/resources\/[^/]+\/actions$/.test(new URL(request.url()).pathname))
        resourceActionRequests += 1
      const requestUrl = new URL(request.url())
      if (requestUrl.pathname.endsWith('/api/v1/resource-actions'))
        resourceActionCatalogRequests += 1
      if (
        requestUrl.pathname.endsWith('/api/v1/resources') &&
        requestUrl.searchParams.get('include') === 'actions'
      )
        resourceWithActionsRequests += 1
      if (request.method() === 'PUT' && requestUrl.pathname.endsWith('/api/v1/users/7/permissions'))
        permissionPutRequests += 1
    })
    await page.goto('/security/users')
    await expect(page.getByRole('heading', { name: 'Người dùng' })).toBeVisible()
    const userQuery = page.getByRole('search')
    await expect(userQuery.getByRole('searchbox', { name: 'Tìm kiếm' })).toBeVisible()
    await expect(userQuery.getByLabel('Trạng thái', { exact: true })).toBeVisible()
    await expect(userQuery.getByLabel('Sắp xếp', { exact: true })).toBeVisible()
    await expect(userQuery.getByLabel('Số dòng mỗi trang', { exact: true })).toBeVisible()
    await expect(page.getByRole('navigation', { name: 'Phân trang danh sách' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'quyetdv', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'Quyet Do Van', exact: true })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'linhpt', exact: true })).toBeVisible()
    await expect(
      page.getByRole('table', { name: 'Danh sách dữ liệu' }).getByRole('row'),
    ).toHaveCount(3)
    const headers = await page
      .getByRole('table', { name: 'Danh sách dữ liệu' })
      .getByRole('columnheader')
      .allTextContents()
    expect(headers.map((header) => header.trim()).slice(-2)).toEqual(['Trạng thái', 'Thao tác'])
    const tableAccessibility = await new AxeBuilder({ page })
      .include('.security-list-panel')
      .analyze()
    expect(tableAccessibility.violations).toEqual([])
    const createButton = page.getByRole('button', { name: /Tạo|Create/ })
    await expect(createButton).toHaveCount(1)
    await createButton.click()
    const createDialog = page.getByRole('dialog', { name: 'Biểu mẫu người dùng' })
    await expect(createDialog).toBeVisible()
    await expect(createDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(createDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(createDialog.locator('.modal-card__footer')).toHaveCount(1)
    await page.getByRole('button', { name: 'Đóng' }).click()
    const firstUserRow = page.getByRole('row', { name: /7 quyetdv/ })
    await expect(firstUserRow.getByRole('button', { name: 'Sửa' })).toBeVisible()
    await expect(firstUserRow.getByRole('button', { name: 'Vô hiệu hóa' })).toBeVisible()
    await page
      .getByRole('row', { name: /7 quyetdv/ })
      .getByRole('button', { name: 'Xem chi tiết' })
      .click()
    const detailDialog = page.getByRole('dialog', { name: /Chi tiết người dùng/ })
    await expect(detailDialog).toBeVisible()
    await expect(detailDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(detailDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(detailDialog.locator('.modal-card__footer')).toHaveCount(1)
    await expect(detailDialog.getByText('quyetdv@example.test')).toBeVisible()
    expect(resourceActionRequests).toBe(0)
    expect(resourceActionCatalogRequests).toBe(0)
    await detailDialog.getByRole('button', { name: 'Direct Permissions' }).click()
    await expect.poll(() => resourceWithActionsRequests).toBe(1)
    expect(resourceActionCatalogRequests).toBe(0)
    expect(resourceActionRequests).toBe(0)
    await detailDialog.locator('details').first().locator('summary').click()
    await detailDialog.getByRole('checkbox').first().check()
    await detailDialog.getByRole('button', { name: 'Lưu quyền' }).click()
    await expect.poll(() => permissionPutRequests).toBe(1)
    await detailDialog.getByRole('button', { name: 'Tổng quan' }).click()
    await detailDialog.getByRole('button', { name: 'Direct Permissions' }).click()
    expect(resourceWithActionsRequests).toBe(1)
    expect(resourceActionCatalogRequests).toBe(0)
    expect(resourceActionRequests).toBe(0)
    const detailAccessibility = await new AxeBuilder({ page }).include('.modal-card').analyze()
    expect(detailAccessibility.violations).toEqual([])
    expect(memberRequests).toBeGreaterThan(0)
    expect(securityUserRequests).toBe(2)
  })

  test('authorization groups show all members and resource/action permissions in a modal', async ({
    page,
  }) => {
    let resourceActionRequests = 0
    let resourceWithActionsRequests = 0
    let resourceWithActionsUrl = ''
    page.on('request', (request) => {
      const requestUrl = new URL(request.url())
      if (/\/api\/v1\/resources\/[^/]+\/actions$/.test(requestUrl.pathname))
        resourceActionRequests += 1
      if (
        requestUrl.pathname.endsWith('/api/v1/resources') &&
        requestUrl.searchParams.get('include') === 'actions'
      ) {
        resourceWithActionsRequests += 1
        resourceWithActionsUrl = requestUrl.toString()
      }
    })
    await page.goto('/security/authorization-groups')
    await expect(page.getByRole('heading', { name: 'Nhóm phân quyền' })).toBeVisible()
    await expect(page.getByRole('navigation', { name: 'Phân trang danh sách' })).toHaveCount(1)
    await page.getByRole('button', { name: 'Xem chi tiết' }).click()
    const dialog = page.getByRole('dialog', { name: /Nhóm phân quyền/ })
    await expect(dialog).toBeVisible()
    await expect(dialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(dialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(dialog.locator('.modal-card__footer')).toHaveCount(1)
    await dialog.getByRole('button', { name: 'Members' }).click()
    await expect(dialog.getByRole('cell', { name: 'quyetdv' })).toBeVisible()
    await expect(dialog.getByRole('cell', { name: 'linhpt' })).toBeVisible()
    await expect(dialog.getByText('linhpt (#8)')).toBeVisible()
    await dialog.getByRole('button', { name: 'Permissions' }).click()
    await expect(dialog.getByRole('cell', { name: 'Policy' })).toBeVisible()
    await expect(dialog.getByRole('cell', { name: 'view' })).toBeVisible()
    await expect(dialog.getByText('ALLOW')).toBeVisible()
    expect(resourceWithActionsRequests).toBe(1)
    expect(resourceActionRequests).toBe(0)
    expect(new URL(resourceWithActionsUrl).searchParams.get('page')).toBe('1')
    expect(new URL(resourceWithActionsUrl).searchParams.get('page_size')).toBe('200')
    expect(new URL(resourceWithActionsUrl).searchParams.get('sort')).toBe('type')
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
    await expect(page.getByRole('cell', { name: 'Product flow' })).toBeVisible()
    await expect(page.getByRole('navigation', { name: 'Phân trang danh sách' })).toHaveCount(1)
    await page.getByRole('button', { name: 'Xem chi tiết' }).click()
    const resourceDialog = page.getByRole('dialog', { name: /Resource:/ })
    await expect(resourceDialog).toBeVisible()
    await expect(resourceDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(resourceDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(resourceDialog.locator('.modal-card__footer')).toHaveCount(1)
    await resourceDialog
      .locator('.modal-card__footer')
      .getByRole('button', { name: 'Đóng' })
      .click()
    await page.getByRole('button', { name: 'Sửa' }).click()
    const resourceEditDialog = page.getByRole('dialog', { name: 'Biểu mẫu Resource' })
    await expect(resourceEditDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(resourceEditDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(resourceEditDialog.locator('.modal-card__footer')).toHaveCount(1)
    const lifecycleSelect = resourceEditDialog
      .locator('select')
      .filter({ has: page.locator('option[value="50"]') })
    await expect(lifecycleSelect).toHaveValue('50')
    await resourceEditDialog
      .locator('.modal-card__footer')
      .getByRole('button', { name: 'Hủy' })
      .click()
    await page.getByRole('button', { name: /Tạo|Create/ }).click()
    const domain = page
      .locator('select')
      .filter({ has: page.locator('option[value="SYSTEM"]') })
      .first()
    await expect(domain.locator('option')).toHaveCount(3)
    await expect(domain.locator('option[value="COMPANY"]')).toHaveCount(0)

    await page.goto('/security/policies')
    await expect(page.getByRole('heading', { name: 'Policy PBAC' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'Security administrators' })).toBeVisible()
    await expect(page.getByText('ABSOLUTE_DENY:view')).toBeVisible()
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

  test('workflow management screens use one table-first pagination and modal detail surfaces', async ({
    page,
  }) => {
    const transitionsResponse = page.waitForResponse(
      (response) => new URL(response.url()).pathname === '/api/v1/lifecycles/50/transitions',
    )
    await page.goto('/configuration/lifecycles')
    expect((await transitionsResponse).status()).toBe(200)
    await expect(page.getByRole('heading', { name: /Lifecycle/i })).toBeVisible()
    await expect(page.getByRole('navigation', { name: 'Phân trang danh sách' })).toHaveCount(1)
    await page.getByRole('button', { name: 'Xem chi tiết' }).click()
    const lifecycleDialog = page.getByRole('dialog', { name: /Product flow/ })
    await expect(lifecycleDialog).toBeVisible()
    await expect(lifecycleDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(lifecycleDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(lifecycleDialog.locator('.modal-card__footer')).toHaveCount(1)
    await lifecycleDialog
      .locator('.modal-card__footer')
      .getByRole('button', { name: 'Đóng' })
      .click()

    // Wait for the actual Workflow State list, not just the pagination container.
    const workflowStatesResponse = page.waitForResponse(
      (response) =>
        response.request().method() === 'GET' &&
        new URL(response.url()).pathname === '/api/v1/workflow-states',
    )
    await page.goto('/configuration/workflow-states')
    expect((await workflowStatesResponse).status()).toBe(200)
    await expect(page.getByRole('heading', { name: /Workflow State/i })).toBeVisible()
    await expect(page.getByRole('navigation', { name: 'Phân trang danh sách' })).toHaveCount(1)

    const draftRow = page
      .getByRole('table', { name: 'Danh sách dữ liệu' })
      .getByRole('row')
      .filter({
        has: page.getByRole('cell', { name: 'DRAFT', exact: true }),
      })
    await expect(draftRow).toBeVisible()
    const editDraft = draftRow.getByRole('button', { name: 'Sửa', exact: true })
    await expect(editDraft).toBeEnabled()
    await editDraft.click()
    const stateDialog = page.getByRole('dialog', { name: /Trạng thái quy trình|Workflow State/i })
    await expect(stateDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(stateDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(stateDialog.locator('.modal-card__footer')).toHaveCount(1)
  })
})
