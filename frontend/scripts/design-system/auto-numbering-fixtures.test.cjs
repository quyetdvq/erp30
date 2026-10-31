// HF12 regression: Auto Numbering tests must emulate real SDK operations and
// isolate their browser session from unrelated shell requests.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const read = (rel) => fs.readFileSync(path.join(__dirname, '../..', rel), 'utf8')
const fixture = read('e2e/support/autoNumberingApiFixture.ts')
const allTest = read('e2e/ds5-hf10-auto-numbering-all.spec.ts')
const originalTest = read('e2e/fe050-auto-numbering.spec.ts')
const unit = read('src/services/__tests__/autoNumberingAll.spec.ts')

test('All resource aggregation is unit tested without mocked SDK transport concurrency', () => {
  const service = read('src/services/autoNumbering.ts')
  assert.match(service, /export async function aggregateNumberingSeries\(/)
  assert.match(service, /return aggregateNumberingSeries\(resourceIds, listNumberingSeriesPage\)/)
  assert.match(unit, /aggregateNumberingSeries\(\['1', '2', '3', '4'/)
  assert.match(unit, /readPage\.mockImplementation/)
  assert.match(unit, /production All resource delegates through the typed SDK reader/)
  assert.match(unit, /listNumberingSeries: sdkList/)
  assert.match(unit, /expect\(options\.throwOnError\)\.toBe\(true\)/)
  assert.doesNotMatch(unit, /sdkList\.mockImplementation/)
})

test('both Auto Numbering browser suites isolate background Inbox and CORS from fixture API', () => {
  for (const suite of [allTest, originalTest]) {
    assert.match(suite, /mockAutoNumberingSession\(page/)
    assert.match(suite, /mockAutoNumberingPreflight\(page\)/)
    assert.match(suite, /fulfillAutoNumberingFixture\(route/)
    assert.doesNotMatch(suite, /route\.continue\(\)/)
  }
  assert.match(fixture, /\/api\/v1\/notifications/)
  assert.match(fixture, /\/api\/v1\/display-settings\/runtime/)
  assert.match(fixture, /E2E_UNMOCKED_API/)
  assert.match(fixture, /\/actions\/sync/)
  assert.match(fixture, /\/summary/)
  assert.match(fixture, /status: 204/)
  assert.match(fixture, /Access-Control-Allow-Origin/)
  assert.match(fixture, /Access-Control-Allow-Credentials/)
  assert.match(fixture, /route\.fallback\(\)/)
  assert.doesNotMatch(fixture, /\/api\/v1\/auth\/logout/)
})

test('AppShell Display Settings load is isolated so its 401 cannot end mock sessions', () => {
  const shell = read('src/components/AppShell.vue')
  const api = read('src/services/api.ts')
  assert.match(shell, /displaySettings\.load\(userId\)/)
  assert.match(api, /auth\.expireSession\(\)/)
  assert.match(fixture, /display-settings\/runtime/)
  assert.match(fixture, /status: 200[\s\S]*?timezone: 'Asia\/Ho_Chi_Minh'/)
})
