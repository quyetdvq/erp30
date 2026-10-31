// Focused source/contract regression: edit validation must not self-collide.
const { test } = require('node:test')
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { resolve } = require('node:path')
const root = resolve(__dirname, '../../..')
const read = (p) => readFileSync(resolve(root, p), 'utf8')

test('Edit passes its own ID to preview; Create does not invent one', () => {
  const view = read('frontend/src/features/workflow/pages/AutoNumberingView.vue')
  const service = read('frontend/src/services/autoNumbering.ts')
  assert.match(view, /validateNumberingSeries\(formResourceId\.value, fullPayload\(\), editingId\.value\)/)
  assert.match(service, /const body = seriesId \? \{ \.\.\.payload, series_id: seriesId \} : payload/)
  assert.match(service, /validateNumberingSeriesConfig\(\{[\s\S]*?body,/)
})

test('Backend validates edit series belongs to route Resource before excluding it', () => {
  const backend = read('backend/app/Modules/Numbering/Configuration/NumberingService.php')
  assert.match(backend, /array_key_exists\('series_id',\$payload\) \? NumberingRules::id\(\$payload\['series_id'\]\) : null/)
  assert.match(backend, /\$current = \$this->series\(\$resourceId,\$seriesId\)/)
  assert.match(backend, /\$this->assertIssuedFieldsUnchanged\(\$seriesId,\$current,\$candidate\)/)
  assert.match(backend, /\$this->validateCandidate\(\$resourceId,\$candidate,\$seriesId\)/)
  assert.match(backend, /if \(\$exceptId !== null && \(string\) \$old\['id'\]===\$exceptId\) continue/)
})

test('Canonical OpenAPI records optional edit context without altering the frozen contract', () => {
  const contract = read('contracts/openapi/openapi.yaml')
  assert.match(contract, /NumberingSeriesValidationRequest:[\s\S]*?series_id:/)
  assert.match(contract, /operationId: validateNumberingSeriesConfig[\s\S]*?requestBody: \{\$ref: '#\/components\/requestBodies\/NumberingSeriesValidationRequest'\}/)
  const frozen = read('docs/backend-rewrite/openapi-frozen-v1.2.4.yaml')
  assert.match(frozen, /operationId: validateNumberingSeriesConfig[\s\S]*?requestBody: \{\$ref: '#\/components\/requestBodies\/NumberingSeriesCreate'\}/)
})
