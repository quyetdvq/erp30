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
          },
        },
      }),
    }),
  )
}

test.describe('P1-FE-030 security surfaces', () => {
  test.beforeEach(async ({ page }) => {
    await mockSession(page)
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
    await page.route('**/api/v1/resources*', async (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [{ id: '2', type: 'policy', name: 'Policy', domain: 'ORGANIZATION' }],
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
          data: [],
          meta: { page: 1, page_size: 200, total: 0, request_id: 'test-state' },
        }),
      }),
    )
  })

  test('users is read-only and does not offer invented Shield writes', async ({ page }) => {
    await page.goto('/security/users')
    await expect(page.getByRole('heading', { name: 'Người dùng' })).toBeVisible()
    await expect(page.getByText('Shield User create/edit/state')).toBeVisible()
    await expect(page.getByRole('button', { name: /Tạo|Create|Sửa|Edit/ })).toHaveCount(0)
  })

  test('resources expose canonical Domain values and policies expose exact PBAC vocabularies', async ({
    page,
  }) => {
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
    await expect(page.locator('option[value="ROLE"]')).toHaveCount(0)
    await page.getByRole('button', { name: /Thêm Action|Add Action/ }).click()
    await expect(page.locator('option[value="ABSOLUTE_DENY"]')).toHaveCount(1)
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
