/**
 * NFX-D1 browser-level regression against the real Vue UI and a fully mocked API.
 * IMPORTANT: these tests intentionally do NOT touch a database, SMTP or live Push.
 * Real, authenticated Browser UAT is a separate release gate.
 */
import { expect, test, type Page, type Route } from '@playwright/test'
import { messages } from '../src/i18n'

type Policy = {
  key: string
  default_value: boolean
  allow_user_override: boolean
  mandatory: boolean
  capability: 'AVAILABLE' | 'UNAVAILABLE'
  producer_state: 'ACTIVE' | 'INACTIVE'
  revision: string
  override_epoch: string
}
type Preference = {
  key: string
  system_default: boolean
  allow_user_override: boolean
  user_override: boolean | null
  effective_value: boolean
  effective_source: string
  override_epoch: string
  user_revision: string
  locked: boolean
  capability: 'AVAILABLE' | 'UNAVAILABLE'
  producer_state: 'ACTIVE' | 'INACTIVE'
}
type PatchCall = { path: string; changes: unknown[] }
const keys = [
  'import.completed.in_app', 'import.completed_with_errors.in_app',
  'import.failed.in_app', 'workflow.action_required.in_app',
  'security.permission_changed.in_app', 'import.completed.email',
  'import.completed_with_errors.email', 'import.failed.email',
  'import.completed.push',
] as const

function policy(key: string): Policy {
  const available = key !== 'security.permission_changed.in_app'
  return {
    key, default_value: key.endsWith('.in_app'), allow_user_override: available,
    mandatory: false, capability: available ? 'AVAILABLE' : 'UNAVAILABLE',
    producer_state: available ? 'ACTIVE' : 'INACTIVE',
    revision: '5', override_epoch: '2',
  }
}
function preference(row: Policy): Preference {
  return {
    key: row.key, system_default: row.default_value,
    allow_user_override: row.allow_user_override, user_override: null,
    effective_value: row.default_value, effective_source: 'SYSTEM_DEFAULT',
    override_epoch: row.override_epoch, user_revision: '0',
    locked: row.capability === 'UNAVAILABLE', capability: row.capability,
    producer_state: row.producer_state,
  }
}

async function mockApi(page: Page, opts: { adminEdit?: boolean; rejectNextPreference?: boolean; rejectNextPolicy?: boolean } = {}) {
  const policies = keys.map(policy)
  const preferences = policies.map(preference)
  const mutations: PatchCall[] = []
  const deviceMutations: string[] = []
  let preferenceConflict = opts.rejectNextPreference ?? false
  let policyConflict = opts.rejectNextPolicy ?? false

  // Always intercept ALL API requests; fail closed rather than letting a test
  // accidentally reach real SMTP, Push, policy, or authorization endpoints.
  await page.route('**/api/v1/**', async (route: Route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    const method = request.method()
    const origin = request.headers().origin || 'http://localhost:5173'
    const headers = {
      'Access-Control-Allow-Origin': origin,
      'Access-Control-Allow-Credentials': 'true',
      'Access-Control-Allow-Methods': 'GET, PATCH, POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, X-CSRF-TOKEN, Accept, Accept-Language, X-Request-ID',
      'X-CSRF-TOKEN': 'nfx-d1-test-csrf',
    }
    const reply = (status: number, data: unknown) => route.fulfill({
      status, contentType: 'application/json', headers,
      body: JSON.stringify({ data, meta: { request_id: 'nfx-d1-mock' } }),
    })
    if (method === 'OPTIONS') return route.fulfill({ status: 204, headers })
    if (method === 'GET' && path === '/api/v1/auth/me') return reply(200, {
      user: {
        id: 7, username: 'nfx-d1-qa', name: 'NFX-D1 QA', email: 'qa@example.test',
        active: true, status: 'ACTIVE', groups: ['orgadmin'],
        base_permissions: [
          'notification_setting.view',
          ...(opts.adminEdit === false ? [] : ['notification_setting.edit']),
          'display_setting.view',
        ],
      },
    })
    if (method === 'GET' && path === '/api/v1/auth/csrf') {
      return reply(200, { csrf_token: 'nfx-d1-test-csrf', header_name: 'X-CSRF-TOKEN' })
    }
    if (path === '/api/v1/notification-settings') {
      if (method === 'GET') return reply(200, policies)
      if (method !== 'PATCH') return reply(405, null)
      const changes = request.postDataJSON()?.changes
      if (!Array.isArray(changes) || changes.length !== 1) return reply(422, null)
      mutations.push({ path, changes })
      if (policyConflict) {
        policyConflict = false
        return reply(409, null)
      }
      const change = changes[0]
      const row = policies.find((item) => item.key === change.key)
      if (!row || change.expected_revision !== row.revision) return reply(409, null)
      if (!row.capability || row.capability === 'UNAVAILABLE') return reply(422, null)
      if ((row.key.endsWith('.email') || row.key.endsWith('.push')) && change.default_value === true) {
        return reply(422, null)
      }
      if (row.allow_user_override !== change.allow_user_override) {
        row.override_epoch = String(Number(row.override_epoch) + 1)
      }
      row.default_value = change.default_value
      row.allow_user_override = change.allow_user_override
      row.revision = String(Number(row.revision) + 1)
      return reply(200, policies)
    }
    if (path === '/api/v1/notifications/preferences') {
      if (method === 'GET') return reply(200, preferences)
      if (method !== 'PATCH') return reply(405, null)
      const changes = request.postDataJSON()?.changes
      if (!Array.isArray(changes) || changes.length !== 1) return reply(422, null)
      mutations.push({ path, changes })
      if (preferenceConflict) {
        preferenceConflict = false
        return reply(409, null)
      }
      const change = changes[0]
      const row = preferences.find((item) => item.key === change.key)
      if (!row || row.locked || change.expected_user_revision !== row.user_revision
          || change.expected_override_epoch !== row.override_epoch) return reply(409, null)
      row.user_override = change.value
      row.user_revision = String(Number(row.user_revision) + 1)
      row.effective_value = change.value ?? row.system_default
      row.effective_source = change.value === null ? 'SYSTEM_DEFAULT' : 'USER'
      return reply(200, preferences)
    }
    if (method === 'GET' && path === '/api/v1/notifications/push/config') {
      return reply(200, { available: true, queue_enabled: true, send_enabled: true, public_key: null })
    }
    if (path.startsWith('/api/v1/notifications/push/subscriptions')) {
      if (method === 'GET') return reply(200, [])
      deviceMutations.push(`${method} ${path}`)
      return reply(403, null)
    }
    // Other shell GET requests are bounded and mocked; ALL writes fail closed.
    if (method === 'GET') return reply(200, [])
    return reply(403, null)
  })
  return { policies, preferences, mutations, deviceMutations }
}

function personalRow(page: Page, label: string) {
  return page.locator('.content-panel.m11-panel .m11-row').filter({ hasText: label })
}
function adminRow(page: Page, key: string) {
  return page.locator('.content-panel.m11-panel .m11-row').filter({ has: page.locator('.m11-key', { hasText: key }) })
}
async function personalPage(page: Page) {
  await page.goto('/profile/display-preferences?tab=notifications')
  await expect(page.getByRole('heading', { name: messages.vi.notificationSettings.personalTitle })).toBeVisible()
}
async function systemPage(page: Page) {
  await page.goto('/system/display-settings?tab=notifications')
  await expect(page.getByRole('heading', { name: messages.vi.notificationSettings.systemTitle })).toBeVisible()
}

test('D1 browser: all nine personal policies are grouped 5/3/1 with Inherit clearly non-consenting', async ({ page }) => {
  const mock = await mockApi(page)
  await personalPage(page)
  const groups = page.locator('.content-panel.m11-panel .m11-group')
  await expect(groups).toHaveCount(3)
  for (const [i, count] of [5, 3, 1].entries()) {
    await expect(groups.nth(i).locator('.m11-row')).toHaveCount(count)
  }
  const failed = personalRow(page, messages.vi.notificationSettings.keys.importFailedEmail)
  await expect(failed.locator('select option[value="inherit"]')).toHaveText(messages.vi.notificationSettings.noConsentInherit)
  await expect(failed.locator('select')).toHaveValue('inherit')
  await expect(failed.locator('button')).toBeDisabled()
  await expect(page.locator('.content-panel.m11-panel')).toContainText(messages.vi.notificationSettings.sourceSystem)
  expect(mock.mutations).toHaveLength(0)
})

test('D1 browser: independent Import failure opt-in saves once, survives reload, then can be revoked', async ({ page }) => {
  const mock = await mockApi(page)
  await personalPage(page)
  const failed = personalRow(page, messages.vi.notificationSettings.keys.importFailedEmail)
  const partial = personalRow(page, messages.vi.notificationSettings.keys.importCompletedWithErrorsEmail)
  await failed.locator('select').selectOption('on')
  await expect(failed).toContainText(messages.vi.notificationSettings.unsaved)
  await expect(partial.locator('select')).toHaveValue('inherit')
  await expect(failed.locator('button')).toBeEnabled()
  expect(mock.mutations).toHaveLength(0)
  await failed.locator('button').click()
  await expect(failed.locator('button')).toBeDisabled()
  await expect(page.getByText(messages.vi.notificationSettings.saved)).toBeVisible()
  expect(mock.mutations).toHaveLength(1)
  expect(mock.mutations[0]?.changes).toEqual([{
    key: 'import.failed.email', value: true,
    expected_override_epoch: '2', expected_user_revision: '0',
  }])
  await page.reload()
  await expect(failed.locator('select')).toHaveValue('on')
  await failed.locator('select').selectOption('off')
  await failed.locator('button').click()
  // A disabled button also occurs during the in-flight request. Wait for the
  // server acknowledgement before inspecting the captured mutation and CAS.
  await expect(page.locator('.content-panel.m11-panel > p[role="status"]'))
    .toHaveText(messages.vi.notificationSettings.saved)
  await expect(failed.locator('button')).toBeDisabled()
  await expect.poll(() => mock.mutations.length).toBe(2)
  expect(mock.mutations[1]?.changes).toEqual([{
    key: 'import.failed.email', value: false,
    expected_override_epoch: '2', expected_user_revision: '1',
  }])
  await page.reload()
  await expect(failed.locator('select')).toHaveValue('off')
  await expect(partial.locator('select')).toHaveValue('inherit')
})

test('D1 browser: Push draft never authorizes device registration or an API write', async ({ page }) => {
  const mock = await mockApi(page)
  await personalPage(page)
  const pushRow = personalRow(page, messages.vi.notificationSettings.keys.importCompletedPush)
  const subscribe = page.getByRole('button', { name: messages.vi.pushDevices.subscribe })
  await expect(subscribe).toBeDisabled()
  await pushRow.locator('select').selectOption('on')
  await expect(pushRow.locator('button')).toBeEnabled()
  await expect(subscribe).toBeDisabled()
  expect(mock.mutations).toHaveLength(0)
  expect(mock.deviceMutations).toHaveLength(0)
})

test('D1 browser: locked unavailable policy cannot be edited or submitted', async ({ page }) => {
  const mock = await mockApi(page)
  await personalPage(page)
  const locked = personalRow(page, messages.vi.notificationSettings.keys.securityPermissionChanged)
  await expect(locked.locator('select')).toBeDisabled()
  await expect(locked.locator('button')).toBeDisabled()
  await expect(locked).toContainText(messages.vi.notificationSettings.unavailable)
  expect(mock.mutations).toHaveLength(0)
})

test('D1 browser: 409 on personal save retains draft until explicit Reload', async ({ page }) => {
  const mock = await mockApi(page, { rejectNextPreference: true })
  await personalPage(page)
  const failed = personalRow(page, messages.vi.notificationSettings.keys.importFailedEmail)
  await failed.locator('select').selectOption('on')
  await failed.locator('button').click()
  // Global toast and the inline error both have role=alert; target the actual form error.
  await expect(page.locator('.content-panel.m11-panel > p.inline-error[role="alert"]'))
    .toContainText(messages.vi.notificationSettings.conflict)
  await expect(failed.locator('select')).toHaveValue('on')
  await expect(failed.locator('button')).toBeEnabled()
  await page.getByRole('button', { name: messages.vi.notificationSettings.reload }).click()
  await expect(failed.locator('select')).toHaveValue('inherit')
  await expect(failed.locator('button')).toBeDisabled()
  expect(mock.mutations).toHaveLength(1)
})

test('D1 browser: read-only PBAC cannot change system policies', async ({ page }) => {
  const mock = await mockApi(page, { adminEdit: false })
  await systemPage(page)
  const groups = page.locator('.content-panel.m11-panel .m11-group')
  await expect(groups).toHaveCount(3)
  await expect(groups.nth(0).locator('.m11-row')).toHaveCount(5)
  await expect(groups.nth(1).locator('.m11-row')).toHaveCount(3)
  await expect(groups.nth(2).locator('.m11-row')).toHaveCount(1)
  await expect(page.getByText(messages.vi.notificationSettings.readOnly)).toBeVisible()
  const buttons = page.locator('.content-panel.m11-panel .m11-row button')
  const count = await buttons.count()
  for (let i = 0; i < count; i++) await expect(buttons.nth(i)).toBeDisabled()
  expect(mock.mutations).toHaveLength(0)
})

test('D1 browser: email/push defaults stay disabled; override revocation warns, uses CAS and is row-scoped', async ({ page }) => {
  const mock = await mockApi(page)
  await systemPage(page)
  for (const group of [1, 2]) {
    for (const row of await page.locator('.content-panel.m11-panel .m11-group').nth(group).locator('.m11-row').all()) {
      await expect(row.locator('input[type="checkbox"]').first()).toBeDisabled()
    }
  }
  const failed = adminRow(page, 'import.failed.email')
  await expect(failed.locator('button')).toBeDisabled()
  await failed.locator('input[type="checkbox"]').nth(1).uncheck()
  await expect(failed).toContainText(messages.vi.notificationSettings.overrideDisableWarning)
  await expect(failed.locator('button')).toBeEnabled()
  await failed.locator('button').click()
  // Do not confuse the transient saving-disabled state with completed save.
  await expect(page.locator('.content-panel.m11-panel > p[role="status"]'))
    .toHaveText(messages.vi.notificationSettings.saved)
  await expect(failed.locator('button')).toBeDisabled()
  await expect.poll(() => mock.mutations.length).toBe(1)
  expect(mock.mutations[0]?.changes).toEqual([{
    key: 'import.failed.email', default_value: false, allow_user_override: false,
    expected_revision: '5',
  }])
  await failed.locator('input[type="checkbox"]').nth(1).check()
  await failed.locator('button').click()
  await expect(page.locator('.content-panel.m11-panel > p[role="status"]'))
    .toHaveText(messages.vi.notificationSettings.saved)
  await expect(failed.locator('button')).toBeDisabled()
  await expect.poll(() => mock.mutations.length).toBe(2)
  expect(mock.mutations[1]?.changes).toEqual([{
    key: 'import.failed.email', default_value: false, allow_user_override: true,
    expected_revision: '6',
  }])
})

test('D1 browser: system policy 409 preserves pending draft and never retries silently', async ({ page }) => {
  const mock = await mockApi(page, { rejectNextPolicy: true })
  await systemPage(page)
  const failed = adminRow(page, 'import.failed.email')
  await failed.locator('input[type="checkbox"]').nth(1).uncheck()
  await failed.locator('button').click()
  // Global toast and the inline error both have role=alert; target the actual form error.
  await expect(page.locator('.content-panel.m11-panel > p.inline-error[role="alert"]'))
    .toContainText(messages.vi.notificationSettings.conflict)
  await expect(failed.locator('input[type="checkbox"]').nth(1)).not.toBeChecked()
  await expect(failed.locator('button')).toBeEnabled()
  expect(mock.mutations).toHaveLength(1)
  await page.getByRole('button', { name: messages.vi.notificationSettings.reload }).click()
  await expect(failed.locator('input[type="checkbox"]').nth(1)).toBeChecked()
})

test('D1 browser: English translation and mobile-width layout remain usable', async ({ page }) => {
  await mockApi(page)
  await page.addInitScript(() => window.localStorage.setItem('ui.locale', 'en'))
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/profile/display-preferences?tab=notifications')
  await expect(page.getByRole('heading', { name: messages.en.notificationSettings.personalTitle })).toBeVisible()
  await expect(page.getByRole('heading', { name: messages.en.notificationSettings.channels.email })).toBeVisible()
  await expect(page.locator('.content-panel.m11-panel .m11-row')).toHaveCount(9)
  const failed = personalRow(page, messages.en.notificationSettings.keys.importFailedEmail)
  await expect(failed.locator('select option[value="inherit"]')).toHaveText(messages.en.notificationSettings.noConsentInherit)
  const dimensions = await failed.evaluate((element) => ({ scroll: element.scrollWidth, client: element.clientWidth }))
  expect(dimensions.scroll).toBeLessThanOrEqual(dimensions.client + 2)
  await expect(failed.locator('select')).toBeVisible()
})

// NFX-D2: real router/workspace integration; API remains entirely mocked.
test('D2 browser: personal draft blocks tab switch on Cancel and discards only on confirmation', async ({ page }) => {
  const mock = await mockApi(page)
  await personalPage(page)
  const failed = personalRow(page, messages.vi.notificationSettings.keys.importFailedEmail)
  await failed.locator('select').selectOption('on')
  await expect(failed.locator('button')).toBeEnabled()
  let dismissed = 0
  page.once('dialog', async (dialog) => { dismissed++; await dialog.dismiss() })
  const display = page.getByRole('tab', { name: messages.vi.notificationSettings.displayTab })
  await display.click()
  await expect(page.getByRole('heading', { name: messages.vi.notificationSettings.personalTitle })).toBeVisible()
  expect(dismissed).toBe(1)
  expect(mock.mutations).toHaveLength(0)
  let accepted = 0
  page.once('dialog', async (dialog) => { accepted++; await dialog.accept() })
  await display.click()
  await expect(display).toHaveAttribute('aria-selected', 'true')
  expect(accepted).toBe(1)
})

test('D2 browser: system draft guards tab navigation and reverting it removes the guard', async ({ page }) => {
  const mock = await mockApi(page)
  await systemPage(page)
  const failed = adminRow(page, 'import.failed.email')
  const override = failed.locator('input[type="checkbox"]').nth(1)
  await override.uncheck()
  await expect(failed.locator('button')).toBeEnabled()
  let dismissed = 0
  page.once('dialog', async (dialog) => { dismissed++; await dialog.dismiss() })
  const display = page.getByRole('tab', { name: messages.vi.notificationSettings.displayTab })
  await display.click()
  await expect(page.getByRole('heading', { name: messages.vi.notificationSettings.systemTitle })).toBeVisible()
  expect(dismissed).toBe(1)
  await override.check() // revert to acknowledged value
  await expect(failed.locator('button')).toBeDisabled()
  await display.click() // no dialog once the draft is clean
  await expect(display).toHaveAttribute('aria-selected', 'true')
  expect(mock.mutations).toHaveLength(0)
})
