/** E1.1 Browser QA: all API calls mocked, no DB/email/push/cron effects. */
import { expect, test, type Page, type Route } from '@playwright/test'
import { messages } from '../src/i18n'

function sample() {
  return {
    as_of_utc: '2026-10-27T07:00:00.000000Z',
    queues: {
      outbox: { available: true, status_counts: { PENDING: 1, PROCESSED: 8 }, overdue_ready_15m: 1, expired_leases: 0 },
      email: { available: true, status_counts: { SENT: 5 }, overdue_ready_15m: 0, expired_leases: 0 },
      push: { available: true, status_counts: { SENT: 1, CANCELLED: 1 }, overdue_ready_15m: 0, expired_leases: 0 },
    },
    runtime_switches: [
      { key: 'DINKERP_M11_TASKS_INAPP_ENABLED', section: 'scheduler', enabled: true, source: 'database' },
      { key: 'DINKERP_M11_TASKS_EMAIL_ENABLED', section: 'scheduler', enabled: true, source: 'database' },
      { key: 'DINKERP_M11_TASKS_PUSH_ENABLED', section: 'scheduler', enabled: true, source: 'database' },
    ],
    cron: { health: 'UNKNOWN', reason: 'TELEMETRY_UNAVAILABLE', last_tick_at: null },
    telemetry_available: false,
  }
}

function sampleDiagnostics() {
  const queue = {
    available: true, ready: 2, retry_ready: 1, retry_delayed: 0, dead: 1,
    expired_leases: 0, overdue_ready_15m: 1, overdue_ready_60m: 1,
    oldest_ready_minutes: 90, error_categories: { TRANSPORT: 1, OTHER: 1 },
    findings: ['DEAD_PRESENT', 'OVERDUE_60M', 'OVERDUE_15M', 'RETRY_READY'],
  }
  return {
    as_of_utc: '2026-10-27T10:05:00.000000Z',
    queues: { outbox: queue, email: queue, push: queue },
    cron: { health: 'UNKNOWN', reason: 'TELEMETRY_UNAVAILABLE' },
  }
}

async function mockApi(page: Page, options: {
  view?: boolean; overviewStatus?: number; diagnosticsStatus?: number; settingsOnly?: boolean
  revokeOnDiagnostics403?: boolean
} = {}) {
  const calls: string[] = []
  const diagnosticCalls: string[] = []
  const writes: string[] = []
  const allowed = options.view !== false && options.settingsOnly !== true
  let revoked = false
  let authMeCalls = 0
  await page.route('**/api/v1/**', async (route: Route) => {
    const request = route.request()
    const method = request.method()
    const path = new URL(request.url()).pathname
    const origin = request.headers().origin || 'http://localhost:5173'
    const headers = {
      'Access-Control-Allow-Origin': origin,
      'Access-Control-Allow-Credentials': 'true',
      'Access-Control-Allow-Methods': 'GET, PATCH, POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, X-CSRF-TOKEN, Accept, Accept-Language, X-Request-ID',
      'X-CSRF-TOKEN': 'nfx-e1-test-csrf',
    }
    const reply = (status: number, data: unknown) => route.fulfill({
      status, contentType: 'application/json', headers,
      body: JSON.stringify({ data, meta: { request_id: 'nfx-e1-mock' } }),
    })
    if (method === 'OPTIONS') return route.fulfill({ status: 204, headers })
    if (method === 'GET' && path === '/api/v1/auth/me') {
      authMeCalls += 1
      return reply(200, {
        user: {
          id: 7, username: 'nfx-e1-qa', name: 'E1 QA', email: 'qa@example.test',
          active: true, status: 'ACTIVE', groups: ['orgadmin'],
          base_permissions: options.settingsOnly ? ['notification_setting.view']
            : (allowed && !revoked ? ['notification_operations.view'] : []),
        },
      })
    }
    if (method === 'GET' && path === '/api/v1/auth/csrf') return reply(200, {
      csrf_token: 'nfx-e1-test-csrf', header_name: 'X-CSRF-TOKEN',
    })
    if (path === '/api/v1/notification-operations/diagnostics') {
      diagnosticCalls.push(`${method} ${path}`)
      if (method !== 'GET' || !allowed) return reply(403, null)
      if (options.diagnosticsStatus) {
        // A genuine 403 after initial access can mean PBAC was revoked meanwhile.
        // The subsequent /auth/me refresh must reflect the revoked permission.
        if (options.diagnosticsStatus === 403 && options.revokeOnDiagnostics403) revoked = true
        return reply(options.diagnosticsStatus, null)
      }
      return reply(200, sampleDiagnostics())
    }
    if (path === '/api/v1/notification-operations/overview') {
      calls.push(`${method} ${path}`)
      if (method !== 'GET' || !allowed) return reply(403, null)
      if (options.overviewStatus) return reply(options.overviewStatus, null)
      return reply(200, sample())
    }
    if (method === 'GET') return reply(200, [])
    writes.push(`${method} ${path}`)
    return reply(403, null)
  })
  return { calls, diagnosticCalls, writes, authMeCalls: () => authMeCalls }
}

test('E1.1: read-only queues render in Vietnamese; configured Tasks never mark Cron healthy', async ({ page }) => {
  const mock = await mockApi(page)
  await page.goto('/system/notification-operations')
  await expect(page.getByRole('heading', { name: messages.vi.notificationOperations.title })).toBeVisible()
  await expect(page.locator('[data-testid="ops-queue-outbox"]')).toContainText('8')
  await expect(page.locator('[data-testid="ops-queue-email"]')).toContainText('5')
  await expect(page.locator('[data-testid="ops-queue-push"]')).toContainText('1')
  await expect(page.locator('[data-testid="ops-cron"]')).toContainText(messages.vi.notificationOperations.unknown)
  await expect(page.locator('[data-testid="ops-runtime"]')).toContainText(messages.vi.notificationOperations.runtimeNote)
  await expect(page.getByRole('heading', { name: messages.vi.notificationOperations.title })).toHaveCount(1)
  expect(mock.calls).toEqual(['GET /api/v1/notification-operations/overview'])
  expect(mock.writes).toHaveLength(0)
  await page.getByTestId('ops-refresh').click()
  await expect.poll(() => mock.calls.length).toBe(2)
  expect(mock.writes).toHaveLength(0)
})

test('E1.1: without view permission, menu and dashboard data are hidden', async ({ page }) => {
  const mock = await mockApi(page, { view: false })
  await page.goto('/system/notification-operations')
  await expect(page.locator('.m11-operations [role="alert"]')).toContainText(messages.vi.notificationOperations.notAuthorized)
  await expect(page.locator('[data-testid="ops-queue-outbox"]')).toHaveCount(0)
  expect(mock.calls).toHaveLength(0)
  expect(mock.writes).toHaveLength(0)
})

test('E1.1: failed overview HTTP request does not display stale numbers', async ({ page }) => {
  const mock = await mockApi(page, { overviewStatus: 500 })
  await page.goto('/system/notification-operations')
  await expect(page.locator('.m11-operations [role="alert"]')).toContainText(messages.vi.notificationOperations.loadError)
  await expect(page.locator('[data-testid="ops-queue-outbox"]')).toHaveCount(0)
  expect(mock.calls).toEqual(['GET /api/v1/notification-operations/overview'])
  expect(mock.writes).toHaveLength(0)
})


test('E1.2A: legacy Settings VIEW is insufficient for the Operations page', async ({ page }) => {
  const mock = await mockApi(page, { settingsOnly: true })
  await page.goto('/system/notification-operations')
  await expect(page.locator('.m11-operations [role="alert"]')).toContainText(messages.vi.notificationOperations.notAuthorized)
  await expect(page.locator('[data-testid="ops-queue-outbox"]')).toHaveCount(0)
  expect(mock.calls).toHaveLength(0)
  expect(mock.writes).toHaveLength(0)
})


test('E1.2B: aggregates and sanitized error categories display without any mutation', async ({ page }) => {
  const mock = await mockApi(page)
  await page.goto('/system/notification-operations')
  await expect(page.getByTestId('ops-diagnostic-outbox')).toContainText('90')
  await expect(page.getByTestId('ops-diagnostic-email')).toContainText('2')
  await expect(page.getByTestId('ops-diagnostics')).toContainText(messages.vi.notificationOperations.diagnostics.title)
  await expect(page.getByTestId('ops-diagnostics')).toContainText(messages.vi.notificationOperations.diagnostics.cronDisclaimer)
  await expect(page.getByTestId('ops-diagnostics')).not.toContainText('last_error_code')
  expect(mock.diagnosticCalls).toEqual(['GET /api/v1/notification-operations/diagnostics'])
  expect(mock.writes).toHaveLength(0)
})

test('E1.2B: diagnostics error does not conceal E1.1 overview or invent health', async ({ page }) => {
  const mock = await mockApi(page, { diagnosticsStatus: 500 })
  await page.goto('/system/notification-operations')
  await expect(page.getByTestId('ops-diagnostics-error')).toBeVisible()
  await expect(page.getByTestId('ops-queue-outbox')).toContainText('8')
  await expect(page.getByTestId('ops-cron')).toContainText(messages.vi.notificationOperations.unknown)
  expect(mock.calls).toEqual(['GET /api/v1/notification-operations/overview'])
  expect(mock.diagnosticCalls).toEqual(['GET /api/v1/notification-operations/diagnostics'])
  expect(mock.writes).toHaveLength(0)
})

test('E1.2B: Settings-only user cannot request diagnostics, even with direct page URL', async ({ page }) => {
  const mock = await mockApi(page, { settingsOnly: true })
  await page.goto('/system/notification-operations')
  await expect(page.locator('.m11-operations [role="alert"]')).toContainText(messages.vi.notificationOperations.notAuthorized)
  expect(mock.calls).toHaveLength(0)
  expect(mock.diagnosticCalls).toHaveLength(0)
  expect(mock.writes).toHaveLength(0)
})

// E1.3 presentation regression: mocked API responses do not replace backend PBAC tests.
test('E1.3: Diagnostics 403 refreshes revoked PBAC and hides ALL Operations metrics', async ({ page }) => {
  // The client treats any protected 403 as a stale permission snapshot.
  // A revoked permission removes the parent Overview (and its child Diagnostics).
  // Unlike an ordinary 500, a 403 must NOT leave old Overview metrics visible.
  const mock = await mockApi(page, { diagnosticsStatus: 403, revokeOnDiagnostics403: true })
  await page.goto('/system/notification-operations')
  await expect.poll(() => mock.authMeCalls()).toBeGreaterThanOrEqual(2)
  await expect(page.locator('.m11-operations [role="alert"]'))
    .toContainText(messages.vi.notificationOperations.notAuthorized)
  await expect(page.getByTestId('ops-queue-outbox')).toHaveCount(0)
  await expect(page.getByTestId('ops-queue-email')).toHaveCount(0)
  await expect(page.getByTestId('ops-diagnostics')).toHaveCount(0)
  await expect(page.getByTestId('ops-cron')).toHaveCount(0)
  expect(mock.calls).toEqual(['GET /api/v1/notification-operations/overview'])
  expect(mock.diagnosticCalls).toEqual(['GET /api/v1/notification-operations/diagnostics'])
  expect(mock.writes).toHaveLength(0)
})

test('E1.3: Overview 401 denies all metrics and makes no diagnostics request', async ({ page }) => {
  const mock = await mockApi(page, { overviewStatus: 401 })
  await page.goto('/system/notification-operations')
  await expect(page.getByTestId('ops-queue-outbox')).toHaveCount(0)
  await expect(page.getByTestId('ops-diagnostics')).toHaveCount(0)
  await expect.poll(() => mock.calls.length).toBe(1)
  expect(mock.calls).toEqual(['GET /api/v1/notification-operations/overview'])
  expect(mock.diagnosticCalls).toHaveLength(0)
  expect(mock.writes).toHaveLength(0)
})

test('E1.3: repeated refresh only issues GET, never runs or retries a worker', async ({ page }) => {
  const mock = await mockApi(page)
  await page.goto('/system/notification-operations')
  await expect(page.getByTestId('ops-queue-outbox')).toContainText('8')
  await page.getByTestId('ops-refresh').click()
  await expect.poll(() => mock.calls.length).toBe(2)
  await expect(page.getByTestId('ops-queue-outbox')).toContainText('8')
  await expect(page.getByTestId('ops-diagnostics')).toBeVisible()
  await page.getByTestId('ops-refresh').click()
  await expect.poll(() => mock.calls.length).toBe(3)
  expect(mock.writes).toHaveLength(0)
  expect(mock.calls.every(call => call === 'GET /api/v1/notification-operations/overview')).toBe(true)
  expect(mock.diagnosticCalls.every(call => call === 'GET /api/v1/notification-operations/diagnostics')).toBe(true)
})
