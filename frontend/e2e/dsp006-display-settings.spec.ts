import { expect, test, type Page, type Route } from '@playwright/test'

const definitions = [
  ['Display.timezone', 'DATE_TIME', 'string', 'Asia/Ho_Chi_Minh'],
  ['Display.dateFormat', 'DATE_TIME', 'string', 'DD/MM/YYYY'],
  ['Display.dateTimeFormat', 'DATE_TIME', 'string', 'DD/MM/YYYY HH:mm'],
  ['Display.timeFormat', 'DATE_TIME', 'string', 'HH:mm'],
  ['Display.firstDayOfWeek', 'DATE_TIME', 'string', 'MONDAY'],
  ['Display.number.decimalSeparator', 'NUMBER', 'string', '.'],
  ['Display.number.groupSeparator', 'NUMBER', 'string', ','],
  ['Display.quantityDecimals', 'NUMBER', 'integer', 3],
  ['Display.percentageDecimals', 'NUMBER', 'integer', 3],
  ['Display.trailingZeros', 'NUMBER', 'boolean', false],
  ['Display.money.decimalSeparator', 'MONEY', 'string', '.'],
  ['Display.money.groupSeparator', 'MONEY', 'string', ','],
  ['Display.money.symbolMode', 'MONEY', 'string', 'SYMBOL'],
  ['Display.money.symbolPosition', 'MONEY', 'string', 'AUTO'],
  ['Display.money.decimalsMode', 'MONEY', 'string', 'CURRENCY_DEFAULT'],
] as const

function valueRows(overrides: Record<string, unknown> = {}) {
  return definitions.map(([key], index) => ({
    key,
    override_value: overrides[key] ?? null,
    effective_value: overrides[key] ?? definitions[index][3],
    source: {
      type: overrides[key] === undefined ? 'SYSTEM' : 'ORGANIZATION',
      organization_id: overrides[key] === undefined ? null : 11,
    },
    inherited: overrides[key] === undefined,
  }))
}

async function mockSession(page: Page, groups = ['orgadmin']): Promise<void> {
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
            email: 'a@test',
            groups,
            base_permissions: ['display_setting.view', 'display_setting.edit'],
          },
        },
      }),
    }),
  )
}

test('system mode exposes Display Settings without a frontend group guess', async ({ page }) => {
  await mockSession(page, [])
  await page.goto('/system/display-settings')
  await page.getByRole('button', { name: 'Hệ thống' }).dispatchEvent('click')
  await expect(page.getByRole('menuitem', { name: 'Cấu hình hiển thị' })).toBeVisible()
})

async function mockAdminApi(page: Page): Promise<{ calls: string[] }> {
  const calls: string[] = []
  const currentOverrides: Record<string, unknown> = {}
  const displayRoute = async (route: Route) => {
    calls.push(`${route.request().method()} ${route.request().url()}`)
    const url = new URL(route.request().url())
    if (url.pathname.endsWith('/definitions')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: definitions.map(([key, category, data_type, value]) => ({
            key,
            category,
            data_type,
            default: value,
            user_overridable: true,
          })),
        }),
      })
    }
    if (url.pathname.endsWith('/values')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: valueRows(currentOverrides) }),
      })
    }
    if (url.pathname.endsWith('/runtime')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            timezone: 'Asia/Ho_Chi_Minh',
            date: {
              format: 'DD/MM/YYYY',
              date_time_format: 'DD/MM/YYYY HH:mm',
              time_format: 'HH:mm',
              first_day_of_week: 'MONDAY',
            },
            number: {
              decimal_separator: '.',
              group_separator: ',',
              quantity_decimals: 3,
              percentage_decimals: 3,
              trailing_zeros: false,
            },
            money: {
              decimal_separator: '.',
              group_separator: ',',
              symbol_mode: 'SYMBOL',
              symbol_position: 'AUTO',
              decimals_mode: 'CURRENCY_DEFAULT',
            },
          },
        }),
      })
    }
    if (route.request().method() === 'PATCH') {
      currentOverrides['Display.quantityDecimals'] = 4
    }
    if (route.request().method() === 'POST') {
      delete currentOverrides['Display.quantityDecimals']
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: valueRows(currentOverrides) }),
    })
  }
  await page.route('**/api/v1/display-settings/**', displayRoute)
  await page.route('**/api/v1/display-settings', displayRoute)
  await page.route('**/api/v1/organizations/tree**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            id: '1',
            code: 'ROOT',
            name: 'Dinktech Group',
            type: 'GROUP',
            status: 'ACTIVE',
          },
          {
            id: '11',
            code: 'ORG-11',
            name: 'North Organization',
            type: 'COMPANY',
            status: 'ACTIVE',
            parent_id: '1',
          },
          {
            id: '12',
            code: 'BR-12',
            name: 'North Branch',
            type: 'BRANCH',
            status: 'ACTIVE',
            parent_id: '11',
          },
        ],
      }),
    }),
  )
  return { calls }
}

test('admin Display Settings switches context and uses set/reset APIs', async ({ page }) => {
  await mockSession(page)
  const { calls } = await mockAdminApi(page)
  await page.goto('/system/display-settings')
  await expect(page.getByRole('heading', { name: 'Cấu hình hiển thị' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Date & Time' })).toBeVisible()
  await expect(page.locator('#display-context')).toHaveValue('SYSTEM')

  const quantity = page.locator('#setting-Display\\.quantityDecimals')
  await quantity.fill('4')
  await page.getByRole('button', { name: 'Lưu override' }).nth(7).dispatchEvent('click')
  await expect.poll(() => calls.some((call) => call.startsWith('PATCH '))).toBe(true)
  await page.getByRole('button', { name: 'Dùng giá trị kế thừa' }).first().dispatchEvent('click')
  await expect.poll(() => calls.some((call) => call.startsWith('POST '))).toBe(true)

  await page.locator('#display-context').selectOption('ORGANIZATION')
  await page.locator('#display-organization').click()
  await page
    .locator('.n-tree-node-content__text')
    .getByText('North Branch (BR-12)', { exact: true })
    .click()
  await expect(page.locator('#display-organization')).toContainText(
    'Dinktech Group (ROOT) / North Organization (ORG-11) / North Branch (BR-12)',
  )
  await expect.poll(() => calls.some((call) => call.includes('organization_id=12'))).toBe(true)
  await expect(page.getByText('Inherited').first()).toBeVisible()
})

test('My Display Preferences stays local and isolates user namespace', async ({ page }) => {
  await mockSession(page)
  await page.goto('/profile/display-preferences')
  await expect(page.getByRole('heading', { name: 'Tùy chọn hiển thị' })).toBeVisible()
  await page.locator('#preference-Display\\.quantityDecimals').fill('1')
  await page.locator('#preference-Display\\.quantityDecimals').dispatchEvent('change')
  await expect
    .poll(() => page.evaluate(() => localStorage.getItem('ui.presentation.7')))
    .toContain('quantityDecimals')
  await page
    .getByRole('button', { name: 'Dùng giá trị Organization/System' })
    .first()
    .dispatchEvent('click')
  await expect
    .poll(() => page.evaluate(() => localStorage.getItem('ui.presentation.7')))
    .not.toContain('quantityDecimals')
  await page.locator('#preference-Display\\.quantityDecimals').fill('1')
  await page.locator('#preference-Display\\.quantityDecimals').dispatchEvent('change')
  await page.reload()
  await expect(page.locator('#preference-Display\\.quantityDecimals')).toHaveValue('1')
  await page.locator('#preference-language').selectOption('en')
  await expect.poll(() => page.evaluate(() => localStorage.getItem('ui.locale'))).toBe('en')
})
