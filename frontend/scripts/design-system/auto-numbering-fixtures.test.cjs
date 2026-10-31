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

test('Auto Numbering uses an atomic overview and retains scoped SDK refresh', () => {
  const service = read('src/services/autoNumbering.ts')
  const view = read('src/features/workflow/pages/AutoNumberingView.vue')
  const overviewUnit = read('src/services/__tests__/autoNumberingOverview.spec.ts')
  assert.match(view, /getAutoNumberingOverview\(\)/)
  assert.doesNotMatch(view, /listAllNumberingSeries\(/)
  assert.match(service, /'\/api\/v1\/numbering-series\/overview'/)
  assert.match(allTest, /GET \/api\/v1\/numbering-series\/overview/)
  assert.match(overviewUnit, /toHaveBeenCalledExactlyOnceWith/)
  assert.match(overviewUnit, /cross-Resource series/)
  assert.match(service, /listNumberingSeries\(\{/)
  assert.match(unit, /listNumberingSeries: sdkList/)
  assert.doesNotMatch(service, /aggregateNumberingSeries\(/)
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
