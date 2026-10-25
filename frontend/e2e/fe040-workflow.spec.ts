import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { mockPagination } from './support/mockPagination'

const workflowStates = [
  { id: '11', code: 'draft', name: 'Draft', description: null, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
  { id: '12', code: 'approved', name: 'Approved', description: null, status: 'ACTIVE', allowed_actions: { edit: true, setstate: true } },
  { id: '13', code: 'archived', name: 'Archived', description: null, status: 'INACTIVE', allowed_actions: { edit: true, setstate: true } },
]

const lifecycle = {
  id: '21',
  code: 'order',
  name: 'Order lifecycle',
  description: 'Order flow',
  status: 'ACTIVE',
  allowed_actions: { edit: true, setstate: true },
}

async function mockSession(
  page: Page,
  permissions = [
    'workflow_state.view',
    'workflow_state.create',
    'workflow_state.edit',
    'workflow_state.setstate',
    'lifecycle.view',
    'lifecycle.create',
    'lifecycle.edit',
    'lifecycle.setstate',
  ],
): Promise<void> {
  await page.route('**/api/v1/auth/me', async (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          user: {
            id: 7,
            username: 'workflow-admin',
            email: 'workflow@example.test',
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

async function mockWorkflowApi(page: Page): Promise<void> {
  await page.route('**/api/v1/workflow-states*', async (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: workflowStates,
          meta: mockPagination(route, workflowStates.length),
        }),
      })
    }
    return route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        data: { ...workflowStates[0], id: '14', code: 'review', name: 'Review', status: 'ACTIVE' },
      }),
    })
  })
  await page.route('**/api/v1/lifecycles*', async (route) => {
    const url = route.request().url()
    const pathname = new URL(url).pathname
    if (pathname === '/api/v1/lifecycles' && route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [lifecycle], meta: mockPagination(route, 1) }),
      })
    }
    if (pathname === '/api/v1/lifecycles/21/states' && route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '31',
              lifecycle_id: '21',
              state_id: '11',
              sequence: 1,
              is_initial: 1,
              is_final: 0,
            },
          ],
        }),
      })
    }
    if (pathname === '/api/v1/lifecycles/21/transitions' && route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '41',
              lifecycle_id: '21',
              from_state_id: '11',
              to_state_id: '12',
              action_code: 'approve',
              status: 'ACTIVE',
            },
          ],
        }),
      })
    }
    return route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ data: lifecycle }),
    })
  })
  await page.route('**/api/v1/lifecycles/21/states**', async (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '31',
              lifecycle_id: '21',
              state_id: '11',
              sequence: 1,
              is_initial: 1,
              is_final: 0,
            },
          ],
        }),
      })
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: { lifecycle, states: [], transitions: [] } }),
    })
  })
  await page.route('**/api/v1/lifecycles/21/transitions**', async (route) => {
    if (route.request().method() === 'GET') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: '41',
              lifecycle_id: '21',
              from_state_id: '11',
              to_state_id: '12',
              action_code: 'approve',
              status: 'ACTIVE',
            },
          ],
        }),
      })
    }
    return route.fulfill({
      status: 409,
      contentType: 'application/json',
      body: JSON.stringify({ code: 'WF_INVALID_CONFIGURATION', message: 'duplicate transition' }),
    })
  })
}

test.describe('P1-FE-040 Workflow/Lifecycle UI', () => {
  test('workflow state CRUD surface is permission-aware and supports status action', async ({
    page,
  }) => {
    await mockSession(page)
    await mockWorkflowApi(page)
    await page.goto('/configuration/workflow-states')
    await expect(page.getByRole('heading', { name: 'Workflow State' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'draft', exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: /Tạo|Create/ })).toBeVisible()
    await page.getByRole('button', { name: /Tạo|Create/ }).click()
    await expect(page.getByRole('dialog', { name: /Workflow State/ })).toBeVisible()
    await expect(page.getByRole('dialog').getByRole('button', { name: /Lưu|Save/ })).toBeEnabled()
  })

  test('lifecycle tabs constrain state membership and surface duplicate transition conflict', async ({
    page,
  }) => {
    await mockSession(page)
    await mockWorkflowApi(page)
    await page.goto('/configuration/lifecycles')
    await expect(page.getByRole('heading', { name: 'Lifecycles' })).toBeVisible()
    await page.getByRole('button', { name: /Xem chi tiết|View details/ }).click()
    await expect(page.getByRole('tab', { name: /States/ })).toBeVisible()
    await page.getByRole('tab', { name: /States/ }).click()
    await expect(page.getByRole('cell', { name: /Draft/ })).toBeVisible()
    await page.getByRole('tab', { name: /Transitions/ }).click()
    await expect(page.getByRole('cell', { name: 'approve', exact: true })).toBeVisible()
    await page.getByRole('button', { name: /Thêm Transition|Add Transition/ }).click()
    const transitionDialog = page.getByRole('dialog', { name: /Transition/ })
    await transitionDialog.getByRole('combobox', { name: 'From State' }).selectOption('11')
    await transitionDialog.getByRole('textbox', { name: 'Action' }).fill('approve')
    await expect(transitionDialog.getByText(/From State \+ Action/)).toBeVisible()
    await expect(transitionDialog.getByRole('button', { name: /Lưu|Save/ })).toBeDisabled()
  })

  test('State membership modal retains active unassigned State validation and submit', async ({
    page,
  }) => {
    await mockSession(page)
    await mockWorkflowApi(page)
    let submittedState: unknown = null
    page.on('request', (request) => {
      if (
        request.method() === 'PUT' &&
        new URL(request.url()).pathname === '/api/v1/lifecycles/21/states'
      ) {
        submittedState = request.postDataJSON()
      }
    })
    await page.goto('/configuration/lifecycles')
    await expect(page.getByRole('heading', { name: 'Lifecycles' })).toBeVisible()
    await page.getByRole('button', { name: /View details|Xem chi ti.t/ }).click()
    await page.getByRole('tab', { name: 'States' }).click()
    await page.getByRole('button', { name: /Add State|Th.m State/ }).click()

    const stateDialog = page.getByRole('dialog', { name: /State.*Lifecycle|Lifecycle State/ })
    await expect(stateDialog).toBeVisible()
    await expect(stateDialog.locator('.modal-card__header')).toHaveCount(1)
    await expect(stateDialog.locator('.modal-card__body')).toHaveCount(1)
    await expect(stateDialog.locator('.modal-card__footer')).toHaveCount(1)
    const stateSelect = stateDialog.getByRole('combobox', { name: 'Workflow State', exact: true })
    await expect(stateSelect).toBeVisible()
    await expect(stateSelect.locator('option[value="11"]')).toHaveCount(0)
    await expect(stateSelect.locator('option[value="12"]')).toHaveCount(1)
    await expect(stateSelect.locator('option[value="13"]')).toHaveCount(0)
    await expect(stateDialog.getByRole('checkbox', { name: /^Initial/ })).toBeDisabled()
    const saveButton = stateDialog.locator('button[type="submit"]')
    await expect(saveButton).toBeDisabled()
    await stateSelect.selectOption('12')
    await expect(saveButton).toBeEnabled()
    await saveButton.click()
    await expect(stateDialog).toHaveCount(0)
    await expect.poll(() => submittedState).toMatchObject({
      states: [{ state_id: '12', sequence: 2, is_initial: false, is_final: false }],
    })
  })

  test('missing base permissions hide configuration navigation and actions', async ({ page }) => {
    await mockSession(page, [])
    await mockWorkflowApi(page)
    await page.goto('/configuration/workflow-states')
    await expect(page.getByRole('button', { name: /Tạo|Create/ })).toHaveCount(0)
    await page.goto('/configuration/lifecycles')
    await expect(page.getByRole('button', { name: /Tạo|Create/ })).toHaveCount(0)
  })

  test('lifecycle detail has no critical accessibility violations', async ({ page }) => {
    await mockSession(page)
    await mockWorkflowApi(page)
    await page.goto('/configuration/lifecycles')
    await expect(page.getByRole('heading', { name: 'Lifecycles' })).toBeVisible()
    const result = await new AxeBuilder({ page }).include('.workflow-panel').analyze()
    expect(result.violations).toEqual([])
  })
})
