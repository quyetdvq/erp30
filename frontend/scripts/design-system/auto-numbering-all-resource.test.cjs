// DS5 HF10: All resource is a listing scope, never a Resource route identifier.
// These checks complement transport unit tests and browser UI tests.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const read = (rel) => fs.readFileSync(path.join(__dirname, '../..', rel), 'utf8')
const view = read('src/features/workflow/pages/AutoNumberingView.vue')
const transport = read('src/services/autoNumbering.ts')

test('Auto Numbering defaults to All resource and aggregates authorized Resource lists', () => {
  assert.match(view, /const selectedResourceId = ref\(''\)/)
  assert.match(view, /<SearchableSelect[\s\S]*?:model-value="selectedResourceId"/)
  assert.match(view, /:placeholder="t\('numbering\.allResources'\)"/)
  assert.doesNotMatch(view, /selectedResourceId\.value\s*=\s*String\(result\[0\]\.id\)/)
  assert.match(view, /await loadSeries\(\)/)
  assert.match(view, /await listAllNumberingSeries\(ids\)/)
  assert.match(view, /const result = await listNumberingSeriesPage\(resourceId\)/)
})

test('All resource is never passed as an API path; the aggregator fails closed on permission loss', () => {
  assert.match(transport, /listNumberingSeries\(\{[\s\S]*?path: \{ resource_id: resourceId \}/)
  assert.match(transport, /const ids = \[\.\.\.new Set\(resourceIds\.filter/)
  assert.match(transport, /Math\.min\(4, ids\.length\)/)
  assert.match(view, /rowAllowedActions\(item\)\?\.configure_numbering === true/)
  assert.doesNotMatch(transport, /status === 403 \|\| status === 404/)
  assert.match(transport, /if \(failure\?\.status === 'rejected'\) throw failure\.reason/)
  assert.match(transport, /row\.resource_id\) !== id/)
  assert.doesNotMatch(transport, /\/api\/v1\/numbering-series\b/)
})

test('Editing and toggling use each row Resource; form Resource does not change the filter', () => {
  assert.match(view, /const formResourceId = ref\(''\)/)
  assert.match(view, /const formDraft = computed\(\(\) => \(\{ \.\.\.form\.value, resource_id: formResourceId\.value \}\)\)/)
  assert.match(view, /formResourceId\.value = selectedResourceId\.value/)
  assert.match(view, /formResourceId\.value = String\(row\.resource_id\)/)
  assert.match(view, /validateNumberingSeries\(formResourceId\.value, fullPayload\(\), editingId\.value\)/)
  assert.match(view, /const resourceId = formResourceId\.value[\s\S]*?saveNumberingSeries\(\s*resourceId,/)
  assert.match(view, /changeNumberingSeriesState\(\s*String\(row\.resource_id\),/)
  assert.match(view, /<select v-model="formResourceId" required :disabled="Boolean\(editingId\)">/)
  assert.doesNotMatch(view, /selectedResourceId\.value = String\(row\.resource_id\)/)
})

test('Aggregate table visibly identifies Resource and supports full pagination', () => {
  assert.match(view, /key: 'resource_id', labelKey: 'numbering\.resource'/)
  assert.match(view, /:total="rows\.length" :disabled="loadingResources \|\| loadingSeries"/)
  assert.match(view, /:show-page-size="false"/)
  assert.match(view, /:empty-dataset="!loadingResources && !loadingSeries && rows\.length === 0"/)
  assert.match(view, /const generation = \+\+seriesLoadGeneration/)
  assert.match(view, /if \(generation !== seriesLoadGeneration \|\| !canConfigure\.value\) return/)
})

test('All resource labels exist in both locales, and prior CRUD access is preserved', () => {
  assert.match(read('src/i18n/locales/en.ts'), /allResources: 'All resource'/)
  assert.match(read('src/i18n/locales/vi.ts'), /allResources: 'Tất cả Resource'/)
  assert.match(view, /hasBasePermissionKey\('resource\.configure_numbering'\)/)
  assert.match(view, /rowAllowedActions\(resourceById\.get/)
  assert.match(view, /@view="void openView\(row\)" @edit="openEdit\(row\)"/)
  assert.match(view, /confirmDiscard\(\)/)
})
